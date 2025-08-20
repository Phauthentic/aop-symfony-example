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
        foreach ($container->getDefinitions() as $id => $definition) {
            if (!$definition->getClass() || !class_exists($definition->getClass())) {
                continue;
            }

            $reflectionClass = new ReflectionClass($definition->getClass());

            // Find Aspect classes
            if ($reflectionClass->getAttributes(Aspect::class)) {
                foreach ($reflectionClass->getMethods() as $method) {
                    // Collect all types of advice
                    $this->collectAdvice($method, $definition->getClass());
                }
            }
        }
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
            }
        }
    }

    private function applyAdvice(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $id => $definition) {
            if (!$definition->getClass() || !class_exists($definition->getClass())) {
                continue;
            }

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
                // Create a factory definition that will generate the proxy
                $this->createProxyDefinition($container, $id, $definition, $matchingAdvices);
            }
        }
    }

    private function adviceMatches(array $advice, string $class, ReflectionClass $reflectionClass): bool
    {
        $pointcut = $advice['pointcut'];
        
        // Check if class matches
        if (!$pointcut->matches($class, '*')) {
            return false;
        }

        // Check for annotation-based matching
        if ($pointcut->hasAnnotationMatching()) {
            return $this->checkAnnotationMatching($pointcut, $reflectionClass);
        }

        return true;
    }

    private function checkAnnotationMatching(Pointcut $pointcut, ReflectionClass $reflectionClass): bool
    {
        $annotationClass = $pointcut->getAnnotationClass();
        
        // Check if the class itself has the annotation
        if ($reflectionClass->getAttributes($annotationClass)) {
            return true;
        }

        // Check if any method has the annotation
        foreach ($reflectionClass->getMethods() as $method) {
            if ($method->getAttributes($annotationClass)) {
                return true;
            }
        }

        return false;
    }

    private function createProxyDefinition(ContainerBuilder $container, string $id, Definition $definition, array $advices): void
    {
        // Create a factory service that will generate the proxy
        $factoryId = $id . '.aop_factory';
        
        $factoryDefinition = new Definition();
        $factoryDefinition->setClass(ProxyGenerator::class);
        $factoryDefinition->setFactory([ProxyGenerator::class, 'createProxy']);
        $factoryDefinition->setArguments([
            new \Symfony\Component\DependencyInjection\Reference($id . '.inner'),
            $advices
        ]);
        
        $container->setDefinition($factoryId, $factoryDefinition);

        // Replace the original service definition with the factory
        $definition->setFactory([new \Symfony\Component\DependencyInjection\Reference($factoryId), 'createProxy']);
        $definition->setArguments([]);
    }
}