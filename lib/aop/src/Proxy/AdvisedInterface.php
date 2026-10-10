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
}
