<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Fixtures\Aspect;

use Phauthentic\AOP\Attribute\Around;
use Phauthentic\AOP\Attribute\Aspect;
use Phauthentic\AOP\JoinPoint\ProceedingJoinPointInterface;

/**
 * Around advice on RefTarget whose behaviour each test picks: proceed as is, with replaced arguments, or not at all.
 */
#[Aspect]
final class RefAroundAspect
{
    /** @var array<int|string, mixed>|null */
    public ?array $replacement = null;

    public bool $proceed = true;

    #[Around('execution(* Phauthentic\AOP\Tests\Fixtures\Targets\RefTarget::*(..))')]
    public function around(ProceedingJoinPointInterface $pjp): mixed
    {
        if (!$this->proceed) {
            return null;
        }

        return $pjp->proceed($this->replacement);
    }
}
