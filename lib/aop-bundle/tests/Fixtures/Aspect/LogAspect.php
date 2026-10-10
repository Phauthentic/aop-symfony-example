<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Symfony\Tests\Fixtures\Aspect;

use Phauthentic\AOP\Attribute\Around;
use Phauthentic\AOP\Attribute\Aspect;
use Phauthentic\AOP\JoinPoint\ProceedingJoinPointInterface;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Log;

#[Aspect]
class LogAspect
{
    #[Around('execution(public * Phauthentic\AOP\Symfony\Tests\Fixtures\**::*(..)) && !execution(* **::setPrefix(..))')]
    public function around(ProceedingJoinPointInterface $pjp): mixed
    {
        Log::add('around>'.$pjp->getSignature()->methodName);
        try {
            return $pjp->proceed();
        } finally {
            Log::add('around<');
        }
    }
}
