<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Unit\Proxy;

use Phauthentic\AOP\Proxy\AdvisedInterface;
use Phauthentic\AOP\Proxy\InterfaceProxyGenerator;
use Phauthentic\AOP\Proxy\ProxyClassWriter;
use Phauthentic\AOP\Proxy\ProxyStrategy;
use Phauthentic\AOP\Tests\Fixtures\Log;
use Phauthentic\AOP\Tests\Fixtures\Targets\FinalWithInterface;
use Phauthentic\AOP\Tests\Fixtures\Targets\GreetingInterface;
use Phauthentic\AOP\Tests\Fixtures\Targets\NamedInterface;
use Phauthentic\AOP\Tests\Fixtures\Targets\ShoutInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(InterfaceProxyGenerator::class)]
final class InterfaceProxyGeneratorTest extends TestCase
{
    protected function setUp(): void
    {
        Log::reset();
    }

    /**
     * @return array{AdvisedInterface, FinalWithInterface}
     */
    private function proxy(): array
    {
        $generated = (new InterfaceProxyGenerator())->generate(new \ReflectionClass(FinalWithInterface::class), ['greet', 'shout', 'fluent']);
        self::assertSame(ProxyStrategy::INTERFACE, $generated->strategy);
        $proxyClass = ProxyClassWriter::load($generated);
        $target = new FinalWithInterface();
        $proxy = new $proxyClass($target);
        if (!$proxy instanceof AdvisedInterface) {
            self::fail('Interface proxy must implement AdvisedInterface');
        }

        return [$proxy, $target];
    }

    #[Test]
    public function implementsAllInterfacesOfTheTarget(): void
    {
        [$proxy] = $this->proxy();

        self::assertInstanceOf(GreetingInterface::class, $proxy);
        self::assertInstanceOf(NamedInterface::class, $proxy);
        self::assertInstanceOf(ShoutInterface::class, $proxy);
        self::assertInstanceOf(\Countable::class, $proxy);
        $reflection = new \ReflectionClass($proxy);
        self::assertFalse($reflection->getParentClass(), 'an interface proxy extends nothing');
        self::assertFalse($reflection->isFinal());
    }

    #[Test]
    public function delegatesEveryInterfaceMethodToTheTarget(): void
    {
        [$proxy, $target] = $this->proxy();
        self::assertInstanceOf(FinalWithInterface::class, $proxy->__aopGetTarget());
        self::assertSame($target, $proxy->__aopGetTarget());

        \assert($proxy instanceof GreetingInterface && $proxy instanceof ShoutInterface && $proxy instanceof \Countable);
        self::assertSame('Hello Bob', $proxy->greet('Bob'));
        self::assertSame('Hi Bob', $proxy->greet('Bob', 'Hi'));
        self::assertSame('final', $proxy->name());
        self::assertSame('LOUD', $proxy->shout('loud'));
        self::assertCount(42, $proxy);
        self::assertSame(['greet', 'greet', 'name', 'shout'], Log::$entries);
    }

    #[Test]
    public function routesAdvisedMethodsThroughTheRuntime(): void
    {
        [$proxy] = $this->proxy();
        \assert($proxy instanceof GreetingInterface);
        $proxy->__aopSetRuntime(ProxyTestHelper::emptyRuntime());

        self::assertSame('Hello Bob', $proxy->greet('Bob'));
        self::assertSame($proxy, $proxy->fluent(), 'returning the target itself yields the proxy');
    }

    #[Test]
    public function fluentReturnsTheProxyWithoutRuntimeToo(): void
    {
        [$proxy] = $this->proxy();
        \assert($proxy instanceof GreetingInterface);

        self::assertSame($proxy, $proxy->fluent());
    }

    #[Test]
    public function signaturesMatchTheInterfaces(): void
    {
        [$proxy] = $this->proxy();

        foreach (['greet' => GreetingInterface::class, 'name' => NamedInterface::class, 'shout' => ShoutInterface::class] as $method => $interface) {
            $expected = ProxyTestHelper::signature(new \ReflectionMethod($interface, $method));
            self::assertSame($expected, ProxyTestHelper::signature(new \ReflectionMethod($proxy, $method)), $method);
        }
    }
}
