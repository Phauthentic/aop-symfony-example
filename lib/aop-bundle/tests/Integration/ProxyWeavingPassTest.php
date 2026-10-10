<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Symfony\Tests\Integration;

use Phauthentic\AOP\Proxy\AdvisedInterface;
use Phauthentic\AOP\Proxy\ProxyClassName;
use Phauthentic\AOP\Proxy\ProxyClassWriter;
use Phauthentic\AOP\Symfony\Compiler\ProxyWeavingPass;
use Phauthentic\AOP\Symfony\ProxyAutoloader;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Aspect\BeanAspect;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Aspect\LogAspect;
use Phauthentic\AOP\Symfony\Tests\Fixtures\BundleContainer;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Excluded\ExcludedService;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Service\CallsService;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Service\FactoryCreatedService;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Service\FinalNoInterfaceService;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Service\FinalService;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Service\LazyService;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Service\OrderSubscriber;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Service\PlainService;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Service\SelfCallingService;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Service\ShoutInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(ProxyWeavingPass::class)]
final class ProxyWeavingPassTest extends TestCase
{
    /**
     * @param array<string, mixed> $config
     */
    private function compiled(array $config = []): ContainerBuilder
    {
        $container = BundleContainer::create($config + ['exclude' => ['Phauthentic\AOP\Symfony\Tests\Fixtures\Excluded\**']]);
        $container->register(LogAspect::class, LogAspect::class)->setAutoconfigured(true);
        $container->register(BeanAspect::class, BeanAspect::class)->setAutoconfigured(true);
        $container->register('fixture.service', PlainService::class)->setPublic(true);
        $container->register('fixture.other', PlainService::class)->setPublic(true);
        $container->register(FinalService::class, FinalService::class)->setPublic(true)->addTag('custom.tag', ['a' => 1]);
        $container->register(CallsService::class, CallsService::class)->setPublic(true)->addMethodCall('setPrefix', ['pre-']);
        $container->register(FactoryCreatedService::class, FactoryCreatedService::class)->setPublic(true)->setFactory([FactoryCreatedService::class, 'create']);
        $container->register(FactoryCreatedService::class.'.with_file', FactoryCreatedService::class)->setPublic(true)->setFile(__FILE__);
        $container->register(LazyService::class, LazyService::class)->setPublic(true)->setLazy(true);
        $container->register(FinalNoInterfaceService::class, FinalNoInterfaceService::class)->setPublic(true);
        $container->register(SelfCallingService::class, SelfCallingService::class)->setPublic(true);
        $container->register(OrderSubscriber::class, OrderSubscriber::class)->addTag('kernel.event_subscriber');
        $container->register('fixture.untagged_subscriber', OrderSubscriber::class)->setPublic(true);
        $container->register(ExcludedService::class, ExcludedService::class)->setPublic(true);
        BundleContainer::compile($container);

        return $container;
    }

    /**
     * @return array{advised: array<string, array{class: string, proxy: string, strategy: string, methods: array<string, list<string>>, selfInvocations?: list<array{caller: string, method: string, file: string, line: int}>}>, skipped: array<string, array{class: string, reason: string}>}
     */
    private static function report(ContainerBuilder $container): array
    {
        /** @var array{advised: array<string, array{class: string, proxy: string, strategy: string, methods: array<string, list<string>>, selfInvocations?: list<array{caller: string, method: string, file: string, line: int}>}>, skipped: array<string, array{class: string, reason: string}>} $report */
        $report = $container->getParameter('phauthentic_aop.weaving_report');

        return $report;
    }

    #[Test]
    public function subclassStrategySwapsTheClassAndInjectsTheRuntime(): void
    {
        $container = $this->compiled();
        $definition = $container->getDefinition('fixture.service');
        $proxyClass = (string) $definition->getClass();

        self::assertStringStartsWith(ProxyClassName::NAMESPACE_PREFIX, $proxyClass);
        self::assertTrue(class_exists($proxyClass, false), 'proxy class is loaded during the build');
        self::assertTrue(is_subclass_of($proxyClass, PlainService::class));
        self::assertTrue(is_subclass_of($proxyClass, AdvisedInterface::class));
        self::assertEquals([['__aopSetRuntime', [new Reference('phauthentic_aop.runtime')]]], $definition->getMethodCalls());

        $dir = $container->getParameterBag()->resolveValue('%kernel.build_dir%/phauthentic_aop');
        self::assertIsString($dir);
        self::assertFileExists((new ProxyClassWriter($dir))->pathFor($proxyClass));
    }

    #[Test]
    public function wovenServicesIncludeTheProxyAutoloadFile(): void
    {
        $container = $this->compiled();
        $dir = $container->getParameterBag()->resolveValue('%kernel.build_dir%/phauthentic_aop');
        self::assertIsString($dir);
        $file = $dir.'/'.ProxyAutoloader::FILE;

        self::assertSame($file, $container->getDefinition('fixture.service')->getFile());
        self::assertSame($file, $container->getDefinition(FinalService::class)->getFile());
        self::assertSame(ProxyAutoloader::fileContents(), file_get_contents($file));
    }

    #[Test]
    public function lazyServiceGetsALazyProxyOfTheTargetClass(): void
    {
        $definition = $this->compiled()->getDefinition(LazyService::class);

        self::assertTrue($definition->isLazy());
        self::assertSame([['interface' => LazyService::class]], $definition->getTag('proxy'));
    }

    #[Test]
    public function serviceWithAFileIsDecoratedInsteadOfSubclassed(): void
    {
        $container = $this->compiled();

        self::assertSame(__FILE__, $container->getDefinition(FactoryCreatedService::class.'.with_file.aop_target')->getFile());
        self::assertSame('interface', self::report($container)['advised'][FactoryCreatedService::class.'.with_file']['strategy']);
    }

    #[Test]
    public function existingMethodCallsAreKeptBeforeTheRuntimeCall(): void
    {
        $calls = $this->compiled()->getDefinition(CallsService::class)->getMethodCalls();

        self::assertSame(['setPrefix', '__aopSetRuntime'], array_column($calls, 0));
    }

    #[Test]
    public function servicesOfTheSameClassGetDistinctProxiesAndBeanNames(): void
    {
        $container = $this->compiled();
        $service = (string) $container->getDefinition('fixture.service')->getClass();
        $other = (string) $container->getDefinition('fixture.other')->getClass();

        self::assertNotSame($service, $other);
        $registrations = [];
        foreach ($container->getDefinition('phauthentic_aop.runtime')->getMethodCalls() as $call) {
            if (\is_array($call) && 'registerBeanName' === ($call[0] ?? null)) {
                $registrations[] = $call[1] ?? null;
            }
        }
        self::assertContains([$service, 'fixture.service'], $registrations);
        self::assertContains([$other, 'fixture.other'], $registrations);
    }

    #[Test]
    public function finalClassWithInterfaceIsDecoratedByHand(): void
    {
        $container = $this->compiled();
        $outer = $container->getDefinition(FinalService::class);
        $inner = $container->getDefinition(FinalService::class.'.aop_target');

        self::assertSame(FinalService::class, $inner->getClass());
        self::assertFalse($inner->isPublic());
        self::assertSame([], $inner->getTags());

        $proxyClass = (string) $outer->getClass();
        self::assertTrue(is_subclass_of($proxyClass, ShoutInterface::class));
        self::assertFalse(is_subclass_of($proxyClass, FinalService::class));
        self::assertTrue($outer->isPublic());
        self::assertSame(['custom.tag' => [['a' => 1]]], $outer->getTags());
        self::assertEquals([new Reference(FinalService::class.'.aop_target')], $outer->getArguments());
        self::assertEquals([['__aopSetRuntime', [new Reference('phauthentic_aop.runtime')]]], $outer->getMethodCalls());
    }

    #[Test]
    public function factoryCreatedServiceFallsBackToTheInterfaceStrategy(): void
    {
        $container = $this->compiled();

        self::assertSame([FactoryCreatedService::class, 'create'], $container->getDefinition(FactoryCreatedService::class.'.aop_target')->getFactory());
        self::assertSame('interface', self::report($container)['advised'][FactoryCreatedService::class]['strategy']);
    }

    #[Test]
    public function taggedServiceWhosePublicMethodsAnInterfaceProxyWouldHideIsSkipped(): void
    {
        $container = $this->compiled();
        $skipped = self::report($container)['skipped'][OrderSubscriber::class] ?? null;

        self::assertSame(OrderSubscriber::class, $container->getDefinition(OrderSubscriber::class)->getClass());
        self::assertNotNull($skipped);
        self::assertSame(
            'an interface proxy would hide method onOrderPlaced(), which tag kernel.event_subscriber may reference',
            $skipped['reason'],
        );
    }

    #[Test]
    public function untaggedServiceWithNonInterfaceMethodsIsStillInterfaceProxied(): void
    {
        self::assertSame('interface', self::report($this->compiled())['advised']['fixture.untagged_subscriber']['strategy']);
    }

    #[Test]
    public function excludedServicesAndAspectsAreNeverProxied(): void
    {
        $container = $this->compiled();

        self::assertSame(ExcludedService::class, $container->getDefinition(ExcludedService::class)->getClass());
        self::assertSame(LogAspect::class, $container->getDefinition(LogAspect::class)->getClass());
        self::assertArrayNotHasKey(LogAspect::class, self::report($container)['advised']);
        self::assertArrayNotHasKey(LogAspect::class, self::report($container)['skipped']);
    }

    #[Test]
    public function reportListsAdvisedMethodsAndSkippedServices(): void
    {
        $report = self::report($this->compiled());

        self::assertSame([
            'class' => PlainService::class,
            'proxy' => $report['advised']['fixture.service']['proxy'],
            'strategy' => 'subclass',
            'methods' => ['greet' => [BeanAspect::class.'::before', LogAspect::class.'::around']],
        ], $report['advised']['fixture.service']);
        self::assertSame(['greet' => [LogAspect::class.'::around']], $report['advised']['fixture.other']['methods']);
        self::assertSame(['decorate' => [LogAspect::class.'::around']], $report['advised'][CallsService::class]['methods']);
        self::assertSame(FinalNoInterfaceService::class, $report['skipped'][FinalNoInterfaceService::class]['class']);
        self::assertStringContainsString('final', $report['skipped'][FinalNoInterfaceService::class]['reason']);
        self::assertArrayNotHasKey(ExcludedService::class, $report['advised'] + $report['skipped']);
    }

    #[Test]
    public function reportListsSelfInvocationsOfInterfaceProxiedServicesOnly(): void
    {
        $report = self::report($this->compiled());
        $file = (string) (new \ReflectionClass(SelfCallingService::class))->getFileName();

        self::assertSame(
            [['caller' => 'outer', 'method' => 'inner', 'file' => $file, 'line' => 11]],
            $report['advised'][SelfCallingService::class]['selfInvocations'] ?? null,
        );
        self::assertSame([], $report['advised'][FinalService::class]['selfInvocations'] ?? null);
        self::assertArrayNotHasKey('selfInvocations', $report['advised']['fixture.service']);
    }

    #[Test]
    public function configuredStrategyThatCannotApplyIsReportedAsSkipped(): void
    {
        $container = $this->compiled(['proxy_strategy' => 'interface']);
        $report = self::report($container);

        self::assertSame(PlainService::class, $container->getDefinition('fixture.service')->getClass());
        self::assertStringContainsString('implements no interface', $report['skipped']['fixture.service']['reason']);
        self::assertSame('interface', $report['advised'][FinalService::class]['strategy']);
    }

    #[Test]
    public function disabledBundleLeavesTheContainerAlone(): void
    {
        $container = $this->compiled(['enabled' => false]);

        self::assertSame(PlainService::class, $container->getDefinition('fixture.service')->getClass());
        self::assertFalse($container->hasDefinition('phauthentic_aop.runtime'));
    }
}
