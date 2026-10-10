<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Fixtures\Aspect;

use Phauthentic\AOP\Attribute\Aspect;
use Phauthentic\AOP\Attribute\DeclareParents;
use Phauthentic\AOP\Tests\Fixtures\Introduction\Auditable;
use Phauthentic\AOP\Tests\Fixtures\Introduction\NeedsArgumentsAuditTrail;

#[Aspect]
#[DeclareParents('**', Auditable::class, NeedsArgumentsAuditTrail::class)]
final class IntroducesUninstantiableAspect
{
}
