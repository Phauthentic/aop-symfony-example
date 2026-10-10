<?php

declare(strict_types=1);

namespace App\Aspect;

use App\Contract\Auditable;
use App\Contract\AuditTrail;
use Phauthentic\AOP\Attribute\AfterReturning;
use Phauthentic\AOP\Attribute\Aspect;
use Phauthentic\AOP\Attribute\DeclareParents;
use Phauthentic\AOP\Attribute\Order;
use Phauthentic\AOP\JoinPoint\JoinPointInterface;

/**
 * Every service becomes Auditable without implementing it, and records each call that returned to its caller.
 */
#[Aspect]
#[Order(0)]
#[DeclareParents('App\Service\*Service', Auditable::class, AuditTrail::class)]
final class AuditIntroductionAspect
{
    #[AfterReturning('App\Aspect\CommonPointcuts::serviceLayer() && this(auditable)')]
    public function record(JoinPointInterface $jp, Auditable $auditable): void
    {
        $auditable->recordAudit($jp->getSignature()->methodName);
    }
}
