<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Interceptor;

use Phauthentic\AOP\Aspect\AspectInstanceResolverInterface;
use Phauthentic\AOP\Binding\BindingPlan;
use Phauthentic\AOP\JoinPoint\MethodInvocation;

/**
 * Calls one advice method: resolves the aspect instance lazily (once) and binds the arguments.
 */
final class AdviceInvoker
{
    private ?object $aspect = null;

    /**
     * @param class-string $aspectClass
     */
    public function __construct(
        private readonly AspectInstanceResolverInterface $aspects,
        private readonly string $aspectClass,
        private readonly string $method,
        public readonly BindingPlan $plan,
    ) {
    }

    public function call(MethodInvocation $invocation, mixed $returnValue = null, ?\Throwable $thrown = null): mixed
    {
        $this->aspect ??= $this->aspects->get($this->aspectClass);
        $arguments = $this->plan->resolve($invocation, $invocation->getBoundArguments(), $returnValue, $thrown);

        return $this->aspect->{$this->method}(...$arguments->values);
    }
}
