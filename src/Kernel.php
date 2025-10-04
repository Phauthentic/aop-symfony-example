<?php

namespace App;

use App\AOP\AopCompilerPass;
use App\AOP\ClassLoader;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function boot(): void
    {
        // Register AOP proxy class loader before booting
        $proxyDir = $this->getProjectDir() . '/var/proxy-classes';
        $classLoader = new ClassLoader($proxyDir);
        $classLoader->initialize();
        $classLoader->register();
        
        parent::boot();
    }

    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new AopCompilerPass());
    }
}
