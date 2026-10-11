<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Fixtures\Aspect;

use Phauthentic\AOP\Attribute\Aspect;
use Phauthentic\AOP\Attribute\Before;
use Phauthentic\AOP\Attribute\DeclareParents;
use Phauthentic\AOP\JoinPoint\JoinPointInterface;
use Phauthentic\AOP\Tests\Fixtures\Introduction\BareTarget;
use Phauthentic\AOP\Tests\Fixtures\Introduction\Tracked;
use Phauthentic\AOP\Tests\Fixtures\Introduction\TrackedImpl;
use Phauthentic\AOP\Tests\Fixtures\Log;

/**
 * Introduces `Tracked` and advises its method through `this()` of the parent interface.
 */
#[Aspect]
#[DeclareParents(BareTarget::class, Tracked::class, TrackedImpl::class)]
final class TrackedIntroductionAspect
{
    #[Before('execution(* track(..)) && this(Phauthentic\AOP\Tests\Fixtures\Introduction\Trackable)')]
    public function before(JoinPointInterface $jp): void
    {
        Log::add('this(Trackable) '.$jp->getSignature()->methodName);
    }
}
