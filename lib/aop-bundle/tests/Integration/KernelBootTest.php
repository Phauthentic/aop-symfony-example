<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Symfony\Tests\Integration;

use Phauthentic\AOP\Proxy\AdvisedInterface;
use Phauthentic\AOP\Symfony\PhauthenticAopBundle;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Aspect\LogAspect;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Log;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Service\Auditable;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Service\AuditedService;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Service\CallsService;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Service\CountedService;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Service\FactoryCreatedService;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Service\LazyService;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Service\OrderSubscriber;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Service\PlainService;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Service\ShoutInterface;
use Phauthentic\AOP\Symfony\Tests\Fixtures\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\VarExporter\LazyObjectInterface;

#[CoversClass(PhauthenticAopBundle::class)]
final class KernelBootTest extends TestCase
{
    private static string $baseDir;
    private static TestKernel $kernel;

    public static function setUpBeforeClass(): void
    {
        self::$baseDir = TestKernel::newBaseDir();
        self::$kernel = new TestKernel(self::$baseDir);
        self::$kernel->boot();
    }

    public static function tearDownAfterClass(): void
    {
        self::$kernel->shutdown();
    }

    protected function setUp(): void
    {
        Log::reset();
    }

    private static function container(): ContainerInterface
    {
        return self::$kernel->getContainer();
    }

    private static function containerFile(): string
    {
        $files = glob(self::$kernel->getCacheDir().'/*Container.php') ?: [];
        self::assertCount(1, $files);

        return $files[0];
    }

    #[Test]
    public function subclassProxiedServiceIsAdvised(): void
    {
        $service = self::container()->get('fixture.service');

        self::assertInstanceOf(PlainService::class, $service);
        self::assertInstanceOf(AdvisedInterface::class, $service);
        self::assertSame('Hello x', $service->greet('x'));
        self::assertSame(['bean:greet', 'around>greet', 'greet', 'around<'], Log::$entries);

        $name = ' y ';
        $service->normalize($name);
        self::assertSame('y', $name, 'by-reference parameters write through the proxy');
    }

    #[Test]
    public function pertargetAspectGetsOneInstancePerAdvisedService(): void
    {
        $a = self::container()->get('fixture.counted_a');
        $b = self::container()->get('fixture.counted_b');
        self::assertInstanceOf(CountedService::class, $a);
        self::assertInstanceOf(CountedService::class, $b);

        $a->work();
        $a->work();
        $b->work();

        $counts = array_values(array_filter(Log::$entries, static fn (string $e): bool => str_starts_with($e, 'counter#')));
        self::assertCount(3, $counts);
        [$a1, $a2, $b1] = array_map(static fn (string $e): array => explode(':', $e), $counts);
        self::assertSame($a1[0], $a2[0]);
        self::assertNotSame($a1[0], $b1[0]);
        self::assertSame(['1', '2', '1'], [$a1[1], $a2[1], $b1[1]]);
    }

    #[Test]
    public function introducedInterfaceIsImplementedAndItsMethodsAreAdvised(): void
    {
        $service = self::container()->get('fixture.audited');

        self::assertInstanceOf(AuditedService::class, $service);
        self::assertInstanceOf(Auditable::class, $service);
        self::assertSame('worked', $service->work());
        self::assertSame(['work'], $service->auditTrail());
        self::assertSame(
            ['around>work', 'work', 'around<', 'around>recordAudit', 'around<', 'around>auditTrail', 'around<'],
            Log::$entries,
            'this(auditable) binds the proxy, and introduced methods are advised like any other',
        );
    }

    #[Test]
    public function beanPointcutOnlyMatchesItsServiceId(): void
    {
        $other = self::container()->get('fixture.other');
        self::assertInstanceOf(PlainService::class, $other);

        $other->greet('x');
        self::assertSame(['around>greet', 'greet', 'around<'], Log::$entries);
    }

    #[Test]
    public function interfaceProxiedServiceIsAdvised(): void
    {
        $service = self::container()->get(ShoutInterface::class);

        self::assertInstanceOf(AdvisedInterface::class, $service);
        self::assertInstanceOf(ShoutInterface::class, $service);
        self::assertSame('HI', $service->shout('hi'));
        self::assertSame(['around>shout', 'shout', 'around<'], Log::$entries);
    }

    #[Test]
    public function factoryCreatedServiceIsAdvised(): void
    {
        $service = self::container()->get(FactoryCreatedService::class);

        self::assertInstanceOf(ShoutInterface::class, $service);
        self::assertSame('HI!', $service->shout('hi'));
        self::assertSame(['around>shout', 'factory shout', 'around<'], Log::$entries);
    }

    #[Test]
    public function lazyServiceStaysLazyAndIsAdvised(): void
    {
        $service = self::container()->get(LazyService::class);

        // A lazy proxy of the target class wrapping the advised instance, not a ghost of the proxy class: see the
        // "Lazy services" section of docs/symfony.md for why.
        self::assertInstanceOf(LazyObjectInterface::class, $service);
        self::assertInstanceOf(LazyService::class, $service);
        self::assertNotInstanceOf(AdvisedInterface::class, $service);
        self::assertFalse($service->isLazyObjectInitialized());
        self::assertSame([], Log::$entries);

        self::assertSame('pong', $service->ping());
        self::assertSame(['lazy constructed', 'around>ping', 'ping', 'around<'], Log::$entries);
        self::assertInstanceOf(AdvisedInterface::class, $service->initializeLazyObject());
    }

    #[Test]
    public function eventSubscriberWithANonInterfaceListenerMethodIsNotHiddenBehindAnInterfaceProxy(): void
    {
        $dispatcher = self::container()->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);

        $dispatcher->dispatch(new \stdClass(), OrderSubscriber::EVENT);

        self::assertSame(['order placed'], Log::$entries);
    }

    #[Test]
    public function methodCallsStillRun(): void
    {
        $service = self::container()->get(CallsService::class);

        self::assertInstanceOf(CallsService::class, $service);
        self::assertSame('pre-x', $service->decorate('x'));
        self::assertSame(['around>decorate', 'decorate', 'around<'], Log::$entries);
    }

    #[Test]
    public function containerTracksAspectAndTargetClassesForFreshness(): void
    {
        $file = self::containerFile();
        self::assertTrue((new ConfigCache($file, true))->isFresh());

        $meta = unserialize((string) file_get_contents($file.'.meta'));
        self::assertIsArray($meta);
        $resources = array_map(static fn (mixed $r): string => $r instanceof \Stringable ? (string) $r : get_debug_type($r), $meta);
        self::assertContains('reflection.'.LogAspect::class, $resources);
        self::assertContains('reflection.'.PlainService::class, $resources);
    }

    #[Test]
    public function cachedContainerBootsInAFreshProcessWithoutRebuilding(): void
    {
        $file = self::containerFile();
        clearstatcache();
        $mtime = filemtime($file);

        $command = sprintf('%s %s %s 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg(dirname(__DIR__).'/Fixtures/boot.php'), escapeshellarg(self::$baseDir));
        exec($command, $output, $exitCode);
        $stdout = implode("\n", $output);

        self::assertSame(0, $exitCode, $stdout);
        self::assertSame([
            'early' => ['ping' => 'pong', 'greet' => 'Hello early'],
            'greet' => 'Hello x',
            'hot' => 'Hello y',
            'lazy' => true,
            'ping' => 'pong',
            'log' => ['bean:greet', 'around>greet', 'greet', 'around<', 'around>greet', 'greet', 'around<', 'lazy constructed', 'around>ping', 'ping', 'around<'],
        ], json_decode($stdout, true, flags: \JSON_THROW_ON_ERROR));
        clearstatcache();
        self::assertSame($mtime, filemtime($file), 'container was rebuilt instead of loaded from cache');
    }

    #[Test]
    public function secondKernelBootsFromTheSameCache(): void
    {
        $kernel = new TestKernel(self::$baseDir);
        $kernel->boot();
        Log::reset();
        try {
            $service = $kernel->getContainer()->get('fixture.service');
            self::assertInstanceOf(PlainService::class, $service);
            $service->greet('x');
            self::assertSame(['bean:greet', 'around>greet', 'greet', 'around<'], Log::$entries);
        } finally {
            $kernel->shutdown();
        }
    }

    #[Test]
    public function kernelWithAnotherCacheDirInTheSameProcessWorks(): void
    {
        $kernel = new TestKernel(TestKernel::newBaseDir());
        $kernel->boot();
        try {
            $service = $kernel->getContainer()->get(LazyService::class);
            self::assertInstanceOf(LazyService::class, $service);
            self::assertSame('pong', $service->ping());
        } finally {
            $kernel->shutdown();
        }
    }
}
