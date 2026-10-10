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
use Phauthentic\AOP\Tests\Fixtures\Targets\LegacyCountable;
use Phauthentic\AOP\Tests\Fixtures\Targets\NamedInterface;
use Phauthentic\AOP\Tests\Fixtures\Targets\RefTarget;
use Phauthentic\AOP\Tests\Fixtures\Targets\RefTargetInterface;
use Phauthentic\AOP\Tests\Fixtures\Targets\ShapeBase;
use Phauthentic\AOP\Tests\Fixtures\Targets\ShapeFactoryInterface;
use Phauthentic\AOP\Tests\Fixtures\Targets\ShoutInterface;
use Phauthentic\AOP\Tests\Fixtures\Targets\Square;
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
        // Salted per test: the runtime is static per proxy class, so a class shared between tests would leak it.
        $generated = (new InterfaceProxyGenerator())->generate(new \ReflectionClass(FinalWithInterface::class), ['greet', 'shout', 'fluent'], $this->name());
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

    #[Test]
    public function keepsReturnTypeWillChangeOfUntypedTargetMethods(): void
    {
        $notices = [];
        set_error_handler(static function (int $level, string $message) use (&$notices): bool {
            $notices[] = $message;

            return true;
        });
        try {
            $proxyClass = ProxyClassWriter::load((new InterfaceProxyGenerator())->generate(new \ReflectionClass(LegacyCountable::class), ['count']));
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $notices);
        $proxy = new $proxyClass(new LegacyCountable());
        self::assertInstanceOf(\Countable::class, $proxy);
        self::assertCount(3, $proxy);
    }

    #[Test]
    public function interfaceReturningParentClassOfTheTargetUsesTheInterfaceType(): void
    {
        $generated = (new InterfaceProxyGenerator())->generate(new \ReflectionClass(Square::class), ['scaled']);
        $proxyClass = ProxyClassWriter::load($generated);
        $proxy = new $proxyClass(new Square(2));
        \assert($proxy instanceof ShapeFactoryInterface);

        $returnType = (string) (new \ReflectionMethod($proxy, 'scaled'))->getReturnType();
        self::assertSame('\\'.ShapeBase::class, '\\'.ltrim($returnType, '\\'));
        $scaled = $proxy->scaled(3);
        self::assertInstanceOf(Square::class, $scaled, 'the proxy cannot stand in for a parent class, so the raw target result is returned');
        self::assertSame(6, $scaled->size);
    }

    #[Test]
    public function byReferenceParametersWriteThroughToTheCallerWithAndWithoutRuntime(): void
    {
        $proxyClass = ProxyClassWriter::load((new InterfaceProxyGenerator())->generate(new \ReflectionClass(RefTarget::class), ['appendAll', 'increment', 'swap'], $this->name()));

        foreach ([null, ProxyTestHelper::emptyRuntime()] as $runtime) {
            $proxy = new $proxyClass(new RefTarget());
            \assert($proxy instanceof RefTargetInterface && $proxy instanceof AdvisedInterface);
            if (null !== $runtime) {
                $proxy->__aopSetRuntime($runtime);
            }
            $n = 1;
            $proxy->increment($n);
            self::assertSame(2, $n);
            $list = ['a'];
            self::assertSame(3, $proxy->appendAll($list, 'b', 'c'));
            self::assertSame(['a', 'b', 'c'], $list);
            $a = 'a';
            $b = 'b';
            $proxy->swap($a, $b);
            self::assertSame(['b', 'a'], [$a, $b]);
            $x = 1;
            $y = 2;
            $proxy->scaleAll(10, $x, $y);
            self::assertSame([10, 20], [$x, $y], 'unadvised interface methods delegate by reference too');
        }
    }
}
