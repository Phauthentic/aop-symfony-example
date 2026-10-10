<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Fixtures\Aspect;

use Phauthentic\AOP\Attribute\After;
use Phauthentic\AOP\Attribute\AfterReturning;
use Phauthentic\AOP\Attribute\AfterThrowing;
use Phauthentic\AOP\Attribute\Around;
use Phauthentic\AOP\Attribute\Aspect;
use Phauthentic\AOP\Attribute\Before;
use Phauthentic\AOP\Attribute\Order;
use Phauthentic\AOP\JoinPoint\JoinPointInterface;
use Phauthentic\AOP\JoinPoint\ProceedingJoinPointInterface;
use Phauthentic\AOP\Tests\Fixtures\Log;

#[Aspect]
#[Order(1)]
final class RecordingAspectA
{
    #[Around('execution(* Phauthentic\AOP\Tests\Fixtures\Service\**::*(..))')]
    public function around(ProceedingJoinPointInterface $pjp): mixed
    {
        Log::add('A.around>');
        try {
            return $pjp->proceed();
        } finally {
            Log::add('A.around<');
        }
    }

    #[Before('execution(* Phauthentic\AOP\Tests\Fixtures\Service\**::*(..))')]
    public function before(JoinPointInterface $jp): void
    {
        Log::add('A.before');
    }

    #[After('execution(* Phauthentic\AOP\Tests\Fixtures\Service\**::*(..))')]
    public function after(JoinPointInterface $jp): void
    {
        Log::add('A.after');
    }

    #[AfterReturning('execution(* Phauthentic\AOP\Tests\Fixtures\Service\**::*(..))', returning: 'result')]
    public function afterReturning(mixed $result): void
    {
        Log::add('A.afterReturning');
    }

    #[AfterThrowing('execution(* Phauthentic\AOP\Tests\Fixtures\Service\**::*(..))', throwing: 'e')]
    public function afterThrowing(\Throwable $e): void
    {
        Log::add('A.afterThrowing');
    }
}
