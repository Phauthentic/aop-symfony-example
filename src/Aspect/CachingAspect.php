<?php

declare(strict_types=1);

namespace App\Aspect;

use App\Demo\Trace;
use Phauthentic\AOP\Attribute\Around;
use Phauthentic\AOP\Attribute\Aspect;
use Phauthentic\AOP\Attribute\Order;
use Phauthentic\AOP\JoinPoint\ProceedingJoinPointInterface;

#[Aspect]
#[Order(10)]
final class CachingAspect
{
    /** @var array<string, mixed> */
    private array $cache = [];

    public function __construct(private readonly Trace $trace)
    {
    }

    #[Around('execution(* App\Service\StringService::upper(..))')]
    public function around(ProceedingJoinPointInterface $pjp): mixed
    {
        $label = sprintf('%s(%s)', $pjp->getSignature()->methodName, implode(',', array_filter($pjp->getArgs(), is_string(...))));
        $key = $pjp->getSignature()->toLongString().serialize($pjp->getArgs());
        if (\array_key_exists($key, $this->cache)) {
            $this->trace->add('cache hit '.$label);

            return $this->cache[$key];
        }
        $this->trace->add('cache miss '.$label);

        return $this->cache[$key] = $pjp->proceed();
    }
}
