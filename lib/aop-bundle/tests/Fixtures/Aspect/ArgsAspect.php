<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Symfony\Tests\Fixtures\Aspect;

use Phauthentic\AOP\Attribute\Aspect;
use Phauthentic\AOP\Attribute\Before;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Log;

/**
 * Statically matches ArgsService::scale() by its int parameter; each call is checked at runtime.
 */
#[Aspect]
final class ArgsAspect
{
    #[Before('execution(public * Phauthentic\AOP\Symfony\Tests\Fixtures\Service\ArgsService::*(..)) && args(int)')]
    public function check(): void
    {
        Log::add('args');
    }
}
