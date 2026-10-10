<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Interceptor;

use Phauthentic\AOP\Aspect\AspectInstanceResolverInterface;
use Phauthentic\AOP\Aspect\AspectScope;
use Phauthentic\AOP\Binding\BindingPlan;
use Phauthentic\AOP\JoinPoint\MethodInvocation;

final class AroundAdviceInterceptor implements MethodInterceptorInterface
{
    private readonly AdviceInvoker $advice;

    /**
     * @param class-string $aspectClass
     */
    public function __construct(AspectInstanceResolverInterface $aspects, string $aspectClass, string $method, BindingPlan $plan, AspectScope $scope = AspectScope::SINGLETON)
    {
        $this->advice = new AdviceInvoker($aspects, $aspectClass, $method, $plan, $scope);
    }

    public function invoke(MethodInvocation $invocation): mixed
    {
        return $this->advice->call($invocation);
    }
}
