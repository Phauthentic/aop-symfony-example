<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Fixtures\Aspect;

use Phauthentic\AOP\Attribute\Aspect;
use Phauthentic\AOP\Attribute\Before;

#[Aspect]
final class PrivateAdviceAspect
{
    /** @phpstan-ignore method.unused */
    #[Before('a()')]
    private function hidden(): void
    {
    }
}
