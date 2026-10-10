<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Unit\Advisor;

use Phauthentic\AOP\Advice\AdviceType;
use Phauthentic\AOP\Advisor\AdvisorRegistry;
use PHPUnit\Framework\TestCase;

final class AdvisorRegistryTest extends TestCase
{
    public function testSortsFiltersAndCounts(): void
    {
        $a = AdvisorOrderComparatorTest::advisor(...);
        $r = new AdvisorRegistry([
            $a('B', 'm', AdviceType::BEFORE, 2, 0),
            $a('A', 'z', AdviceType::AFTER, 1, 1),
            $a('A', 'y', AdviceType::AROUND, 1, 0),
        ]);

        self::assertSame(3, $r->count());
        self::assertSame(['A::y', 'A::z', 'B::m'], array_map(static fn ($x) => $x->id, $r->all()));
        self::assertSame(['A::y', 'A::z'], array_map(static fn ($x) => $x->id, $r->forAspect('A')));
        self::assertSame([], $r->forAspect('Nope'));
    }
}
