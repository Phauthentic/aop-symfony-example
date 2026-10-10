<?php

declare(strict_types=1);

namespace Phauthentic\AOP\JoinPoint;

use Phauthentic\AOP\Interceptor\MethodInterceptorInterface;

/**
 * Each proceed() call hands the next interceptor its own copy positioned one step further along the chain,
 * so an interceptor may call proceed() several times.
 */
final class MethodInvocation implements ProceedingJoinPointInterface
{
    private int $index = 0;

    /**
     * @var array<string, mixed>
     */
    private array $boundArguments = [];

    /**
     * @param array<int|string, mixed>                  $args
     * @param list<MethodInterceptorInterface>          $interceptors
     * @param \Closure(array<int|string, mixed>): mixed $targetInvoker
     */
    public function __construct(
        private readonly object $proxy,
        private readonly object $target,
        private readonly MethodSignature $signature,
        private array $args,
        private readonly array $interceptors,
        private readonly \Closure $targetInvoker,
    ) {
    }

    public function getArgs(): array
    {
        return $this->args;
    }

    public function getThis(): object
    {
        return $this->proxy;
    }

    public function getTarget(): object
    {
        return $this->target;
    }

    public function getSignature(): MethodSignature
    {
        return $this->signature;
    }

    public function toShortString(): string
    {
        return $this->signature->toShortString();
    }

    /**
     * @param array<string, mixed> $bound values bound by the pointcut, for the next interceptor
     */
    public function withBoundArguments(array $bound): self
    {
        $copy = clone $this;
        $copy->boundArguments = $bound;

        return $copy;
    }

    /**
     * @return array<string, mixed>
     */
    public function getBoundArguments(): array
    {
        return $this->boundArguments;
    }

    public function proceed(?array $args = null): mixed
    {
        $args ??= $this->args;
        $interceptor = $this->interceptors[$this->index] ?? null;

        if (null === $interceptor) {
            return ($this->targetInvoker)($args);
        }

        $next = clone $this;
        $next->index = $this->index + 1;
        $next->args = $args;

        return $interceptor->invoke($next);
    }
}
