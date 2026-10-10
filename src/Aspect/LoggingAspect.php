<?php

declare(strict_types=1);

namespace App\Aspect;

use App\Demo\Trace;
use Phauthentic\AOP\Attribute\After;
use Phauthentic\AOP\Attribute\Aspect;
use Phauthentic\AOP\Attribute\Before;
use Phauthentic\AOP\Attribute\Order;
use Phauthentic\AOP\JoinPoint\JoinPointInterface;

#[Aspect]
#[Order(1)]
final class LoggingAspect
{
    public function __construct(private readonly Trace $trace)
    {
    }

    #[Before('App\Aspect\CommonPointcuts::serviceLayer()')]
    public function before(JoinPointInterface $jp): void
    {
        $this->trace->add('log before '.$jp->toShortString());
    }

    #[After('App\Aspect\CommonPointcuts::serviceLayer()')]
    public function after(JoinPointInterface $jp): void
    {
        $this->trace->add('log after '.$jp->toShortString());
    }
}
