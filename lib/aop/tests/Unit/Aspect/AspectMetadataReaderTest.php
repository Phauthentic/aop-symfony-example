<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Unit\Aspect;

use Phauthentic\AOP\Advice\AdviceType;
use Phauthentic\AOP\Aspect\AspectMetadataReader;
use Phauthentic\AOP\Exception\AspectDefinitionException;
use Phauthentic\AOP\Tests\Fixtures\Aspect as F;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AspectMetadataReaderTest extends TestCase
{
    public function testReadsFullAspect(): void
    {
        $m = (new AspectMetadataReader())->read(F\AttributeFixtureAspect::class);

        self::assertSame(F\AttributeFixtureAspect::class, $m->aspectClass);
        self::assertSame(10, $m->order);
        self::assertSame(['servicePointcut' => 'execution(* App\Service\*Service::*(..))'], $m->namedPointcuts);
        self::assertSame(
            ['logBefore', 'logAfter', 'wrapAround', 'logReturn', 'logReturnUnbound', 'logThrow'],
            array_map(static fn ($a) => $a->methodName, $m->advices),
        );
        self::assertSame(
            [AdviceType::BEFORE, AdviceType::AFTER, AdviceType::AROUND, AdviceType::AFTER_RETURNING, AdviceType::AFTER_RETURNING, AdviceType::AFTER_THROWING],
            array_map(static fn ($a) => $a->type, $m->advices),
        );
        self::assertSame([0, 1, 2, 3, 4, 5], array_map(static fn ($a) => $a->declarationIndex, $m->advices));
        self::assertSame('result', $m->advices[3]->returning);
        self::assertNull($m->advices[4]->returning);
        self::assertSame('error', $m->advices[5]->throwing);
        self::assertSame('servicePointcut()', $m->advices[0]->expression);
        self::assertSame(F\AttributeFixtureAspect::class, $m->advices[0]->aspectClass);
    }

    public function testAcceptsReflectionClassAndDeclarationOrder(): void
    {
        $m = (new AspectMetadataReader())->read(new \ReflectionClass(F\OrderedAspect::class));

        self::assertSame(1, $m->order);
        self::assertSame(['first', 'second'], array_map(static fn ($a) => $a->methodName, $m->advices));
    }

    public function testDefaultOrderIsIntMax(): void
    {
        self::assertSame(PHP_INT_MAX, (new AspectMetadataReader())->read(F\UnorderedAspect::class)->order);
    }

    /** @return iterable<string, array{class-string, string}> */
    public static function invalid(): iterable
    {
        yield 'not an aspect' => [F\NotAnAspect::class, '#[Aspect]'];
        yield 'private advice' => [F\PrivateAdviceAspect::class, 'public'];
        yield 'static advice' => [F\StaticAdviceAspect::class, 'static'];
        yield 'two advice attributes' => [F\DoubleAdviceAspect::class, 'more than one'];
        yield 'pointcut non-void' => [F\BadPointcutReturnAspect::class, 'void'];
        yield 'pointcut params' => [F\BadPointcutParamsAspect::class, 'parameters'];
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('invalid')]
    public function testRejectsInvalid(string $class, string $message): void
    {
        $this->expectException(AspectDefinitionException::class);
        $this->expectExceptionMessage($message);
        (new AspectMetadataReader())->read($class);
    }
}
