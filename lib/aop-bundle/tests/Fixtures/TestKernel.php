<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Symfony\Tests\Fixtures;

use Phauthentic\AOP\Symfony\PhauthenticAopBundle;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Aspect\ArgsAspect;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Aspect\AuditAspect;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Aspect\BeanAspect;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Aspect\CounterAspect;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Aspect\LogAspect;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Excluded\ExcludedService;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Service\ArgsService;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Service\AuditedService;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Service\CallsService;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Service\CountedService;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Service\FactoryCreatedService;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Service\FinalNoInterfaceService;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Service\FinalService;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Service\LazyService;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Service\OrderSubscriber;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Service\PlainService;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Service\SelfCallingService;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Service\ShoutInterface;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public const SCRATCH_DIR = '/tmp/claude-1000/-home-florian-projects-symfony-aop/b141f6da-9d6f-43c9-a85c-7dec6c378374/scratchpad/aop-bundle-tests';

    /**
     * @param array<string, mixed> $aopConfig merged into the phauthentic_aop configuration
     */
    public function __construct(private readonly string $baseDir, private readonly array $aopConfig = [])
    {
        parent::__construct('test', true);
    }

    public static function newBaseDir(): string
    {
        return self::SCRATCH_DIR.'/'.bin2hex(random_bytes(6));
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new EarlyBootBundle();
        yield new PhauthenticAopBundle();
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return $this->baseDir.'/cache';
    }

    public function getLogDir(): string
    {
        return $this->baseDir.'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['secret' => 'test', 'test' => true]);
        $container->extension('phauthentic_aop', $this->aopConfig + [
            'include' => ['Phauthentic\AOP\Symfony\Tests\Fixtures\**'],
            'exclude' => ['Phauthentic\AOP\Symfony\Tests\Fixtures\Excluded\**'],
        ]);

        $services = $container->services()->defaults()->autowire()->autoconfigure();
        $services->set('fixture.service', PlainService::class)->public();
        $services->set('fixture.other', PlainService::class)->public();
        // Hot path services get no inlined class includes in the dumped container, only their definition file.
        $services->set('fixture.hot', PlainService::class)->public()->tag('container.hot_path');
        $services->set(FinalService::class)->public();
        $services->alias(ShoutInterface::class, FinalService::class)->public();
        $services->set(LazyService::class)->lazy()->public();
        $services->set('fixture.early', PlainService::class)->public();
        $services->set('fixture.early_lazy', LazyService::class)->lazy()->public();
        $services->set(CallsService::class)->call('setPrefix', ['pre-'])->public();
        $services->set(FactoryCreatedService::class)->factory([FactoryCreatedService::class, 'create'])->public();
        $services->set(FinalNoInterfaceService::class)->public();
        $services->set(SelfCallingService::class)->public();
        $services->set(OrderSubscriber::class);
        $services->set(ExcludedService::class)->public();
        $services->set(LogAspect::class);
        $services->set(BeanAspect::class);
        $services->set('fixture.audited', AuditedService::class)->public();
        $services->set(AuditAspect::class);
        $services->set('fixture.counted_a', CountedService::class)->public();
        $services->set('fixture.counted_b', CountedService::class)->public();
        $services->set(CounterAspect::class);
        $services->set(ArgsAspect::class);
        $services->set('fixture.args', ArgsService::class)->public();
    }
}
