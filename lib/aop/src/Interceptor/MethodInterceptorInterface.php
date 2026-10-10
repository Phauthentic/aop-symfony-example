<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Interceptor;

use Phauthentic\AOP\JoinPoint\MethodInvocation;

interface MethodInterceptorInterface
{
    public function invoke(MethodInvocation $invocation): mixed;
}
