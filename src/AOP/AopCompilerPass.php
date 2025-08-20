<?php

declare(strict_types=1);

namespace App\AOP;

use ReflectionClass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * AOP Compiler Pass that weaves aspects into services
 * Implements Spring AOP style aspect weaving
 */
class AopCompilerPass implements CompilerPassInterface
{
    private array $advices = [];
    private array $aspects = [];

    public function process(ContainerBuilder $container): void
    {
        // First pass: collect all aspects and their advice
        $this->collectAspects($container);

        // Second pass: apply advice to matching services
        $this->applyAdvice($container);
    }

    private function collectAspects(ContainerBuilder $container): void
    {
        $aspectCount = 0;
        $adviceCount = 0;
        
        echo "🔍 AOP: Collecting aspects and advice...\n";
        
        foreach ($container->getDefinitions() as $id => $definition) {
            $class = $definition->getClass();
            
            if (!$class) {
                continue;
            }
            
            // Skip proxy classes to avoid conflicts
            if (str_ends_with($class, 'Proxy')) {
                continue;
            }
            
            // Only try to reflect classes that are likely to exist
            if (!$this->isClassLoadable($class)) {
                continue;
            }
            
            try {
                $reflection = new ReflectionClass($class);
                
                // Check if this is an aspect class
                if ($this->hasAspectAttribute($reflection)) {
                    $this->aspects[] = $class;
                    $aspectCount++;
                    
                    echo "  ✅ Found aspect: $class\n";
                    
                    // Collect advice from this aspect
                    foreach ($reflection->getMethods() as $method) {
                        if ($method->isPublic() && !$method->isConstructor()) {
                            $this->collectAdvice($method, $class);
                            $adviceCount++;
                        }
                    }
                }
            } catch (\ReflectionException $e) {
                // Skip classes that can't be reflected
                continue;
            }
        }
        
        echo "  📊 Total aspects: $aspectCount, Total advice: $adviceCount\n";
    }

    private function hasAspectAttribute(ReflectionClass $class): bool
    {
        $attributes = $class->getAttributes(Aspect::class);
        return !empty($attributes);
    }

    private function collectAdvice(\ReflectionMethod $method, string $aspectClass): void
    {
        $adviceTypes = [
            Before::class => 'before',
            After::class => 'after',
            Around::class => 'around',
            AfterReturning::class => 'afterReturning',
            AfterThrowing::class => 'afterThrowing'
        ];

        foreach ($adviceTypes as $adviceClass => $adviceType) {
            try {
                foreach ($method->getAttributes($adviceClass) as $attr) {
                    $instance = $attr->newInstance();
                    
                    // Store advice method mapped to its pointcut and type
                    $this->advices[] = [
                        'aspect' => $aspectClass,
                        'method' => $method->getName(),
                        'pointcut' => $instance->pointcut,
                        'type' => $adviceType
                    ];
                    
                    echo "    ✓ Found $adviceType advice: {$aspectClass}::{$method->getName()} -> {$instance->pointcut}\n";
                }
            } catch (\Exception $e) {
                // Skip advice that can't be instantiated
                continue;
            }
        }
    }

    private function applyAdvice(ContainerBuilder $container): void
    {
        echo "🏗️  AOP: Applying advice to services...\n";
        
        foreach ($container->getDefinitions() as $id => $definition) {
            $class = $definition->getClass();
            
            if (!$class) {
                continue;
            }
            
            // Skip proxy classes to avoid conflicts
            if (str_ends_with($class, 'Proxy')) {
                continue;
            }
            
            // Only process classes that are likely to exist and be loadable
            if (!$this->isClassLoadable($class)) {
                continue;
            }
            
            // Check if this service has any matching advice
            $matchingAdvice = $this->findMatchingAdvice($class);
            
            if (!empty($matchingAdvice)) {
                echo "  🔧 Applying AOP to service: $id ($class)\n";
                
                // Replace the service definition with a proxy
                $this->replaceWithProxy($container, $id, $definition, $class);
            }
        }
    }

    private function findMatchingAdvice(string $class): array
    {
        $matchingAdvice = [];
        
        foreach ($this->advices as $advice) {
            // Simple pointcut matching - you can enhance this
            if (str_contains($advice['pointcut'], $class) || 
                str_contains($advice['pointcut'], '*') ||
                str_contains($advice['pointcut'], 'App\\Service\\')) {
                $matchingAdvice[] = $advice;
            }
        }
        
        return $matchingAdvice;
    }

    private function replaceWithProxy(ContainerBuilder $container, string $id, Definition $definition, string $class): void
    {
        // Create proxy class name
        $proxyClass = $class . 'Proxy';
        
        // Create new definition for the proxy
        $proxyDefinition = new Definition($proxyClass);
        $proxyDefinition->setPublic($definition->isPublic());
        $proxyDefinition->setLazy($definition->isLazy());
        
        // Add container as constructor argument
        $proxyDefinition->addArgument(new Reference('service_container'));
        
        // Copy other constructor arguments from original definition
        foreach ($definition->getArguments() as $argument) {
            $proxyDefinition->addArgument($argument);
        }
        
        // Copy method calls
        foreach ($definition->getMethodCalls() as $methodCall) {
            $proxyDefinition->addMethodCall($methodCall[0], $methodCall[1]);
        }
        
        // Copy properties
        foreach ($definition->getProperties() as $property => $value) {
            $proxyDefinition->setProperty($property, $value);
        }
        
        // Replace the original definition
        $container->setDefinition($id, $proxyDefinition);
        
        echo "    ✅ Replaced with proxy: $proxyClass\n";
    }

    private function isClassLoadable(string $class): bool
    {
        // Skip classes that are likely to cause issues
        if (str_contains($class, 'Proxy') || 
            str_contains($class, 'Mock') ||
            str_contains($class, 'Test') ||
            str_starts_with($class, 'Symfony\\') ||
            str_starts_with($class, 'Doctrine\\') ||
            str_starts_with($class, 'Twig\\') ||
            str_starts_with($class, 'Monolog\\') ||
            str_starts_with($class, 'Psr\\') ||
            $class === 'Closure' ||
            $class === 'callable' ||
            $class === 'string' ||
            $class === 'int' ||
            $class === 'float' ||
            $class === 'bool' ||
            $class === 'array') {
            return false;
        }
        
        return true;
    }
}