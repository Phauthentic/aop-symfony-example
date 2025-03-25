<?php

declare(strict_types=1);

namespace App\AOP;

use App\Service\UserTestClass;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 *
 */
class AopCompilerPass implements CompilerPassInterface
{
    private array $advices = [];

    public function process(ContainerBuilder $container)
    {
        foreach ($container->getDefinitions() as $id => $definition) {
            if (!$definition->getClass() || !class_exists($definition->getClass())) {
                continue;
            }

            $reflectionClass = new ReflectionClass($definition->getClass());

            // Find Aspect classes
            if ($reflectionClass->getAttributes(Aspect::class)) {
                foreach ($reflectionClass->getMethods() as $method) {
                    foreach ($method->getAttributes(Around::class) as $attr) {
                        // Store advice method mapped to its pointcut
                        $this->advices[] = [
                            'aspect' => $definition->getClass(),
                            'method' => $method->getName(),
                            'pointcut' => new Pointcut(
                                class: $reflectionClass->getName(),
                                method: $method->getName(),
                            )
                        ];
                    }
                }
            }
        }

        // Now replace matching services with proxies
        foreach ($container->getDefinitions() as $id => $definition) {
            if (!$definition->getClass() || !class_exists($definition->getClass())) {
                continue;
            }

            $class = $definition->getClass();
            $reflectionClass = new ReflectionClass($class);

            foreach ($reflectionClass->getMethods() as $method) {
                foreach ($this->advices as $advice) {
                    if ($advice['pointcut']->matches($class, $method->getName())) {
                        $proxy = ProxyGenerator::createProxy(new $class(), [$advice]);
                        //dd($id);
                        $container->setDefinition($id, $container->register($id, get_class($proxy)));
                        dd($id);
                        break;
                    }
                }
            }
        }
    }
}

/*
class AopCompilerPass implements CompilerPassInterface
{
    private PointcutParser $pointcutParser;

    public function __construct()
    {
        $this->pointcutParser = new PointcutParser();
    }

    public function process(ContainerBuilder $container) {
        $this->findAspects($container);
    }

    public function findAspects(ContainerBuilder $container)
    {
        $aspects = [];
        foreach ($container->getDefinitions() as $id => $definition) {
            if (!$definition->getClass() || !class_exists($definition->getClass())) {
                continue;
            }

            $reflectionClass = new ReflectionClass($definition->getClass());

            if ($reflectionClass->getAttributes(Aspect::class)) {
                $this->extractPointcuts($reflectionClass);
            }
        }
    }

    public function extractPointcuts(ReflectionClass $reflectionClass): array
    {
        $pointcutStrings = [];

        foreach ($reflectionClass->getMethods() as $method) {
            $attributes = $method->getAttributes(Around::class);
            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();
                $pointcutStrings[$reflectionClass->getName() . '::' . $method->getName()] = $instance->pointcut;
                $this->pointcutParser->parse($instance->pointcut);
            }
        }

        return $pointcutStrings;
    }
}
*/