<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Fixtures\Aspect;

use Phauthentic\AOP\Attribute\Aspect;
use Phauthentic\AOP\Attribute\Before;
use Phauthentic\AOP\Attribute\Order;

#[Aspect]
#[Order(1)]
final class OrderedAspect
{
    #[Before('a()')]
    public function first(): void
    {
    }

    #[Before('b()')]
    public function second(): void
    {
    }
}
