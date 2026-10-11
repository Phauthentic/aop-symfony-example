<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Unit\Proxy;

use Phauthentic\AOP\Advisor\AdvisorFactory;
use Phauthentic\AOP\Advisor\AdvisorRegistry;
use Phauthentic\AOP\Advisor\MetadataNamedPointcutResolver;
use Phauthentic\AOP\Aspect\ArrayAspectInstanceResolver;
use Phauthentic\AOP\Aspect\AspectMetadataReader;
use Phauthentic\AOP\Interceptor\AdviceInvoker;
use Phauthentic\AOP\Proxy\ProxyFactory;
use Phauthentic\AOP\Proxy\ProxyStrategy;
use Phauthentic\AOP\Tests\Fixtures\Aspect\PerTargetCounterAspect;
use Phauthentic\AOP\Tests\Fixtures\Aspect\PerThisCounterAspect;
use Phauthentic\AOP\Tests\Fixtures\Targets\ReaderRepo;
use Phauthentic\AOP\Tests\Fixtures\Targets\RepoInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AdviceInvoker::class)]
final class ScopedAspectTest extends TestCase
{
    /** @var list<PerTargetCounterAspect|PerThisCounterAspect> */
    private array $created = [];

    /**
     * @param class-string<PerTargetCounterAspect|PerThisCounterAspect> $aspect
     */
    private function factory(string $aspect): ProxyFactory
    {
        $meta = (new AspectMetadataReader())->read($aspect);
        $advisors = (new AdvisorFactory())->fromMetadata($meta, new MetadataNamedPointcutResolver([$meta]));
        $resolver = new ArrayAspectInstanceResolver([$aspect => function () use ($aspect): object {
            $instance = new $aspect();
            $this->created[] = $instance;

            return $instance;
        }]);

        return new ProxyFactory(new AdvisorRegistry($advisors), $resolver, ProxyStrategy::INTERFACE);
    }

    private function repo(ProxyFactory $factory, ReaderRepo $target): RepoInterface
    {
        $proxy = $factory->createProxy($target);
        self::assertInstanceOf(RepoInterface::class, $proxy);

        return $proxy;
    }

    #[Test]
    public function pertargetAspectKeepsStatePerTargetObject(): void
    {
        $factory = $this->factory(PerTargetCounterAspect::class);
        $one = $this->repo($factory, new ReaderRepo());
        $two = $this->repo($factory, new ReaderRepo());

        $one->find();
        $one->find();
        $two->find();

        self::assertCount(2, $this->created);
        self::assertSame([2, 1], array_map(static fn (PerTargetCounterAspect|PerThisCounterAspect $a): int => $a->calls, $this->created));
    }

    #[Test]
    public function perthisAndPertargetDifferWhenTheProxyIsNotTheTarget(): void
    {
        $target = new ReaderRepo();
        $perTarget = $this->factory(PerTargetCounterAspect::class);
        // Two proxies around one target: pertarget shares one aspect, perthis creates one per proxy.
        $this->repo($perTarget, $target)->find();
        $this->repo($perTarget, $target)->find();
        self::assertCount(1, $this->created);

        $this->created = [];
        $perThis = $this->factory(PerThisCounterAspect::class);
        $this->repo($perThis, $target)->find();
        $this->repo($perThis, $target)->find();
        self::assertCount(2, $this->created);
    }
}
