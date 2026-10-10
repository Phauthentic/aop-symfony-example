<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Unit\Proxy;

use Phauthentic\AOP\Exception\ProxyGenerationException;
use Phauthentic\AOP\Proxy\ProxyStrategy;
use Phauthentic\AOP\Proxy\ProxyStrategyResolver;
use Phauthentic\AOP\Tests\Fixtures\Targets\FinalWithInterface;
use Phauthentic\AOP\Tests\Fixtures\Targets\FinalWithoutInterface;
use Phauthentic\AOP\Tests\Fixtures\Targets\ForcedSubclassFinal;
use Phauthentic\AOP\Tests\Fixtures\Targets\InterfaceTypedTarget;
use Phauthentic\AOP\Tests\Fixtures\Targets\SignatureShapes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProxyStrategyResolver::class)]
final class ProxyStrategyResolverTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string, ?ProxyStrategy, ?ProxyStrategy}>
     */
    public static function matrix(): iterable
    {
        yield 'auto, non-final' => [SignatureShapes::class, null, ProxyStrategy::SUBCLASS];
        yield 'auto, final with interface' => [FinalWithInterface::class, null, ProxyStrategy::INTERFACE];
        yield 'auto, final without interface' => [FinalWithoutInterface::class, null, null];
        yield 'configured subclass, non-final' => [SignatureShapes::class, ProxyStrategy::SUBCLASS, ProxyStrategy::SUBCLASS];
        yield 'configured interface, final with interface' => [FinalWithInterface::class, ProxyStrategy::INTERFACE, ProxyStrategy::INTERFACE];
        yield 'attribute beats configured subclass' => [InterfaceTypedTarget::class, ProxyStrategy::SUBCLASS, ProxyStrategy::INTERFACE];
        yield 'attribute beats auto' => [InterfaceTypedTarget::class, null, ProxyStrategy::INTERFACE];
    }

    /**
     * @param class-string $class
     */
    #[Test]
    #[DataProvider('matrix')]
    public function resolves(string $class, ?ProxyStrategy $configured, ?ProxyStrategy $expected): void
    {
        self::assertSame($expected, (new ProxyStrategyResolver())->resolve(new \ReflectionClass($class), $configured));
    }

    #[Test]
    public function subclassOnFinalClassFails(): void
    {
        $this->expectException(ProxyGenerationException::class);
        $this->expectExceptionMessage(FinalWithInterface::class);

        (new ProxyStrategyResolver())->resolve(new \ReflectionClass(FinalWithInterface::class), ProxyStrategy::SUBCLASS);
    }

    #[Test]
    public function subclassAttributeOnFinalClassFails(): void
    {
        $this->expectException(ProxyGenerationException::class);

        (new ProxyStrategyResolver())->resolve(new \ReflectionClass(ForcedSubclassFinal::class), null);
    }

    #[Test]
    public function interfaceOnClassWithoutInterfaceFails(): void
    {
        $this->expectException(ProxyGenerationException::class);
        $this->expectExceptionMessage(FinalWithoutInterface::class);

        (new ProxyStrategyResolver())->resolve(new \ReflectionClass(FinalWithoutInterface::class), ProxyStrategy::INTERFACE);
    }

    #[Test]
    public function allowFinalResolvesFinalClassesLikeNonFinalOnes(): void
    {
        $resolver = new ProxyStrategyResolver();

        self::assertSame(ProxyStrategy::SUBCLASS, $resolver->resolve(new \ReflectionClass(FinalWithoutInterface::class), null, allowFinal: true));
        self::assertSame(ProxyStrategy::SUBCLASS, $resolver->resolve(new \ReflectionClass(FinalWithInterface::class), null, allowFinal: true));
        self::assertSame(ProxyStrategy::SUBCLASS, $resolver->resolve(new \ReflectionClass(FinalWithInterface::class), ProxyStrategy::SUBCLASS, allowFinal: true));
        self::assertSame(ProxyStrategy::INTERFACE, $resolver->resolve(new \ReflectionClass(FinalWithInterface::class), ProxyStrategy::INTERFACE, allowFinal: true));
    }
}
