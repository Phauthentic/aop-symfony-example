<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Fixtures\Aspect;

use Phauthentic\AOP\Attribute\After;
use Phauthentic\AOP\Attribute\Aspect;

#[Aspect]
final class StaticAdviceAspect
{
    #[After('a()')]
    public static function stat(): void
    {
    }
}
