<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Symfony;

use Phauthentic\AOP\Attribute\Aspect;
use Phauthentic\AOP\Symfony\Compiler\AspectCollectorPass;
use Phauthentic\AOP\Symfony\Compiler\ProxyWeavingPass;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class PhauthenticAopBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->registerAttributeForAutoconfiguration(
            Aspect::class,
            static function (ChildDefinition $definition): void {
                $definition->addTag(AspectCollectorPass::TAG);
            },
        );
        // Both run after autowiring and decoration; the collector must run first.
        $container->addCompilerPass(new AspectCollectorPass(), PassConfig::TYPE_BEFORE_REMOVING, 1);
        $container->addCompilerPass(new ProxyWeavingPass(), PassConfig::TYPE_BEFORE_REMOVING);
    }

    /**
     * Woven services include the proxy directory's autoload file themselves; registering it here as well keeps proxy
     * classes loadable for code that needs them without instantiating a service first (e.g. unserialize()).
     */
    public function boot(): void
    {
        $dir = $this->container?->hasParameter(ProxyWeavingPass::PROXY_DIR) ? $this->container->getParameter(ProxyWeavingPass::PROXY_DIR) : null;
        if (\is_string($dir)) {
            ProxyAutoloader::register($dir);
        }
    }
}
