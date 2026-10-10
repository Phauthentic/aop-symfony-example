<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Fixtures\Aspect;

use Phauthentic\AOP\Attribute\Aspect;
use Phauthentic\AOP\Attribute\Before;
use Phauthentic\AOP\JoinPoint\JoinPointInterface;

#[Aspect]
final class CrossReferenceAspect
{
    #[Before('Phauthentic\AOP\Tests\Fixtures\Aspect\NamedPointcutAspect::greets()')]
    public function viaOtherAspect(JoinPointInterface $jp): void
    {
    }

    #[Before('missing()')]
    public function viaUnknown(JoinPointInterface $jp): void
    {
    }
}
