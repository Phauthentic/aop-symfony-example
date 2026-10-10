<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Unit\Proxy;

use Phauthentic\AOP\Pointcut\AnnotationPointcut;
use Phauthentic\AOP\Pointcut\AtArgsPointcut;
use Phauthentic\AOP\Pointcut\AtTargetPointcut;
use Phauthentic\AOP\Pointcut\AtWithinPointcut;
use Phauthentic\AOP\Pointcut\ThisPointcut;
use Phauthentic\AOP\Proxy\ProxyFactory;
use Phauthentic\AOP\Tests\Fixtures\Aspect\BindingProbeAspect;
use Phauthentic\AOP\Tests\Fixtures\Binding\TaggedService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Bindings as seen by advice running on real subclass proxies.
 */
#[CoversClass(ThisPointcut::class)]
#[CoversClass(AnnotationPointcut::class)]
#[CoversClass(AtTargetPointcut::class)]
#[CoversClass(AtWithinPointcut::class)]
#[CoversClass(AtArgsPointcut::class)]
final class PointcutBindingThroughProxyTest extends TestCase
{
    private BindingProbeAspect $aspect;
    private TaggedService $proxy;

    protected function setUp(): void
    {
        $this->aspect = new BindingProbeAspect();
        $this->proxy = ProxyFactory::fromAspects([$this->aspect])->newInstance(TaggedService::class);
    }

    /**
     * @return list<mixed>
     */
    private function bound(string $designator): array
    {
        return array_values(array_map(
            static fn (array $call): mixed => $call[1],
            array_filter($this->aspect->calls, static fn (array $call): bool => $designator === $call[0]),
        ));
    }

    #[Test]
    public function thisBindsToANullableAdviceParameter(): void
    {
        $this->proxy->own();

        self::assertSame([$this->proxy], $this->bound('this'));
    }

    #[Test]
    public function annotationBindsToANullableAdviceParameter(): void
    {
        $this->proxy->own();

        self::assertSame(['method'], $this->bound('@annotation'));
    }

    #[Test]
    public function atArgsBindsToANullableAdviceParameter(): void
    {
        $this->proxy->accept(new TaggedService());
        $this->proxy->accept(new \stdClass());

        self::assertSame(['class'], $this->bound('@args'));
    }

    #[Test]
    public function atTargetBindsTheAttributeOfTheTargetClassNotOfTheProxyClass(): void
    {
        $this->proxy->own();
        $this->proxy->inherited();

        self::assertSame(['class', 'class'], $this->bound('@target'));
    }

    #[Test]
    public function atWithinBindsTheTargetClassAttributeForInheritedMethods(): void
    {
        $this->proxy->inherited();

        self::assertSame(['class'], $this->bound('@within'));
    }
}
