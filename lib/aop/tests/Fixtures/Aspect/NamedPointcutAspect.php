<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Fixtures\Aspect;

use Phauthentic\AOP\Attribute\Aspect;
use Phauthentic\AOP\Attribute\Before;
use Phauthentic\AOP\Attribute\Order;
use Phauthentic\AOP\Attribute\Pointcut;
use Phauthentic\AOP\JoinPoint\JoinPointInterface;
use Phauthentic\AOP\Tests\Fixtures\Log;

#[Aspect]
#[Order(5)]
final class NamedPointcutAspect
{
    #[Pointcut('execution(* **::greet(..))')]
    public function greets(): void
    {
    }

    #[Before('greets()')]
    public function viaLocalReference(JoinPointInterface $jp): void
    {
        Log::add('N.local');
    }

    #[Before('execution(* **::greet(..)) && args(name)')]
    public function withName(string $name): void
    {
        Log::add('N.name='.$name);
    }
}
