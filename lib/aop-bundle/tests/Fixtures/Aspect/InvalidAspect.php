<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Symfony\Tests\Fixtures\Aspect;

use Phauthentic\AOP\Attribute\Aspect;
use Phauthentic\AOP\Attribute\Before;

/**
 * Only registered by the test asserting that an invalid pointcut fails the container build.
 */
#[Aspect]
final class InvalidAspect
{
    #[Before('execution(* broken(')]
    public function broken(): void
    {
    }
}
