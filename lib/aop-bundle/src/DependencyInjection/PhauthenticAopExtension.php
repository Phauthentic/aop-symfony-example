<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Symfony\DependencyInjection;

use Phauthentic\AOP\Symfony\Compiler\ProxyWeavingPass;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class PhauthenticAopExtension extends Extension
{
    /**
     * @param array<array<string, mixed>> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        /** @var array{enabled: bool, proxy_strategy: string, proxy_dir: string, include: list<string>, exclude: list<string>, strip_final: bool, strip_final_include: list<string>} $config */
        $config = $this->processConfiguration(new Configuration(), $configs);
        if (!$config['enabled']) {
            return;
        }

        (new PhpFileLoader($container, new FileLocator(\dirname(__DIR__).'/Resources/config')))->load('services.php');
        $container->setParameter(ProxyWeavingPass::PROXY_DIR, $config['proxy_dir']);
        $container->setParameter(ProxyWeavingPass::PROXY_STRATEGY, $config['proxy_strategy']);
        $container->setParameter(ProxyWeavingPass::INCLUDE, $config['include']);
        $container->setParameter(ProxyWeavingPass::EXCLUDE, $config['exclude']);
        $container->setParameter(ProxyWeavingPass::STRIP_FINAL, $config['strip_final']);
        $container->setParameter(ProxyWeavingPass::STRIP_FINAL_INCLUDE, $config['strip_final_include']);
    }
}
