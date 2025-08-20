<?php

declare(strict_types=1);

namespace App\AOP;

/**
 * Base interface holding AOP advice (action to take at a joinpoint) and a filter 
 * determining the applicability of the advice (such as a pointcut).
 * 
 * This is equivalent to Spring's Advisor interface.
 */
interface Advisor
{
    /**
     * Get the advice that should be applied if the pointcut matches.
     */
    public function getAdvice(): Advice;

    /**
     * Get the pointcut that determines when this advice should be applied.
     */
    public function getPointcut(): Pointcut;

    /**
     * Check if this advisor applies to the given class and method.
     */
    public function matches(string $class, string $method): bool;
}
