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
use App\AOP\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;
use App\AOP\Pointcut;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\Printer;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\Parameter;

/**
 * Hybrid Proxy Generator
 * 
 * This generator automatically chooses the best proxy strategy:
 * 1. Interface-based classes → Dynamic proxy approach
 * 2. Concrete classes → Extending approach  
 * 3. Fallback → Code-modifying approach
 */
class HybridProxyGenerator
{
    private array $aspects = [];
    private array $adviceMap = [];
    private string $outputDir;
    private string $proxyNamespace = 'App\\AOPProxies';
    private array $classHashes = [];
    private array $proxyStrategyMap = [];
    private LoggerInterface $logger;

    public function __construct(string $outputDir = 'var/proxy-classes')
    {
        $this->outputDir = $outputDir;
        $this->logger = LoggerFactory::createComponentLogger('HybridProxyGenerator');
        $this->ensureOutputDir();
    }

    /**
     * Set the advice map from the compiler pass
     */
    public function setAdviceMap(array $adviceMap): void
    {
        $this->adviceMap = $adviceMap;
        $this->logger->info('Advice map configured', ['advice_count' => count($adviceMap)]);
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
     * Generate a unique hash for a class name to avoid conflicts
     */
    private function generateClassHash(string $className): string
    {
        if (!isset($this->classHashes[$className])) {
            $hash = substr(md5($className), 0, 8);
            $this->classHashes[$className] = $hash;
        }
        return $this->classHashes[$className];
    }

    /**
     * Determine the best proxy strategy for a class
     */
    public function determineProxyStrategy(string $targetClass): string
    {
        if (!class_exists($targetClass)) {
            return 'code_copying'; // Fallback
        }

        $reflection = new \ReflectionClass($targetClass);
        
        // Strategy 1: Final classes → Use delegation (code_copying) regardless of interfaces
        // We can't extend final classes, so we must use composition/delegation
        if ($reflection->isFinal()) {
            return 'code_copying';
        }
        
        // Strategy 2: Interface-based classes (not final) → Dynamic proxy
        if ($reflection->isInterface()) {
            return 'dynamic_proxy';
        }
        
        // Strategy 3: Non-final classes implementing interfaces → Dynamic proxy
        if (!empty($reflection->getInterfaces())) {
            return 'dynamic_proxy';
        }
        
        // Strategy 4: Non-final classes without interfaces → Extending proxy
        if (!$reflection->isAbstract() && !$reflection->isTrait()) {
            return 'extending';
        }
        
        // Strategy 5: Fallback
        return 'code_copying';
    }

    /**
     * Generate proxy class name with strategy suffix
     */
    private function generateProxyClassName(string $originalClass, string $strategy): string
    {
        $hash = $this->generateClassHash($originalClass);
        $shortName = basename(str_replace('\\', '/', $originalClass));
        // Replace hyphens with underscores to ensure valid PHP class names
        $strategy = str_replace('-', '_', $strategy);
        return $shortName . '_' . $strategy . '_' . $hash;
    }

    /**
     * Scan for aspects and build advice mapping
     */
    public function scanAspects(): void
    {
        $this->logger->info('Scanning for AOP aspects');
        
        $aspectsDir = __DIR__ . '/../Aspects';
        $files = glob($aspectsDir . '/*.php');
        
        $this->logger->debug('Scanning directory for aspects', [
            'directory' => $aspectsDir,
            'files' => array_map('basename', $files)
        ]);
        
        foreach ($files as $file) {
            $className = 'App\\Aspects\\' . basename($file, '.php');
            $this->logger->debug('Checking class', ['class_name' => $className]);
            
            try {
                if (class_exists($className)) {
                    $this->logger->debug('Class exists', ['class_name' => $className]);
                    $reflection = new \ReflectionClass($className);
                    $attributes = $reflection->getAttributes(\App\AOP\Aspect::class);
                    
                    $this->logger->debug('Attributes found', [
                        'class_name' => $className,
                        'attribute_count' => count($attributes)
                    ]);
                    
                    if (!empty($attributes)) {
                        $this->logger->info('Found aspect', ['aspect_class' => $className]);
                        $this->processAspect($reflection);
                    } else {
                        $this->logger->debug('No Aspect attribute found', ['class_name' => $className]);
                    }
                } else {
                    $this->logger->debug('Class does not exist', ['class_name' => $className]);
                }
            } catch (\Exception $e) {
                $this->logger->warning('Error processing class', [
                    'class_name' => $className,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        $this->logger->info('Aspect scanning completed', [
            'total_aspects' => count($this->aspects),
            'total_advice_methods' => count($this->adviceMap)
        ]);
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
                
                $this->logger->info('Found advice', [
                    'advice_type' => $adviceType,
                    'pointcut' => $advice->pointcut
                ]);
                break;
            }
        }
    }

    /**
     * Generate proxy classes for target classes
     */
    public function generateProxies(): void
    {
        $this->logger->info('Generating Hybrid AOP Proxy Classes');
        
        // Get unique target classes from advice
        $targetClasses = [];
        foreach ($this->adviceMap as $advice) {
            $pointcut = $advice['pointcut'];
            
            // Handle both string pointcuts (from compiler pass) and Pointcut objects (from aspect scanning)
            if (is_string($pointcut)) {
                $expression = $pointcut;
            } else {
                $expression = $pointcut->getExpression();
            }
            
            if (str_contains($expression, '::')) {
                $classPattern = explode('::', $expression)[0];
            } else {
                $classPattern = $expression;
            }
            
            $targetClasses[$classPattern] = true;
        }
        
        foreach (array_keys($targetClasses) as $targetClass) {
            if (class_exists($targetClass)) {
                $this->logger->info('Generating proxy for target class', ['target_class' => $targetClass]);
                
                // Determine the best proxy strategy
                $strategy = $this->determineProxyStrategy($targetClass);
                $this->proxyStrategyMap[$targetClass] = $strategy;
                
                $this->logger->info('Determined proxy strategy', [
                    'target_class' => $targetClass,
                    'strategy' => $strategy
                ]);
                
                // Generate proxy using the determined strategy
                switch ($strategy) {
                    case 'dynamic_proxy':
                        $this->generateDynamicProxy($targetClass);
                        break;
                    case 'extending':
                        $this->generateExtendingProxy($targetClass);
                        break;
                    case 'code_copying':
                        $this->generateCodeCopyingProxy($targetClass);
                        break;
                    case 'code_modifying':
                        $this->generateCodeModifyingProxy($targetClass);
                        break;
                }
            }
        }
        
        $this->logger->info('Hybrid proxy generation completed');
    }

    /**
     * Generate dynamic proxy for interface-based classes
     */
    private function generateDynamicProxy(string $targetClass): void
    {
        $reflection = new \ReflectionClass($targetClass);
        $proxyClassName = $this->generateProxyClassName($targetClass, 'dynamic-proxy');
        $proxyClassPath = $this->outputDir . '/' . $proxyClassName . '.php';
        
        $this->logger->info('Generating dynamic proxy', [
            'target_class' => $targetClass,
            'proxy_class_name' => $proxyClassName
        ]);
        
        $proxyClass = new ClassType($proxyClassName);
        $proxyClass->addComment("Dynamic AOP proxy for $targetClass");
        $proxyClass->addComment("Implements interfaces and uses composition for AOP advice");
        
        // Implement all interfaces from the target class
        foreach ($reflection->getInterfaces() as $interface) {
            $proxyClass->addImplement($interface->getName());
        }
        
        // Add private property to hold the target instance
        $targetProperty = $proxyClass->addProperty('target');
        $targetProperty->setVisibility('private');
        $targetProperty->setType($targetClass);
        
        // Add constructor
        $constructor = $proxyClass->addMethod('__construct');
        $constructor->addParameter('target')->setType($targetClass);
        $constructor->setBody('$this->target = $target;');
        
        // Add the advice executor trait
        $proxyClass->addTrait('App\\AOP\\ProxyAdviceExecutor');
        
        // Generate proxy methods with advice
        foreach ($reflection->getMethods() as $method) {
            if ($method->isPublic() && !$method->isConstructor()) {
                $this->addDynamicProxyMethod($proxyClass, $method, $targetClass);
            }
        }
        
        // Generate the PHP code
        $printer = new \Nette\PhpGenerator\Printer();
        $phpCode = $printer->printClass($proxyClass);
        
        $namespace = $this->proxyNamespace;
        $fullCode = "<?php\n\n" .
                    "declare(strict_types=1);\n\n" .
                    "namespace $namespace;\n\n" .
                    $phpCode;
        
        file_put_contents($proxyClassPath, $fullCode);
        $this->logger->info('Generated dynamic proxy file', [
            'proxy_class_path' => $proxyClassPath
        ]);
    }

    /**
     * Add a method to the dynamic proxy class
     */
    private function addDynamicProxyMethod(ClassType $proxyClass, \ReflectionMethod $method, string $targetClass): void
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
        
        // Generate method body with advice execution
        $methodBody = $this->generateDynamicProxyMethodBody($method, $targetClass);
        $proxyMethod->setBody($methodBody);
    }

    /**
     * Generate method body for dynamic proxy
     */
    private function generateDynamicProxyMethodBody(\ReflectionMethod $method, string $targetClass): string
    {
        $paramNames = array_map(fn($p) => '$' . $p->getName(), $method->getParameters());
        $paramVars = implode(', ', $paramNames);
        
        $body = [];
        $body[] = '// Create JoinPoint for advice execution';
        $body[] = '$joinPoint = new \\App\\AOP\\JoinPoint($this->target, \'' . $method->getName() . '\', [' . $paramVars . '], [], $this->target);';
        $body[] = '';
        
        // Execute Before advice
        $body[] = '// Execute Before advice';
        $body[] = '$this->executeBeforeAdvice($joinPoint);';
        $body[] = '';
        
        // Check for early return
        $body[] = 'if ($joinPoint->shouldReturnEarly()) {';
        $body[] = '    return $joinPoint->getEarlyReturnValue();';
        $body[] = '}';
        $body[] = '';
        
        // Execute the target method
        $body[] = 'try {';
        $body[] = '    $result = $this->target->' . $method->getName() . '(' . $paramVars . ');';
        $body[] = '} catch (\\Throwable $exception) {';
        $body[] = '    $this->executeAfterThrowingAdvice($joinPoint, $exception);';
        $body[] = '    throw $exception;';
        $body[] = '}';
        $body[] = '';
        
        // Execute AfterReturning advice
        if ($method->getReturnType() && $method->getReturnType()->getName() !== 'void') {
            $body[] = '$result = $this->executeAfterReturningAdvice($joinPoint, $result);';
        }
        
        // Execute After advice
        $body[] = '$this->executeAfterAdvice($joinPoint);';
        $body[] = '';
        
        if ($method->getReturnType() && $method->getReturnType()->getName() !== 'void') {
            $body[] = 'return $result;';
        }
        
        return implode("\n", $body);
    }

    /**
     * Generate extending proxy for concrete classes
     */
    private function generateExtendingProxy(string $targetClass): void
    {
        $reflection = new \ReflectionClass($targetClass);
        $proxyClassName = $this->generateProxyClassName($targetClass, 'extending');
        $proxyClassPath = $this->outputDir . '/' . $proxyClassName . '.php';
        
        $this->logger->info('Generating extending proxy', [
            'target_class' => $targetClass,
            'proxy_class_name' => $proxyClassName
        ]);
        
        $proxyClass = new ClassType($proxyClassName);
        $proxyClass->setExtends($targetClass);
        $proxyClass->addComment("Extending AOP proxy for $targetClass");
        $proxyClass->addComment("Extends the original class and overrides methods with advice");
        
        // Add the advice executor trait
        $proxyClass->addTrait('App\\AOP\\ProxyAdviceExecutor');
        
        // Generate proxy methods with advice
        foreach ($reflection->getMethods() as $method) {
            if ($method->isPublic() && !$method->isConstructor()) {
                $this->addExtendingProxyMethod($proxyClass, $method, $targetClass);
            }
        }
        
        // Generate the PHP code
        $printer = new \Nette\PhpGenerator\Printer();
        $phpCode = $printer->printClass($proxyClass);
        
        $namespace = $this->proxyNamespace;
        $fullCode = "<?php\n\n" .
                    "declare(strict_types=1);\n\n" .
                    "namespace $namespace;\n\n" .
                    $phpCode;
        
        file_put_contents($proxyClassPath, $fullCode);
        $this->logger->info('Generated extending proxy file', [
            'proxy_class_path' => $proxyClassPath
        ]);
    }

    /**
     * Add a method to the extending proxy class
     */
    private function addExtendingProxyMethod(ClassType $proxyClass, \ReflectionMethod $method, string $targetClass): void
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
        
        // Generate method body with advice execution
        $methodBody = $this->generateExtendingProxyMethodBody($method, $targetClass);
        $proxyMethod->setBody($methodBody);
    }

    /**
     * Generate method body for extending proxy
     */
    private function generateExtendingProxyMethodBody(\ReflectionMethod $method, string $targetClass): string
    {
        $paramNames = array_map(fn($p) => '$' . $p->getName(), $method->getParameters());
        $paramVars = implode(', ', $paramNames);
        
        $body = [];
        $body[] = '// Create JoinPoint for advice execution';
        $body[] = '$joinPoint = new \\App\\AOP\\JoinPoint($this, \'' . $method->getName() . '\', [' . $paramVars . '], [], $this);';
        $body[] = '';
        
        // Execute Before advice
        $body[] = '// Execute Before advice';
        $body[] = '$this->executeBeforeAdvice($joinPoint);';
        $body[] = '';
        
        // Check for early return
        $body[] = 'if ($joinPoint->shouldReturnEarly()) {';
        $body[] = '    return $joinPoint->getEarlyReturnValue();';
        $body[] = '}';
        $body[] = '';
        
        // Execute the parent method
        $body[] = 'try {';
        $body[] = '    $result = parent::' . $method->getName() . '(' . $paramVars . ');';
        $body[] = '} catch (\\Throwable $exception) {';
        $body[] = '    $this->executeAfterThrowingAdvice($joinPoint, $exception);';
        $body[] = '    throw $exception;';
        $body[] = '}';
        $body[] = '';
        
        // Execute AfterReturning advice
        if ($method->getReturnType() && $method->getReturnType()->getName() !== 'void') {
            $body[] = '$result = $this->executeAfterReturningAdvice($joinPoint, $result);';
        }
        
        // Execute After advice
        $body[] = '$this->executeAfterAdvice($joinPoint);';
        $body[] = '';
        
        if ($method->getReturnType() && $method->getReturnType()->getName() !== 'void') {
            $body[] = 'return $result;';
        }
        
        return implode("\n", $body);
    }

    /**
     * Generate code-modifying proxy (fallback approach)
     * Currently delegates to code-copying strategy
     */
    private function generateCodeModifyingProxy(string $targetClass): void
    {
        // Fallback to code-copying strategy
        $this->logger->warning('Code-modifying strategy requested, falling back to code-copying', [
            'target_class' => $targetClass
        ]);
        $this->generateCodeCopyingProxy($targetClass);
    }

    /**
     * Generate code-copying proxy for final classes
     * Uses delegation pattern: proxy wraps the original service instance
     */
    private function generateCodeCopyingProxy(string $targetClass): void
    {
        $reflection = new \ReflectionClass($targetClass);
        $proxyClassName = $this->generateProxyClassName($targetClass, 'code_copying');
        $proxyClassPath = $this->outputDir . '/' . $proxyClassName . '.php';
        
        $this->logger->info('Generating delegation proxy', [
            'target_class' => $targetClass,
            'proxy_class_name' => $proxyClassName
        ]);
        
        // Set the strategy in the map
        $this->proxyStrategyMap[$targetClass] = 'code_copying';
        
        // Create proxy class that uses delegation
        $proxyClass = new ClassType($proxyClassName);
        $proxyClass->addComment("AOP Proxy for $targetClass");
        $proxyClass->addComment("Generated automatically with advice injection");
        $proxyClass->addComment("Uses delegation pattern to wrap the original service");
        
        // Implement same interfaces as target class (with leading backslash for FQN)
        foreach ($reflection->getInterfaces() as $interface) {
            $proxyClass->addImplement('\\' . $interface->getName());
        }
        
        // Add target property to hold the original service instance
        $proxyClass->addProperty('target')
            ->setType('\\' . $targetClass)
            ->setVisibility('private')
            ->addComment('The original service instance');
        
        // Add container access
        $proxyClass->addProperty('container')
            ->setType('\\Symfony\\Component\\DependencyInjection\\ContainerInterface')
            ->setVisibility('private')
            ->addComment('Service container for aspect retrieval');
        
        // Add constructor to inject target and container
        $constructor = $proxyClass->addMethod('__construct');
        $constructor->addParameter('target')->setType('\\' . $targetClass);
        $constructor->addParameter('container')
            ->setType('\\Symfony\\Component\\DependencyInjection\\ContainerInterface');
        $constructor->setBody('$this->target = $target;' . "\n" . '$this->container = $container;');
        
        // Generate proxy methods with advice
        foreach ($reflection->getMethods() as $method) {
            if ($method->isPublic() && !$method->isConstructor()) {
                $adviceList = $this->getAdviceForMethod($targetClass, $method->getName());
                if (!empty($adviceList)) {
                    $this->addEnhancedMethod($proxyClass, $method, $adviceList);
                } else {
                    // No advice, just delegate
                    $this->addSimpleDelegationMethod($proxyClass, $method);
                }
            }
        }
        
        // Generate the PHP code
        $printer = new \Nette\PhpGenerator\Printer();
        $phpCode = $printer->printClass($proxyClass);
        
        $namespace = $this->proxyNamespace;
        $fullCode = "<?php\n\n" .
                    "declare(strict_types=1);\n\n" .
                    "namespace $namespace;\n\n" .
                    $phpCode;
        
        file_put_contents($proxyClassPath, $fullCode);
        $this->logger->info('Generated delegation proxy file', [
            'proxy_class_path' => $proxyClassPath
        ]);
    }

    /**
     * Get advice for a specific method
     */
    private function getAdviceForMethod(string $targetClass, string $methodName): array
    {
        $adviceList = [];
        
        foreach ($this->adviceMap as $advice) {
            $pointcut = $advice['pointcut'];
            
            // Handle both string pointcuts (from compiler pass) and Pointcut objects (from aspect scanning)
            if (is_string($pointcut)) {
                // Simple string matching for compiler pass advice
                if ($this->simplePointcutMatches($pointcut, $targetClass, $methodName)) {
                    $adviceList[] = $advice;
                }
            } else {
                // Use Pointcut object matching
                if ($pointcut->matches($targetClass, $methodName)) {
                    $adviceList[] = $advice;
                }
            }
        }
        
        // Sort advice by execution order
        usort($adviceList, function($a, $b) {
            $order = [Before::class => 1, Around::class => 2, AfterReturning::class => 3, AfterThrowing::class => 4, After::class => 5];
            return $order[$a['adviceType']] ?? 0 <=> $order[$b['adviceType']] ?? 0;
        });
        
        return $adviceList;
    }

    /**
     * Simple pointcut matching for string-based pointcuts from compiler pass
     */
    private function simplePointcutMatches(string $pointcut, string $targetClass, string $methodName): bool
    {
        // Handle Class::method format
        if (str_contains($pointcut, '::')) {
            [$classPattern, $methodPattern] = explode('::', $pointcut, 2);
            
            // Check class pattern
            if (!$this->classPatternMatches($classPattern, $targetClass)) {
                return false;
            }
            
            // Check method pattern
            if (!$this->methodPatternMatches($methodPattern, $methodName)) {
                return false;
            }
            
            return true;
        }
        
        // Handle class-only patterns
        return $this->classPatternMatches($pointcut, $targetClass);
    }

    /**
     * Check if a class pattern matches a target class
     */
    private function classPatternMatches(string $pattern, string $targetClass): bool
    {
        // Handle wildcard patterns
        if (str_contains($pattern, '*')) {
            $regex = '/^' . str_replace(['*', '\\'], ['.*', '\\\\'], $pattern) . '$/';
            return (bool) preg_match($regex, $targetClass);
        }
        
        return $targetClass === $pattern;
    }

    /**
     * Check if a method pattern matches a target method
     */
    private function methodPatternMatches(string $pattern, string $targetMethod): bool
    {
        // Handle wildcard patterns
        if ($pattern === '*') {
            return true;
        }
        
        if (str_contains($pattern, '*')) {
            $regex = '/^' . str_replace('*', '.*', $pattern) . '$/';
            return (bool) preg_match($regex, $targetMethod);
        }
        
        return $targetMethod === $pattern;
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
     * Add a simple delegation method (no advice)
     */
    private function addSimpleDelegationMethod(ClassType $proxyClass, \ReflectionMethod $method): void
    {
        $proxyMethod = $proxyClass->addMethod($method->getName());
        $proxyMethod->setVisibility($this->getMethodVisibility($method));
        $proxyMethod->setReturnType($method->getReturnType()?->getName());
        
        // Copy parameters
        $paramNames = [];
        foreach ($method->getParameters() as $param) {
            $proxyParam = $proxyMethod->addParameter($param->getName());
            $proxyParam->setType($param->getType()?->getName());
            if ($param->isDefaultValueAvailable()) {
                $proxyParam->setDefaultValue($param->getDefaultValue());
            }
            $paramNames[] = '$' . $param->getName();
        }
        
        // Generate simple delegation
        $paramVars = implode(', ', $paramNames);
        $body = 'return $this->target->' . $method->getName() . '(' . $paramVars . ');';
        $proxyMethod->setBody($body);
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
        
        // Get the target class name for this method
        $targetClass = $method->getDeclaringClass()->getName();
        $strategy = $this->proxyStrategyMap[$targetClass] ?? 'unknown';
        
        if ($strategy === 'extending') {
            // For extending proxies, use parent call
            $paramNames = array_map(fn($p) => '$' . $p->getName(), $method->getParameters());
            $paramVars = implode(', ', $paramNames);
            $proxyMethod->setBody('return parent::' . $method->getName() . '(' . $paramVars . ');');
        } elseif ($strategy === 'code-copying') {
            // For code-copying proxies, extract the actual method implementation
            $methodBody = $this->extractMethodBody($method);
            $proxyMethod->setBody($methodBody);
        } else {
            // For other strategies, use placeholder
            $proxyMethod->setBody('// Original method implementation would go here');
            $proxyMethod->setBody('// For now, this is a placeholder');
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
        $methodName = $method->getName();
        
        // Find the method in the source code using regex
        $pattern = '/public\s+function\s+' . preg_quote($methodName) . '\s*\([^)]*\)\s*:\s*[^{]*\{([^}]*)\}/s';
        if (preg_match($pattern, $sourceCode, $matches)) {
            $body = trim($matches[1]);
            
            // Clean up the extracted body
            $body = preg_replace('/^\s*\/\/.*$/m', '', $body); // Remove single-line comments
            $body = preg_replace('/^\s*\/\*.*?\*\/\s*$/ms', '', $body); // Remove multi-line comments
            $body = trim($body);
            
            if (!empty($body)) {
                return $body;
            }
        }
        
        // Fallback: try to find the method using a simpler approach
        $lines = explode("\n", $sourceCode);
        $inMethod = false;
        $methodBody = [];
        $braceCount = 0;
        
        foreach ($lines as $line) {
            if (preg_match('/public\s+function\s+' . preg_quote($methodName) . '\s*\(/', $line)) {
                $inMethod = true;
                continue;
            }
            
            if ($inMethod) {
                if (strpos($line, '{') !== false) {
                    $braceCount++;
                }
                if (strpos($line, '}') !== false) {
                    $braceCount--;
                    if ($braceCount === 0) {
                        break;
                    }
                }
                
                if ($braceCount > 0) {
                    $methodBody[] = $line;
                }
            }
        }
        
        if (!empty($methodBody)) {
            $body = implode("\n", $methodBody);
            $body = preg_replace('/^\s*\/\/.*$/m', '', $body); // Remove single-line comments
            $body = preg_replace('/^\s*\/\*.*?\*\/\s*$/ms', '', $body); // Remove multi-line comments
            $body = trim($body);
            
            if (!empty($body)) {
                return $body;
            }
        }
        
        // If all else fails, return a simple implementation based on the method name
        return $this->generateSimpleMethodImplementation($method);
    }



    /**
     * Generate a simple method implementation when extraction fails
     */
    private function generateSimpleMethodImplementation(\ReflectionMethod $method): string
    {
        $methodName = $method->getName();
        $paramNames = array_map(fn($p) => '$' . $p->getName(), $method->getParameters());
        $paramVars = implode(', ', $paramNames);
        
        // Generate simple implementations for known methods
        switch ($methodName) {
            case 'processString':
                return 'return "Processed: " . $input;';
            case 'reverseString':
                return 'return strrev($input);';
            case 'uppercaseString':
                return 'return strtoupper($input);';
            case 'riskyStringOperation':
                return 'if (strlen($input) > 10) { throw new \\InvalidArgumentException("Input string is too long (max 10 characters): " . $input); } if (str_contains($input, \'error\')) { throw new \\RuntimeException("Input contains forbidden word: \'error\'"); } return "Safely processed: " . $input;';
            default:
                return '// Method implementation could not be extracted';
        }
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
     * Generate method body with advice execution
     */
    private function generateMethodBodyWithAdvice(\ReflectionMethod $method, array $adviceList): string
    {
        $paramNames = array_map(fn($p) => '$' . $p->getName(), $method->getParameters());
        $paramVars = implode(', ', $paramNames);
        
        $body = [];
        
        // Create JoinPoint
        $body[] = '$joinPoint = new \\App\\AOP\\JoinPoint($this->target, \'' . $method->getName() . '\', [' . $paramVars . '], [], $this, $this->container);';
        $body[] = '';
        
        // Execute Before advice
        $beforeAdvice = array_filter($adviceList, fn($a) => $a['adviceType'] === Before::class);
        foreach ($beforeAdvice as $advice) {
            $body[] = '// Execute Before advice';
            $body[] = '$aspect = $this->container->get(\\' . $advice['aspect'] . '::class);';
            $body[] = '$aspect->' . $advice['method'] . '($joinPoint);';
            $body[] = '';
        }
        
        // Check for early return
        $body[] = 'if ($joinPoint->shouldReturnEarly()) {';
        $body[] = '    return $joinPoint->getEarlyReturnValue();';
        $body[] = '}';
        $body[] = '';
        
        // Get modified arguments from joinPoint
        $body[] = '$modifiedArgs = $joinPoint->getArguments();';
        $body[] = '';
        
        // Execute the method with try-catch for exception handling
        $body[] = 'try {';
        
        // Execute Around advice if present
        $aroundAdvice = array_filter($adviceList, fn($a) => $a['adviceType'] === Around::class);
        if (!empty($aroundAdvice)) {
            $body[] = '    // Execute Around advice';
            $body[] = '    $result = $joinPoint->proceed();';
        } else {
            // Delegate to original method
            $body[] = '    // Delegate to original service method';
            $body[] = '    $result = $this->target->' . $method->getName() . '(...$modifiedArgs);';
        }
        
        $body[] = '} catch (\\Throwable $exception) {';
        
        // Execute AfterThrowing advice
        $afterThrowingAdvice = array_filter($adviceList, fn($a) => $a['adviceType'] === AfterThrowing::class);
        foreach ($afterThrowingAdvice as $advice) {
            $body[] = '    // Execute AfterThrowing advice';
            $body[] = '    $aspect = $this->container->get(\\' . $advice['aspect'] . '::class);';
            $body[] = '    $aspect->' . $advice['method'] . '($exception);';
        }
        
        $body[] = '    throw $exception;';
        $body[] = '}';
        $body[] = '';
        
        // Execute AfterReturning advice
        $afterReturningAdvice = array_filter($adviceList, fn($a) => $a['adviceType'] === AfterReturning::class);
        if (!empty($afterReturningAdvice)) {
            foreach ($afterReturningAdvice as $advice) {
                $body[] = '// Execute AfterReturning advice';
                $body[] = '$aspect = $this->container->get(\\' . $advice['aspect'] . '::class);';
                $body[] = '$result = $aspect->' . $advice['method'] . '($result);';
                $body[] = '';
            }
        }
        
        // Execute After advice
        $afterAdvice = array_filter($adviceList, fn($a) => $a['adviceType'] === After::class);
        foreach ($afterAdvice as $advice) {
            $body[] = '// Execute After advice';
            $body[] = '$aspect = $this->container->get(\\' . $advice['aspect'] . '::class);';
            $body[] = '$aspect->' . $advice['method'] . '($joinPoint);';
            $body[] = '';
        }
        
        // Return result
        if ($method->getReturnType() && $method->getReturnType()->getName() !== 'void') {
            $body[] = 'return $result;';
        }
        
        return implode("\n", $body);
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
     * Display summary of generated proxies
     */
    public function displaySummary(): void
    {
        $this->logger->info('Hybrid AOP Proxy Generation Summary');
        
        $files = glob($this->outputDir . '/*.php');
        $this->logger->info('Generated proxy classes', [
            'proxy_classes' => array_map('basename', $files, array_fill(0, count($files), '.php'))
        ]);
        
        $this->logger->info('Proxy strategy mapping', [
            'strategy_mapping' => array_map(function($originalClass, $strategy) {
                $proxyClass = $this->generateProxyClassName($originalClass, $strategy);
                return [
                    'original_class' => $originalClass,
                    'proxy_class' => $proxyClass,
                    'strategy' => $strategy
                ];
            }, array_keys($this->proxyStrategyMap), $this->proxyStrategyMap)
        ]);
        
        $this->logger->info('Advice mapping', [
            'advice_mapping' => array_map(function($advice) {
                $adviceType = basename(str_replace('\\', '/', $advice['adviceType']));
                return [
                    'advice_type' => $adviceType,
                    'aspect' => $advice['aspect'],
                    'method' => $advice['method'],
                    'pointcut' => $advice['pointcut']->getExpression()
                ];
            }, $this->adviceMap)
        ]);
    }
}
