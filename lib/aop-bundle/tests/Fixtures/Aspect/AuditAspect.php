<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Symfony\Tests\Fixtures\Aspect;

use Phauthentic\AOP\Attribute\AfterReturning;
use Phauthentic\AOP\Attribute\Aspect;
use Phauthentic\AOP\Attribute\DeclareParents;
use Phauthentic\AOP\JoinPoint\JoinPointInterface;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Service\Auditable;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Service\AuditedService;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Service\AuditTrail;

#[Aspect]
#[DeclareParents(AuditedService::class, Auditable::class, AuditTrail::class)]
final class AuditAspect
{
    #[AfterReturning('execution(* Phauthentic\AOP\Symfony\Tests\Fixtures\Service\AuditedService::work(..)) && this(auditable)')]
    public function record(JoinPointInterface $jp, Auditable $auditable): void
    {
        $auditable->recordAudit($jp->getSignature()->methodName);
    }
}
