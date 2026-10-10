<?php

declare(strict_types=1);

namespace App\Aspect;

use Phauthentic\AOP\Attribute\Aspect;
use Phauthentic\AOP\Attribute\Before;
use Phauthentic\AOP\Attribute\Order;

#[Aspect]
#[Order(2)]
final class ValidationAspect
{
    #[Before('execution(* App\Service\StringService::greet(..)) && args(name, ..)')]
    public function before(string $name): void
    {
        if ('' === $name) {
            throw new \InvalidArgumentException('Name must not be empty');
        }
    }
}
