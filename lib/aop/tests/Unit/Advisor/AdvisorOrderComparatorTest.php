<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Unit\Advisor;

use Phauthentic\AOP\Advice\AdviceDefinition;
use Phauthentic\AOP\Advice\AdviceType;
use Phauthentic\AOP\Advisor\Advisor;
use Phauthentic\AOP\Advisor\AdvisorOrderComparator;
use Phauthentic\AOP\Tests\Fixtures\Pointcut\StubPointcut;
use PHPUnit\Framework\TestCase;

final class AdvisorOrderComparatorTest extends TestCase
{
    public static function advisor(string $aspect, string $method, AdviceType $type, int $order, int $index): Advisor
    {
        return new Advisor(
            $aspect.'::'.$method,
            new AdviceDefinition($aspect, // @phpstan-ignore argument.type
                $method, $type, 'x()', null, null, $index),
            $order,
            new StubPointcut(),
        );
    }

    /**
     * @param list<Advisor> $advisors
     *
     * @return list<string>
     */
    private function sorted(array $advisors): array
    {
        usort($advisors, new AdvisorOrderComparator());

        return array_map(static fn (Advisor $a) => $a->id, $advisors);
    }

    public function testAspectOrderThenTypeThenDeclaration(): void
    {
        $a = self::advisor(...);
        $list = [
            $a('B', 'afterThrowing', AdviceType::AFTER_THROWING, 2, 0),
            $a('A', 'afterReturning', AdviceType::AFTER_RETURNING, 1, 0),
            $a('B', 'around', AdviceType::AROUND, 2, 1),
            $a('A', 'after', AdviceType::AFTER, 1, 1),
            $a('A', 'before2', AdviceType::BEFORE, 1, 3),
            $a('A', 'before1', AdviceType::BEFORE, 1, 2),
            $a('A', 'afterThrowing', AdviceType::AFTER_THROWING, 1, 4),
            $a('A', 'around', AdviceType::AROUND, 1, 5),
            $a('B', 'before', AdviceType::BEFORE, 2, 2),
        ];

        self::assertSame(
            ['A::around', 'A::before1', 'A::before2', 'A::after', 'A::afterReturning', 'A::afterThrowing', 'B::around', 'B::before', 'B::afterThrowing'],
            $this->sorted($list),
        );
    }

    public function testClassNameIsFinalTiebreak(): void
    {
        $a = self::advisor(...);

        self::assertSame(
            ['A::m', 'B::m'],
            $this->sorted([$a('B', 'm', AdviceType::BEFORE, 5, 0), $a('A', 'm', AdviceType::BEFORE, 5, 0)]),
        );
    }

    public function testEqualAdvisorsCompareZero(): void
    {
        $a = self::advisor('A', 'm', AdviceType::BEFORE, 5, 0);

        self::assertSame(0, (new AdvisorOrderComparator())($a, $a));
    }
}
