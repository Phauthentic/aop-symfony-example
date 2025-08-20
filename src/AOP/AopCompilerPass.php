<?php

declare(strict_types=1);

namespace App\AOP;

use ReflectionClass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * AOP Compiler Pass that weaves aspects into services
 * Implements Spring AOP style aspect weaving
 */
class AopCompilerPass implements CompilerPassInterface
{
    private array $advices = [];

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
        
        foreach ($container->getDefinitions() as $id => $definition) {
            if (!$definition->getClass() || !class_exists($definition->getClass())) {
                continue;
            }

            try {
                $reflectionClass = new ReflectionClass($definition->getClass());

                // Only collect advice from classes that are explicitly marked as aspects
                if ($reflectionClass->getAttributes(Aspect::class)) {
                    $aspectCount++;
                    error_log(sprintf('AOP: Found aspect: %s (%s)', $id, $definition->getClass()));
                    
                    foreach ($reflectionClass->getMethods() as $method) {
                        // Only collect advice from public methods
                        if ($method->isPublic() && !$method->isConstructor()) {
                            $this->collectAdvice($method, $definition->getClass());
                            $adviceCount++;
                        }
                    }
                }
            } catch (\ReflectionException $e) {
                // Skip classes that can't be reflected
                continue;
            }
        }
        
        error_log(sprintf('AOP: Compiler pass completed. Found %d aspects with %d total advice methods.', $aspectCount, $adviceCount));
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
                    $pointcut = new Pointcut($instance->pointcut);
                    
                    // Store advice method mapped to its pointcut and type
                    $this->advices[] = [
                        'aspect' => $aspectClass,
                        'method' => $method->getName(),
                        'pointcut' => $pointcut,
                        'type' => $adviceType
                    ];
                    
                    error_log(sprintf(
                        'AOP: Collected %s advice from %s::%s with pointcut: %s',
                        $adviceType,
                        $aspectClass,
                        $method->getName(),
                        $instance->pointcut
                    ));
                }
            } catch (\Exception $e) {
                // Skip advice that can't be instantiated
                continue;
            }
        }
    }

    private function applyAdvice(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $id => $definition) {
            // Skip Symfony internal services and services without classes
            if ($this->shouldSkipService($id, $definition)) {
                continue;
            }

            if (!$definition->getClass() || !class_exists($definition->getClass())) {
                continue;
            }

            try {
                $class = $definition->getClass();
                $reflectionClass = new ReflectionClass($class);
                $matchingAdvices = [];

                // Find all matching advice for this service
                foreach ($this->advices as $advice) {
                    if ($this->adviceMatches($advice, $class, $reflectionClass)) {
                        $matchingAdvices[] = $advice;
                    }
                }

                if (!empty($matchingAdvices)) {
                    // Log which services have matching advice
                    error_log(sprintf(
                        'AOP: Service %s (%s) has %d matching advice(s)',
                        $id,
                        $class,
                        count($matchingAdvices)
                    ));
                    
                    // For now, just log that we found matching advice
                    // Proxy generation will be implemented later
                    $this->logAdviceApplication($id, $class, $matchingAdvices);
                }
            } catch (\Exception $e) {
                // Skip services that cause reflection errors
                continue;
            }
        }
    }

    private function shouldSkipService(string $id, Definition $definition): bool
    {
        // Skip Symfony internal services
        $skipPrefixes = [
            'cache.',
            'doctrine.',
            'router.',
            'session.',
            'security.',
            'twig.',
            'validator.',
            'serializer.',
            'messenger.',
            'mailer.',
            'http_client.',
            'lock.',
            'secrets.',
            'web_profiler.',
            'debug.',
            'data_collector.',
            'monolog.',
            'framework.',
            'kernel.',
            'console.'
        ];

        foreach ($skipPrefixes as $prefix) {
            if (str_starts_with($id, $prefix)) {
                return true;
            }
        }

        // Skip services that are already factories or abstract
        if ($definition->isAbstract() || $definition->getFactory()) {
            return true;
        }

        return false;
    }

    private function adviceMatches(array $advice, string $class, ReflectionClass $reflectionClass): bool
    {
        $pointcut = $advice['pointcut'];
        
        // Simple matching: just check if the pointcut matches the class
        $matches = $pointcut->matches($class, '*');
        

        
        return $matches;
    }



    private function logAdviceApplication(string $serviceId, string $className, array $advices): void
    {
        // For now, just log that we found matching advice
        // This will be replaced with actual proxy generation later
    }
}