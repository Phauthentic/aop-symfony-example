<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Advisor;

final class AdvisorOrderComparator
{
    public function __invoke(Advisor $a, Advisor $b): int
    {
        return [$a->order, $a->advice->type->precedence(), $a->advice->declarationIndex, $a->advice->aspectClass]
            <=> [$b->order, $b->advice->type->precedence(), $b->advice->declarationIndex, $b->advice->aspectClass];
    }
}
