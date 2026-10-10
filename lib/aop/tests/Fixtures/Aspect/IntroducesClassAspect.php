<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Fixtures\Aspect;

use Phauthentic\AOP\Attribute\Aspect;
use Phauthentic\AOP\Attribute\DeclareParents;
use Phauthentic\AOP\Tests\Fixtures\Introduction\AuditTrail;

#[Aspect]
#[DeclareParents('**', AuditTrail::class, AuditTrail::class)]
final class IntroducesClassAspect
{
}
