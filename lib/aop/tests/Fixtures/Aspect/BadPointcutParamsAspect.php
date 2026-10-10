<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Fixtures\Aspect;

use Phauthentic\AOP\Attribute\Aspect;
use Phauthentic\AOP\Attribute\Pointcut;

#[Aspect]
final class BadPointcutParamsAspect
{
    #[Pointcut('execution(* *(..))')]
    public function bad(int $x): void
    {
    }
}
