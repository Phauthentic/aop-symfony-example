<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Interceptor;

use Phauthentic\AOP\Aspect\AspectInstanceResolverInterface;
use Phauthentic\AOP\Binding\BindingPlan;
use Phauthentic\AOP\JoinPoint\MethodInvocation;

final class AfterReturningAdviceInterceptor implements MethodInterceptorInterface
{
    private readonly AdviceInvoker $advice;

    /**
     * @param class-string $aspectClass
     */
    public function __construct(AspectInstanceResolverInterface $aspects, string $aspectClass, string $method, BindingPlan $plan)
    {
        $this->advice = new AdviceInvoker($aspects, $aspectClass, $method, $plan);
    }

    public function invoke(MethodInvocation $invocation): mixed
    {
        $result = $invocation->proceed();
        if ($this->advice->plan->acceptsReturnValue($result)) {
            $this->advice->call($invocation, $result);
        }

        return $result;
    }
}
