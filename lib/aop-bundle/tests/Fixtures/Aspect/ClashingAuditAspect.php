<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Symfony\Tests\Fixtures\Aspect;

use Phauthentic\AOP\Attribute\Aspect;
use Phauthentic\AOP\Attribute\DeclareParents;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Service\Auditable;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Service\AuditTrail;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Service\ClashingAuditedService;

#[Aspect]
#[DeclareParents(ClashingAuditedService::class, Auditable::class, AuditTrail::class)]
final class ClashingAuditAspect
{
}
