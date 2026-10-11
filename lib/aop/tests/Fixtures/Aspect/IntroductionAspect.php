<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Fixtures\Aspect;

use Phauthentic\AOP\Attribute\AfterReturning;
use Phauthentic\AOP\Attribute\Aspect;
use Phauthentic\AOP\Attribute\Before;
use Phauthentic\AOP\Attribute\DeclareParents;
use Phauthentic\AOP\Attribute\Order;
use Phauthentic\AOP\JoinPoint\JoinPointInterface;
use Phauthentic\AOP\Tests\Fixtures\Introduction\Auditable;
use Phauthentic\AOP\Tests\Fixtures\Introduction\AuditTrail;
use Phauthentic\AOP\Tests\Fixtures\Log;

/**
 * Spring's usage tracking example: every `*WorkTarget` becomes Auditable, and each `work()` call records itself
 * on the introduced implementation through `this()`.
 */
#[Aspect]
#[Order(3)]
#[DeclareParents('Phauthentic\AOP\Tests\Fixtures\Introduction\*WorkTarget', Auditable::class, AuditTrail::class)]
final class IntroductionAspect
{
    #[AfterReturning('execution(* Phauthentic\AOP\Tests\Fixtures\Introduction\*WorkTarget::work(..)) && this(auditable)')]
    public function record(JoinPointInterface $jp, Auditable $auditable): void
    {
        $args = $jp->getArgs();
        $auditable->recordAudit($jp->getSignature()->methodName.'('.(\is_string($args[0] ?? null) ? $args[0] : '').')');
    }

    #[Before('execution(* Phauthentic\AOP\Tests\Fixtures\Introduction\Auditable::*(..))')]
    public function introduced(JoinPointInterface $jp): void
    {
        Log::add('introduced '.$jp->getSignature()->methodName);
    }

    #[Before('target(Phauthentic\AOP\Tests\Fixtures\Introduction\Auditable) || within(Phauthentic\AOP\Tests\Fixtures\Introduction\Auditable+)')]
    public function targetOrWithin(JoinPointInterface $jp): void
    {
        Log::add('target or within '.$jp->getSignature()->methodName);
    }
}
