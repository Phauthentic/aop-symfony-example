<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Fixtures\Aspect;

use Phauthentic\AOP\Aspect\AspectScope;
use Phauthentic\AOP\Attribute\Aspect;
use Phauthentic\AOP\Attribute\Before;

#[Aspect(scope: AspectScope::PER_TARGET)]
final class PerTargetCounterAspect
{
    public int $calls = 0;

    #[Before('execution(* Phauthentic\AOP\Tests\Fixtures\Targets\ReaderRepo::find(..))')]
    public function count(): void
    {
        ++$this->calls;
    }
}
