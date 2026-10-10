<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Fixtures\Aspect;

use Phauthentic\AOP\Attribute\After;
use Phauthentic\AOP\Attribute\Aspect;
use Phauthentic\AOP\Attribute\Before;

#[Aspect]
final class DoubleAdviceAspect
{
    #[Before('a()')]
    #[After('a()')]
    public function both(): void
    {
    }
}
