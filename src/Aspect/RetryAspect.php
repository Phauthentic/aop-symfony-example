<?php

declare(strict_types=1);

namespace App\Aspect;

use App\Attribute\Retry;
use App\Demo\Trace;
use Phauthentic\AOP\Attribute\Around;
use Phauthentic\AOP\Attribute\Aspect;
use Phauthentic\AOP\Attribute\Order;
use Phauthentic\AOP\JoinPoint\ProceedingJoinPointInterface;

#[Aspect]
#[Order(3)]
final class RetryAspect
{
    public function __construct(private readonly Trace $trace)
    {
    }

    #[Around('@annotation(retry)')]
    public function around(ProceedingJoinPointInterface $pjp, Retry $retry): mixed
    {
        for ($attempt = 1;; ++$attempt) {
            $this->trace->add(sprintf('retry attempt %d/%d', $attempt, $retry->attempts));
            try {
                return $pjp->proceed();
            } catch (\Throwable $e) {
                $this->trace->add(sprintf('retry attempt %d failed: %s', $attempt, $e->getMessage()));
                if ($attempt >= $retry->attempts) {
                    throw $e;
                }
            }
        }
    }
}
