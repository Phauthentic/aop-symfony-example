<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Proxy;

use Phauthentic\AOP\Runtime\AopRuntime;

/**
 * Implemented by every generated proxy class.
 */
interface AdvisedInterface
{
    public function __aopSetRuntime(AopRuntime $runtime): void;

    /**
     * The advised instance: the proxy itself for subclass proxies, the wrapped instance for interface proxies.
     */
    public function __aopGetTarget(): object;
}
