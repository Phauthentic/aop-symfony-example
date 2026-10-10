<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Symfony;

use Phauthentic\AOP\Attribute\Aspect;
use Phauthentic\AOP\Proxy\ProxyClassName;
use Phauthentic\AOP\Proxy\ProxyClassWriter;
use Phauthentic\AOP\Symfony\Compiler\AspectCollectorPass;
use Phauthentic\AOP\Symfony\Compiler\ProxyWeavingPass;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class PhauthenticAopBundle extends Bundle
{
    /** @var (\Closure(string): void)|null */
    private ?\Closure $autoloader = null;

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
     * Generated proxy classes live in the proxy directory, not in any Composer autoload path.
     * A dumped container may need them in a fresh process (also as parents of lazy ghost classes).
     */
    public function boot(): void
    {
        if (null === $this->container || !$this->container->hasParameter(ProxyWeavingPass::PROXY_DIR)) {
            return;
        }
        $dir = $this->container->getParameter(ProxyWeavingPass::PROXY_DIR);
        if (!\is_string($dir)) {
            return;
        }

        $writer = new ProxyClassWriter($dir);
        $this->autoloader = static function (string $class) use ($writer): void {
            if (str_starts_with($class, ProxyClassName::NAMESPACE_PREFIX) && is_file($file = $writer->pathFor($class))) {
                require $file;
            }
        };
        spl_autoload_register($this->autoloader);
    }

    public function shutdown(): void
    {
        if (null !== $this->autoloader) {
            spl_autoload_unregister($this->autoloader);
            $this->autoloader = null;
        }
    }
}
