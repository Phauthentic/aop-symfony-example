<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Fixtures\Aspect;

use Phauthentic\AOP\Attribute\Around;
use Phauthentic\AOP\Attribute\Aspect;
use Phauthentic\AOP\JoinPoint\ProceedingJoinPointInterface;

#[Aspect]
final class WrongReturnAspect
{
    public mixed $value = 42;

    #[Around('execution(* Phauthentic\AOP\Tests\Fixtures\Targets\CountingConstructor::work(..))')]
    public function replace(ProceedingJoinPointInterface $pjp): mixed
    {
        return $this->value;
    }
}
