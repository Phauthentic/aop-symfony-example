<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Symfony\Compiler;

use Phauthentic\AOP\Symfony\Container\AdvisorRegistryFactory;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Collects `#[Aspect]` services, validates their advice and wires the advisor registry and aspect locator.
 */
final class AspectCollectorPass implements CompilerPassInterface
{
    public const TAG = 'phauthentic_aop.aspect';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('phauthentic_aop.advisor_registry')) {
            return;
        }

        $locator = [];
        foreach (array_keys($container->findTaggedServiceIds(self::TAG)) as $id) {
            $class = $container->getParameterBag()->resolveValue($container->getDefinition($id)->getClass() ?? $id);
            // Also tracks the aspect class for container freshness.
            $reflection = \is_string($class) ? $container->getReflectionClass($class, false) : null;
            if (null === $reflection) {
                throw new InvalidArgumentException(sprintf('Aspect service "%s" has no loadable class.', $id));
            }
            $locator[$reflection->getName()] = new Reference($id);
        }

        $aspectClasses = array_keys($locator);
        AdvisorRegistryFactory::create($aspectClasses);

        $container->getDefinition('phauthentic_aop.advisor_registry')->setArgument(0, $aspectClasses);
        $container->getDefinition('phauthentic_aop.aspect_resolver')->setArgument(0, new ServiceLocatorArgument($locator));
    }
}
