<?php

declare(strict_types=1);

namespace App\AOP;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Join point: a point during the execution of a program, such as the execution of a method or the handling of an
 * exception. In Spring AOP, a join point always represents a method execution.
 * 
 * This implements Spring's ProceedingJoinPoint with proper interceptor chaining
 */
class JoinPoint 
{
    private object $target;
    private string $method;
    private array $arguments;
    private string $targetClass;
    private ?object $proxy;
    private bool $argumentsModified = false;
    
    // For interceptor chaining
    private array $interceptors = [];
    private int $currentInterceptorIndex = 0;
    private bool $methodExecuted = false;
    private $methodResult = null;
    
    // Container for getting aspects
    private ?ContainerInterface $container;
    
    // Early return control
    private bool $shouldReturnEarly = false;
    private $earlyReturnValue = null;
    private bool $shouldSkipMethod = false;

    public function __construct(
        object $target, 
        string $method, 
        array $arguments,
        array $interceptors = [],
        ?object $proxy = null,
        ?ContainerInterface $container = null
    ) {
        $this->target = $target;
        $this->method = $method;
        $this->arguments = $arguments;
        $this->targetClass = get_class($target);
        $this->proxy = $proxy;
        $this->interceptors = $interceptors;
        $this->container = $container;
    }

    /**
     * Proceed to the next interceptor in the chain, or to the target method if no more interceptors
     * This is the core of Spring AOP Around advice functionality
     */
    public function proceed()
    {
        // If we have more interceptors, call the next one
        if ($this->currentInterceptorIndex < count($this->interceptors)) {
            $interceptor = $this->interceptors[$this->currentInterceptorIndex];
            $this->currentInterceptorIndex++;
            
            // Call the next interceptor with this JoinPoint
            return $interceptor($this);
        }
        
        // No more interceptors, execute the actual target method
        if (!$this->methodExecuted) {
            $this->methodResult = call_user_func_array([$this->target, $this->method], $this->arguments);
            $this->methodExecuted = true;
        }
        
        return $this->methodResult;
    }

    /**
     * Proceed with modified arguments
     */
    public function proceedWithArguments(array $arguments)
    {
        // Update the stored arguments
        $this->arguments = $arguments;
        
        // Reset interceptor chain to start
        $this->currentInterceptorIndex = 0;
        $this->methodExecuted = false;
        
        return $this->proceed();
    }

    /**
     * Get an aspect from the container
     */
    public function getAspect(string $aspectClass): object
    {
        if ($this->container && $this->container->has($aspectClass)) {
            return $this->container->get($aspectClass);
        }
        
        // Fallback to creating new instance if not in container
        return new $aspectClass();
    }

    /**
     * Modify arguments (for Before advice to use)
     */
    public function setArguments(array $arguments): void
    {
        $this->arguments = $arguments;
        $this->argumentsModified = true;
    }

    /**
     * Get a specific argument by index
     */
    public function getArgument(int $index)
    {
        return $this->arguments[$index] ?? null;
    }

    /**
     * Set a specific argument by index
     */
    public function setArgument(int $index, $value): void
    {
        $this->arguments[$index] = $value;
        $this->argumentsModified = true;
    }

    /**
     * Check if arguments have been modified
     */
    public function areArgumentsModified(): bool
    {
        return $this->argumentsModified;
    }

    /**
     * Get the original arguments (before modification)
     */
    public function getOriginalArguments(): array
    {
        return $this->arguments;
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
     * Set interceptors for Around advice
     */
    public function setInterceptors(array $interceptors): void
    {
        $this->interceptors = $interceptors;
        $this->currentInterceptorIndex = 0;
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
    
    /**
     * Set an early return value - this will cause the method to return immediately
     * without executing the target method or further advice
     */
    public function setEarlyReturn($value): void
    {
        $this->shouldReturnEarly = true;
        $this->earlyReturnValue = $value;
    }
    
    /**
     * Check if an early return has been requested
     */
    public function shouldReturnEarly(): bool
    {
        return $this->shouldReturnEarly;
    }
    
    /**
     * Get the early return value
     */
    public function getEarlyReturnValue()
    {
        return $this->earlyReturnValue;
    }
    
    /**
     * Skip the target method execution (useful for caching, validation failures, etc.)
     * The method will not be called, but AfterReturning advice will still execute
     */
    public function skipMethodExecution(): void
    {
        $this->shouldSkipMethod = true;
    }
    
    /**
     * Check if method execution should be skipped
     */
    public function shouldSkipMethodExecution(): bool
    {
        return $this->shouldSkipMethod;
    }
    
    /**
     * Reset early return state (useful for Around advice)
     */
    public function resetEarlyReturn(): void
    {
        $this->shouldReturnEarly = false;
        $this->earlyReturnValue = null;
        $this->shouldSkipMethod = false;
    }
}
