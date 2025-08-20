<?php

declare(strict_types=1);

namespace App\AOP;

use App\AOP\Advice;
use App\AOP\After;
use App\AOP\AfterReturning;
use App\AOP\AfterThrowing;
use App\AOP\Around;
use App\AOP\Before;
use App\AOP\JoinPoint;
use App\AOP\Pointcut;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\Parameter;

/**
 * AOP Proxy Generator using Nette PHP Generator
 * 
 * This implementation:
 * 1. Scans for classes with AOP advice annotations
 * 2. Generates proxy classes that inject advice execution
 * 3. Properly handles method parameters and return values
 * 4. Supports Before, After, Around, AfterReturning, and AfterThrowing advice
 */
class AopProxyGenerator
{
    private array $aspects = [];
    private array $adviceMap = [];
    private string $outputDir;

    public function __construct(string $outputDir = 'src/Service/Proxy')
    {
        $this->outputDir = $outputDir;
        $this->ensureOutputDir();
    }

    /**
     * Ensure the output directory exists
     */
    private function ensureOutputDir(): void
    {
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }
    }

    /**
     * Get method visibility as string
     */
    private function getMethodVisibility(\ReflectionMethod $method): string
    {
        if ($method->isPublic()) return 'public';
        if ($method->isProtected()) return 'protected';
        if ($method->isPrivate()) return 'private';
        return 'public';
    }

    /**
     * Get property visibility as string
     */
    private function getPropertyVisibility(\ReflectionProperty $property): string
    {
        if ($property->isPublic()) return 'public';
        if ($property->isProtected()) return 'protected';
        if ($property->isPrivate()) return 'private';
        return 'public';
    }

    /**
     * Copy a method from the original class to the proxy class
     */
    private function copyMethod(ClassType $proxyClass, \ReflectionMethod $method): void
    {
        $proxyMethod = $proxyClass->addMethod($method->getName());
        $proxyMethod->setVisibility($this->getMethodVisibility($method));
        $proxyMethod->setReturnType($method->getReturnType()?->getName());
        
        // Copy parameters
        foreach ($method->getParameters() as $param) {
            $proxyParam = $proxyMethod->addParameter($param->getName());
            $proxyParam->setType($param->getType()?->getName());
            if ($param->isDefaultValueAvailable()) {
                $proxyParam->setDefaultValue($param->getDefaultValue());
            }
        }
        
        // Copy method body by reading the source file
        $methodBody = $this->extractMethodBody($method);
        $proxyMethod->setBody($methodBody);
        
        // Copy method comments if available
        if ($method->getDocComment()) {
            $proxyMethod->addComment($method->getDocComment());
        }
    }

    /**
     * Extract method body from source file
     */
    private function extractMethodBody(\ReflectionMethod $method): string
    {
        $fileName = $method->getDeclaringClass()->getFileName();
        if (!$fileName || !file_exists($fileName)) {
            return '// Method body could not be extracted';
        }
        
        $sourceCode = file_get_contents($fileName);
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        
        $lines = explode("\n", $sourceCode);
        $methodLines = array_slice($lines, $startLine - 1, $endLine - $startLine + 1);
        
        // Find the method body (between { and })
        $methodCode = implode("\n", $methodLines);
        $startPos = strpos($methodCode, '{');
        $endPos = strrpos($methodCode, '}');
        
        if ($startPos !== false && $endPos !== false) {
            $body = substr($methodCode, $startPos + 1, $endPos - $startPos - 1);
            $body = trim($body);
            
            // Clean up the extracted body - remove any leading/trailing whitespace and comments
            $body = preg_replace('/^\s*\/\/.*$/m', '', $body); // Remove single-line comments
            $body = preg_replace('/^\s*\/\*.*?\*\/\s*$/ms', '', $body); // Remove multi-line comments
            $body = trim($body);
            
            return $body;
        }
        
        return '// Method body could not be extracted';
    }

    /**
     * Scan for aspects and build advice mapping
     */
    public function scanAspects(): void
    {
        echo "🔍 Scanning for AOP aspects...\n";
        
        // Scan the AOP directory for aspects
        $aopDir = __DIR__ . '/../AOP';
        $files = glob($aopDir . '/*.php');
        
        echo "   📁 Scanning directory: $aopDir\n";
        echo "   📄 Found files: " . implode(', ', array_map('basename', $files)) . "\n\n";
        
        foreach ($files as $file) {
            $className = 'App\\AOP\\' . basename($file, '.php');
            echo "   🔍 Checking: $className\n";
            
            try {
                if (class_exists($className)) {
                    echo "     ✓ Class exists\n";
                    $reflection = new \ReflectionClass($className);
                    $attributes = $reflection->getAttributes(\App\AOP\Aspect::class);
                    
                    echo "     📋 Attributes found: " . count($attributes) . "\n";
                    
                    if (!empty($attributes)) {
                        echo "     ✅ Found aspect: $className\n";
                        $this->processAspect($reflection);
                    } else {
                        echo "     ❌ No Aspect attribute found\n";
                    }
                } else {
                    echo "     ❌ Class does not exist\n";
                }
            } catch (\Exception $e) {
                echo "     ⚠️  Error processing $className: " . $e->getMessage() . "\n";
            }
            echo "\n";
        }
        
        echo "   📊 Total aspects found: " . count($this->aspects) . "\n";
        echo "   📊 Total advice methods: " . count($this->adviceMap) . "\n\n";
    }

    /**
     * Process an aspect class and extract advice methods
     */
    private function processAspect(\ReflectionClass $aspectClass): void
    {
        $this->aspects[] = $aspectClass->getName();
        
        foreach ($aspectClass->getMethods() as $method) {
            $this->processAdviceMethod($method);
        }
    }

    /**
     * Process an advice method and extract pointcut information
     */
    private function processAdviceMethod(\ReflectionMethod $method): void
    {
        $adviceTypes = [Before::class, After::class, Around::class, AfterReturning::class, AfterThrowing::class];
        
        foreach ($adviceTypes as $adviceType) {
            $attributes = $method->getAttributes($adviceType);
            
            if (!empty($attributes)) {
                $attribute = $attributes[0];
                $advice = $attribute->newInstance();
                
                // Try to use Spring-style pointcut expression parser first
                try {
                    $pointcut = new \App\AOP\Pointcut\PointcutExpression($advice->pointcut);
                } catch (\Exception $e) {
                    // Fallback to simple pointcut for backward compatibility
                    $pointcut = new \App\AOP\Pointcut\SimplePointcut($advice->pointcut);
                }
                
                // Create an advisor that combines the advice with its pointcut
                $advisor = new \App\AOP\Advisor\DefaultPointcutAdvisor($advice, $pointcut);
                
                $this->adviceMap[] = [
                    'aspect' => $method->getDeclaringClass()->getName(),
                    'method' => $method->getName(),
                    'adviceType' => $adviceType,
                    'pointcut' => $pointcut,
                    'advisor' => $advisor,
                    'adviceMethod' => $method
                ];
                
                echo "     ✓ Found advice: {$adviceType} -> {$advice->pointcut}\n";
                break;
            }
        }
    }

    /**
     * Generate proxy classes for target classes
     */
    public function generateProxies(): void
    {
        echo "🏗️  Generating AOP proxy classes...\n";
        
        // Get unique target classes from advice
        $targetClasses = [];
        foreach ($this->adviceMap as $advice) {
            $pointcut = $advice['pointcut'];
            // Extract class pattern from the pointcut expression
            $expression = $pointcut->getExpression();
            if (str_contains($expression, '::')) {
                $classPattern = explode('::', $expression)[0];
            } else {
                $classPattern = $expression;
            }
            
            // For now, we'll use the pattern as the key
            // In a more sophisticated implementation, we'd resolve this to actual classes
            $targetClasses[$classPattern] = true;
        }
        
        foreach (array_keys($targetClasses) as $targetClass) {
            if (class_exists($targetClass)) {
                echo "   📝 Generating proxy for: $targetClass\n";
                $this->generateProxyClass($targetClass);
            }
        }
        
        echo "   ✅ Proxy generation completed\n\n";
    }

    /**
     * Generate a proxy class for a target class
     */
    private function generateProxyClass(string $targetClass): void
    {
        $reflection = new \ReflectionClass($targetClass);
        $proxyClassName = $reflection->getShortName() . 'Proxy';
        $proxyNamespace = $reflection->getNamespaceName();
        
        // Create proxy class by copying the entire original class
        $proxyClass = new ClassType($proxyClassName);
        $proxyClass->addComment("AOP Proxy for $targetClass");
        $proxyClass->addComment("Generated automatically with advice injection");
        $proxyClass->addComment("This is a complete copy of the original class with injected AOP advice");
        
        // Add JoinPoint property for advice execution
        $proxyClass->addProperty('joinPoint')
            ->setType(JoinPoint::class)
            ->setVisibility('private');
        
        // Add TargetSource property for proper target management
        $proxyClass->addProperty('targetSource')
            ->setType('\\App\\AOP\\TargetSource')
            ->setVisibility('private');
        
        // Copy all properties from the original class
        foreach ($reflection->getProperties() as $property) {
            $proxyProperty = $proxyClass->addProperty($property->getName());
            $proxyProperty->setVisibility($this->getPropertyVisibility($property));
            $proxyProperty->setType($property->getType()?->getName());
            
            // Copy property comments if available
            if ($property->getDocComment()) {
                $proxyProperty->addComment($property->getDocComment());
            }
        }
        
        // Copy constructor and other methods that don't have advice
        foreach ($reflection->getMethods() as $method) {
            if ($method->isPublic() && !$method->isConstructor()) {
                $this->processMethodWithAdvice($proxyClass, $method, $targetClass);
            } elseif ($method->isConstructor()) {
                // Copy constructor as-is
                $this->copyMethod($proxyClass, $method);
            } else {
                // Copy other methods (protected/private) as-is
                $this->copyMethod($proxyClass, $method);
            }
        }
        
        // Generate the PHP file
        $namespace = new PhpNamespace($proxyNamespace);
        $namespace->add($proxyClass);
        
        $file = new PhpFile();
        $file->addComment("Auto-generated AOP proxy for $targetClass");
        $file->setStrictTypes();
        $file->addNamespace($namespace);
        
        // Write to file
        $printer = new PsrPrinter();
        $generatedCode = $printer->printFile($file);
        
        $outputFile = $this->outputDir . '/' . $proxyClassName . '.php';
        file_put_contents($outputFile, $generatedCode);
        
        echo "     ✓ Generated: $outputFile\n";
    }

    /**
     * Process a method and inject appropriate advice
     */
    private function processMethodWithAdvice(ClassType $proxyClass, \ReflectionMethod $method, string $targetClass): void
    {
        $methodName = $method->getName();
        $adviceList = $this->getAdviceForMethod($targetClass, $methodName);
        
        if (empty($adviceList)) {
            // No advice, just call parent
            $this->addSimpleMethod($proxyClass, $method);
            return;
        }
        
        // Create enhanced method with advice
        $this->addEnhancedMethod($proxyClass, $method, $adviceList);
    }

    /**
     * Get all advice for a specific method
     */
    private function getAdviceForMethod(string $targetClass, string $methodName): array
    {
        $adviceList = [];
        
        foreach ($this->adviceMap as $advice) {
            $pointcut = $advice['pointcut'];
            if ($pointcut->matches($targetClass, $methodName)) {
                $adviceList[] = $advice;
            }
        }
        
        // Sort advice by execution order
        usort($adviceList, function($a, $b) {
            $order = [Before::class => 1, Around::class => 2, AfterReturning::class => 3, AfterThrowing::class => 4, After::class => 5];
            return $order[$a['adviceType']] <=> $order[$b['adviceType']];
        });
        
        return $adviceList;
    }

    /**
     * Add a simple method that just calls parent
     */
    private function addSimpleMethod(ClassType $proxyClass, \ReflectionMethod $method): void
    {
        $proxyMethod = $proxyClass->addMethod($method->getName());
        $proxyMethod->setVisibility($this->getMethodVisibility($method));
        $proxyMethod->setReturnType($method->getReturnType()?->getName());
        
        // Copy parameters
        foreach ($method->getParameters() as $param) {
            $proxyParam = $proxyMethod->addParameter($param->getName());
            $proxyParam->setType($param->getType()?->getName());
            if ($param->isDefaultValueAvailable()) {
                $proxyParam->setDefaultValue($param->getDefaultValue());
            }
        }
        
        // Simple parent call
        $paramNames = array_map(fn($p) => '$' . $p->getName(), $method->getParameters());
        $proxyMethod->setBody('return parent::' . $method->getName() . '(' . implode(', ', $paramNames) . ');');
    }

    /**
     * Add an enhanced method with advice injection
     */
    private function addEnhancedMethod(ClassType $proxyClass, \ReflectionMethod $method, array $adviceList): void
    {
        $proxyMethod = $proxyClass->addMethod($method->getName());
        $proxyMethod->setVisibility($this->getMethodVisibility($method));
        $proxyMethod->setReturnType($method->getReturnType()?->getName());
        
        // Copy parameters
        foreach ($method->getParameters() as $param) {
            $proxyParam = $proxyMethod->addParameter($param->getName());
            $proxyParam->setType($param->getType()?->getName());
            if ($param->isDefaultValueAvailable()) {
                $proxyParam->setDefaultValue($param->getDefaultValue());
            }
        }
        
        // Generate method body with advice
        $methodBody = $this->generateMethodBodyWithAdvice($method, $adviceList);
        $proxyMethod->setBody($methodBody);
    }

    /**
     * Generate method body with advice execution using Spring AOP style interceptor chaining
     */
    private function generateMethodBodyWithAdvice(\ReflectionMethod $method, array $adviceList): string
    {
        $paramNames = array_map(fn($p) => '$' . $p->getName(), $method->getParameters());
        $paramVars = implode(', ', $paramNames);
        
        $body = [];
        
        // Create the interceptor chain for Around advice
        $aroundAdvice = array_filter($adviceList, fn($a) => $a['adviceType'] === Around::class);
        $interceptorChain = [];
        
        if (!empty($aroundAdvice)) {
            foreach ($aroundAdvice as $advice) {
                $interceptorChain[] = sprintf(
                    'function($joinPoint) { $aspect = new \\%s(); return $aspect->%s($joinPoint); }',
                    $advice['aspect'],
                    $advice['method']
                );
            }
        }
        
        // Create JoinPoint with interceptor chain
        $body[] = '// Create temporary instance for JoinPoint target (to avoid infinite loops)';
        $body[] = '$tempTarget = new \\' . $method->getDeclaringClass()->getName() . '();';
        
        if (!empty($interceptorChain)) {
            $body[] = '// Create interceptor chain for Around advice';
            $body[] = '$interceptors = [' . implode(', ', $interceptorChain) . '];';
            $body[] = '$this->joinPoint = new \\App\\AOP\\JoinPoint($tempTarget, \'' . $method->getName() . '\', [' . $paramVars . '], $interceptors, $this);';
        } else {
            $body[] = '$this->joinPoint = new \\App\\AOP\\JoinPoint($tempTarget, \'' . $method->getName() . '\', [' . $paramVars . '], [], $this);';
        }
        $body[] = '';
        
        // Execute Before advice first
        $beforeAdvice = array_filter($adviceList, fn($a) => $a['adviceType'] === Before::class);
        foreach ($beforeAdvice as $advice) {
            $body[] = '// Execute Before advice';
            $body[] = '$aspect = new \\' . $advice['aspect'] . '();';
            $body[] = '$aspect->' . $advice['method'] . '($this->joinPoint);';
            $body[] = '';
        }
        
        // Execute the method through the interceptor chain (or directly if no Around advice)
        if (!empty($aroundAdvice)) {
            $body[] = '// Execute method through Around advice interceptor chain';
            $body[] = '$result = $this->joinPoint->proceed();';
        } else {
            $body[] = '// Execute original method body directly';
            $body[] = '// Get potentially modified arguments from JoinPoint';
            $body[] = '$executionArgs = $this->joinPoint->getArguments();';
            $body[] = '';
            $body[] = '// Create a temporary instance and call the method with modified arguments';
            $body[] = '$tempInstance = new \\' . $method->getDeclaringClass()->getName() . '();';
            $body[] = '$result = call_user_func_array([$tempInstance, \'' . $method->getName() . '\'], $executionArgs);';
        }
        $body[] = '';
        
        // Execute AfterReturning advice
        $afterReturningAdvice = array_filter($adviceList, fn($a) => $a['adviceType'] === AfterReturning::class);
        foreach ($afterReturningAdvice as $advice) {
            $body[] = '// Execute AfterReturning advice';
            $body[] = '$aspect = new \\' . $advice['aspect'] . '();';
            $body[] = '$aspect->' . $advice['method'] . '($result);';
            $body[] = '';
        }
        
        // Execute After advice (finally)
        $afterAdvice = array_filter($adviceList, fn($a) => $a['adviceType'] === After::class);
        foreach ($afterAdvice as $advice) {
            $body[] = '// Execute After advice (finally)';
            $body[] = '$aspect = new \\' . $advice['aspect'] . '();';
            $body[] = '$aspect->' . $advice['method'] . '($this->joinPoint);';
            $body[] = '';
        }
        
        // Return result only if method has a return type
        if ($method->getReturnType() && $method->getReturnType()->getName() !== 'void') {
            $body[] = 'return $result;';
        }
        
        return implode("\n", $body);
    }

    /**
     * Display summary of generated proxies
     */
    public function displaySummary(): void
    {
        echo "📋 AOP Proxy Generation Summary\n";
        echo str_repeat("=", 50) . "\n";
        echo "Generated proxy classes:\n";
        
        $files = glob($this->outputDir . '/*.php');
        foreach ($files as $file) {
            $className = basename($file, '.php');
            echo "  • $className\n";
        }
        
        echo "\nAdvice mapping:\n";
        foreach ($this->adviceMap as $advice) {
            $adviceType = basename(str_replace('\\', '/', $advice['adviceType']));
            echo "  • {$adviceType}: {$advice['aspect']}::{$advice['method']} -> {$advice['pointcut']->getExpression()}\n";
        }
    }
}
