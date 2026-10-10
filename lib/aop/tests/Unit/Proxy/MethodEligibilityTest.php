<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Unit\Proxy;

use Phauthentic\AOP\Proxy\MethodEligibility;
use Phauthentic\AOP\Proxy\ProxyStrategy;
use Phauthentic\AOP\Tests\Fixtures\Targets\AbstractTarget;
use Phauthentic\AOP\Tests\Fixtures\Targets\FinalWithInterface;
use Phauthentic\AOP\Tests\Fixtures\Targets\GreetingInterface;
use Phauthentic\AOP\Tests\Fixtures\Targets\MixedMethods;
use Phauthentic\AOP\Tests\Fixtures\Targets\SignatureShapes;
use Phauthentic\AOP\Tests\Fixtures\Targets\Suit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MethodEligibility::class)]
final class MethodEligibilityTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function excluded(): iterable
    {
        yield 'private' => ['secret', 'private'];
        yield 'static' => ['create', 'static'];
        yield 'final' => ['locked', 'final'];
        yield 'constructor' => ['__construct', 'magic'];
        yield 'magic' => ['__toString', 'magic'];
        yield 'by-reference parameter' => ['byRef', 'by reference'];
        yield 'by-reference return' => ['refReturn', 'by reference'];
        yield 'object default' => ['objectDefault', 'default'];
    }

    #[Test]
    #[DataProvider('excluded')]
    public function excludesWithReason(string $method, string $reasonFragment): void
    {
        $reason = MethodEligibility::reasonForExclusion(new \ReflectionMethod(MixedMethods::class, $method), ProxyStrategy::SUBCLASS);

        self::assertNotNull($reason);
        self::assertStringContainsStringIgnoringCase($reasonFragment, $reason);
    }

    #[Test]
    public function excludesAbstractMethods(): void
    {
        $reason = MethodEligibility::reasonForExclusion(new \ReflectionMethod(AbstractTarget::class, 'todo'), ProxyStrategy::SUBCLASS);

        self::assertNotNull($reason);
        self::assertStringContainsStringIgnoringCase('abstract', $reason);
    }

    #[Test]
    public function publicAndProtectedMethodsAreEligibleForSubclassProxies(): void
    {
        self::assertNull(MethodEligibility::reasonForExclusion(new \ReflectionMethod(MixedMethods::class, 'plain'), ProxyStrategy::SUBCLASS));
        self::assertNull(MethodEligibility::reasonForExclusion(new \ReflectionMethod(MixedMethods::class, 'guarded'), ProxyStrategy::SUBCLASS));
        self::assertNull(MethodEligibility::reasonForExclusion(new \ReflectionMethod(SignatureShapes::class, 'defaults'), ProxyStrategy::SUBCLASS));
    }

    #[Test]
    public function interfaceMethodsOfFinalClassesAreEligibleForInterfaceProxies(): void
    {
        self::assertNull(MethodEligibility::reasonForExclusion(new \ReflectionMethod(FinalWithInterface::class, 'greet'), ProxyStrategy::INTERFACE));
    }

    #[Test]
    public function interfaceProxiesOnlyAdviseInterfaceMethods(): void
    {
        $reason = MethodEligibility::reasonForExclusion(new \ReflectionMethod(FinalWithInterface::class, 'notOnInterface'), ProxyStrategy::INTERFACE);
        self::assertNotNull($reason);
        self::assertStringContainsStringIgnoringCase('interface', $reason);

        $reason = MethodEligibility::reasonForExclusion(new \ReflectionMethod(MixedMethods::class, 'guarded'), ProxyStrategy::INTERFACE);
        self::assertNotNull($reason);
    }

    /**
     * @return iterable<string, array{class-string, string}>
     */
    public static function unproxyableClasses(): iterable
    {
        yield 'enum' => [Suit::class, 'enum'];
        yield 'abstract' => [AbstractTarget::class, 'abstract'];
        yield 'interface' => [GreetingInterface::class, 'interface'];
        yield 'internal' => [\ArrayObject::class, 'internal'];
    }

    /**
     * @param class-string $class
     */
    #[Test]
    #[DataProvider('unproxyableClasses')]
    public function rejectsUnproxyableClasses(string $class, string $reasonFragment): void
    {
        $reason = MethodEligibility::isClassProxyable(new \ReflectionClass($class));

        self::assertNotNull($reason);
        self::assertStringContainsStringIgnoringCase($reasonFragment, $reason);
    }

    #[Test]
    public function rejectsAnonymousAndTraitClasses(): void
    {
        self::assertNotNull(MethodEligibility::isClassProxyable(new \ReflectionClass(new class {})));
        self::assertNotNull(MethodEligibility::isClassProxyable(new \ReflectionClass(\Phauthentic\AOP\Pointcut\SubjectBinding::class)));
    }

    #[Test]
    public function acceptsRegularAndFinalClasses(): void
    {
        self::assertNull(MethodEligibility::isClassProxyable(new \ReflectionClass(SignatureShapes::class)));
        self::assertNull(MethodEligibility::isClassProxyable(new \ReflectionClass(FinalWithInterface::class)));
    }
}
