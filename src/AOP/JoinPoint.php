<?php

declare(strict_types=1);

namespace App\AOP;

/**
 * Join point: a point during the execution of a program, such as the execution of a method or the handling of an
 * exception. In Spring AOP, a join point always represents a method execution.
 * 
 * This is equivalent to Spring's ProceedingJoinPoint
 */
class JoinPoint 
{
    private object $target;
    private string $method;
    private array $arguments;
    private string $targetClass;
    private ?object $proxy;

    public function __construct(
        object $target, 
        string $method, 
        array $arguments,
        ?object $proxy = null
    ) {
        $this->target = $target;
        $this->method = $method;
        $this->arguments = $arguments;
        $this->targetClass = get_class($target);
        $this->proxy = $proxy;
    }

    /**
     * Proceed to the next interceptor in the chain, or to the target method if no more interceptors
     * This is the core of Around advice functionality
     */
    public function proceed()
    {
        return call_user_func_array([$this->target, $this->method], $this->arguments);
    }

    /**
     * Proceed with modified arguments
     */
    public function proceedWithArguments(array $arguments)
    {
        return call_user_func_array([$this->target, $this->method], $arguments);
    }

    public function getTarget(): object 
    {
        return $this->target;
    }

    public function getMethod(): string 
    {
        return $this->method;
    }

    public function getArguments(): array 
    {
        return $this->arguments;
    }

    public function getTargetClass(): string 
    {
        return $this->targetClass;
    }

    public function getProxy(): ?object 
    {
        return $this->proxy;
    }

    /**
     * Get the signature of the method being called
     */
    public function getSignature(): string 
    {
        return $this->targetClass . '::' . $this->method;
    }

    /**
     * Get the kind of join point (always 'method-execution' in Spring AOP)
     */
    public function getKind(): string 
    {
        return 'method-execution';
    }
}
