<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Unit\Runtime;

use Phauthentic\AOP\Proxy\ProxyFactory;
use Phauthentic\AOP\Runtime\AopContext;
use Phauthentic\AOP\Tests\Fixtures\Aspect\TargetsAspect;
use Phauthentic\AOP\Tests\Fixtures\Targets\ContextProbe;
use Phauthentic\AOP\Tests\Fixtures\Targets\ContextProbeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AopContext::class)]
final class AopContextTest extends TestCase
{
    protected function setUp(): void
    {
        ContextProbe::$seen = [];
    }

    private function proxy(ContextProbe $target): ContextProbeInterface
    {
        $proxy = ProxyFactory::fromAspects([new TargetsAspect()])->createProxy($target);
        self::assertInstanceOf(ContextProbeInterface::class, $proxy);

        return $proxy;
    }

    #[Test]
    public function isNullOutsideAnAdvisedCall(): void
    {
        self::assertNull(AopContext::currentProxy());
    }

    #[Test]
    public function isTheProxyInsideAnAdvisedCallAndNullAfterwards(): void
    {
        $proxy = $this->proxy(new ContextProbe());

        $proxy->probe();

        self::assertSame([$proxy, $proxy], ContextProbe::$seen);
        self::assertNull(AopContext::currentProxy());
    }

    #[Test]
    public function nestedInvocationThroughAnotherProxyRestoresTheOuterProxy(): void
    {
        $inner = $this->proxy(new ContextProbe());
        $outer = $this->proxy(new ContextProbe($inner));

        $outer->probe();

        self::assertSame([$outer, $inner, $inner, $outer], ContextProbe::$seen);
        self::assertNull(AopContext::currentProxy());
    }

    #[Test]
    public function isRestoredAfterAnException(): void
    {
        $inner = $this->proxy(new ContextProbe(null, true));
        $outer = $this->proxy(new ContextProbe(new class($inner) implements ContextProbeInterface {
            public function __construct(private readonly ContextProbeInterface $inner)
            {
            }

            public function probe(): void
            {
                try {
                    $this->inner->probe();
                } catch (\LogicException) {
                    ContextProbe::$seen[] = AopContext::currentProxy();
                }
            }
        }));

        $outer->probe();

        self::assertSame([$outer, $inner, $inner, $outer, $outer], ContextProbe::$seen);
        self::assertNull(AopContext::currentProxy());
    }
}
