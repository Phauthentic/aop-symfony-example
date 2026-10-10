<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Fixtures\Aspect;

use Phauthentic\AOP\Attribute\AfterReturning;
use Phauthentic\AOP\Attribute\AfterThrowing;
use Phauthentic\AOP\Attribute\Around;
use Phauthentic\AOP\Attribute\Aspect;
use Phauthentic\AOP\Attribute\Before;
use Phauthentic\AOP\JoinPoint\JoinPointInterface;
use Phauthentic\AOP\JoinPoint\ProceedingJoinPointInterface;
use Phauthentic\AOP\Tests\Fixtures\Hierarchy\Audit;

#[Aspect]
final class BindingAspect
{
    #[Before('execution(* *::*(..)) && args(id, ..)')]
    public function withArgs(JoinPointInterface $jp, int $id): void
    {
    }

    #[AfterReturning('execution(* *::*(..))', returning: 'result')]
    public function afterReturning(string $result, JoinPointInterface $jp): void
    {
    }

    #[AfterReturning('execution(* *::*(..))', returning: 'anything')]
    public function afterReturningUntyped(mixed $anything): void
    {
    }

    #[AfterReturning('execution(* *::*(..))', returning: 'nullable')]
    public function afterReturningNullable(?\Countable $nullable): void
    {
    }

    #[AfterReturning('execution(* *::*(..))', returning: 'missing')]
    public function afterReturningMissingParameter(): void
    {
    }

    #[AfterThrowing('execution(* *::*(..))', throwing: 'e')]
    public function afterThrowing(\RuntimeException $e): void
    {
    }

    // @phpstan-ignore missingType.parameter (untyped on purpose)
    #[AfterThrowing('execution(* *::*(..))', throwing: 'e')]
    public function afterThrowingUntyped($e): void
    {
    }

    #[Around('execution(* *::*(..))')]
    public function around(ProceedingJoinPointInterface $pjp): mixed
    {
        return $pjp->proceed();
    }

    #[Around('execution(* *::*(..))')]
    public function aroundWithoutProceedingJoinPoint(JoinPointInterface $jp): mixed
    {
        return null;
    }

    #[Before('execution(* *::*(..))')]
    public function beforeWithProceedingJoinPoint(ProceedingJoinPointInterface $pjp): void
    {
    }

    #[Before('@annotation(audit)')]
    public function audited(Audit $audit): void
    {
    }

    #[Before('execution(* *::*(..))')]
    public function unresolvable(int $unknown): void
    {
    }
}
