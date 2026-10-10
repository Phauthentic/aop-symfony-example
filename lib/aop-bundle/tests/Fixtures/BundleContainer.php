<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Symfony\Tests\Fixtures;

use Phauthentic\AOP\Symfony\PhauthenticAopBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * A bare ContainerBuilder with the bundle loaded, for compiler pass tests.
 */
final class BundleContainer
{
    /**
     * @param array<string, mixed> $config
     */
    public static function create(array $config = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $base = TestKernel::newBaseDir();
        // Distinct, as in deployments with a read-only build directory: proxies belong next to the container.
        $container->setParameter('kernel.cache_dir', $base.'/cache');
        $container->setParameter('kernel.build_dir', $base.'/build');
        $bundle = new PhauthenticAopBundle();
        $extension = $bundle->getContainerExtension();
        \assert(null !== $extension);
        $container->registerExtension($extension);
        $container->loadFromExtension('phauthentic_aop', $config + ['include' => ['Phauthentic\AOP\Symfony\Tests\Fixtures\**']]);
        $bundle->build($container);

        return $container;
    }

    /**
     * Compiles without the removing passes, so private and inlined definitions stay inspectable.
     */
    public static function compile(ContainerBuilder $container): void
    {
        $container->getCompilerPassConfig()->setRemovingPasses([]);
        $container->compile();
    }
}
