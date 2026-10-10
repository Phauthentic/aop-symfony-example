<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Unit\Aspect;

use Phauthentic\AOP\Aspect\ArrayAspectInstanceResolver;
use Phauthentic\AOP\Exception\AspectDefinitionException;
use Phauthentic\AOP\Tests\Fixtures\Aspect\AttributeFixtureAspect;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArrayAspectInstanceResolver::class)]
final class ArrayAspectInstanceResolverTest extends TestCase
{
    #[Test]
    public function returnsRegisteredInstance(): void
    {
        $aspect = new AttributeFixtureAspect();
        $resolver = new ArrayAspectInstanceResolver([AttributeFixtureAspect::class => $aspect]);

        self::assertSame($aspect, $resolver->get(AttributeFixtureAspect::class));
    }

    #[Test]
    public function closureIsNotInvokedBeforeFirstGet(): void
    {
        $calls = 0;
        $resolver = new ArrayAspectInstanceResolver([
            AttributeFixtureAspect::class => function () use (&$calls): AttributeFixtureAspect {
                ++$calls;

                return new AttributeFixtureAspect();
            },
        ]);

        self::assertSame(0, $calls);
        $resolver->get(AttributeFixtureAspect::class);
        self::assertSame(1, $calls);
    }

    #[Test]
    public function closureIsInvokedOnceAndInstanceIsCached(): void
    {
        $calls = 0;
        $resolver = new ArrayAspectInstanceResolver([
            AttributeFixtureAspect::class => function () use (&$calls): AttributeFixtureAspect {
                ++$calls;

                return new AttributeFixtureAspect();
            },
        ]);

        $first = $resolver->get(AttributeFixtureAspect::class);
        $second = $resolver->get(AttributeFixtureAspect::class);

        self::assertSame(1, $calls);
        self::assertSame($first, $second);
    }

    #[Test]
    public function unknownAspectThrows(): void
    {
        $resolver = new ArrayAspectInstanceResolver([]);

        $this->expectException(AspectDefinitionException::class);
        $resolver->get(AttributeFixtureAspect::class);
    }
}
