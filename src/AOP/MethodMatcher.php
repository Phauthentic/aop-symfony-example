<?php

declare(strict_types=1);

namespace App\AOP;

/**
 * Part of a Pointcut: Checks whether the target method is eligible for advice.
 * 
 * This is equivalent to Spring's MethodMatcher interface.
 */
interface MethodMatcher
{
    /**
     * Should the pointcut apply to the given method?
     * 
     * @param string $methodName The method name to check
     * @return bool Whether the pointcut should apply
     */
    public function matches(string $methodName): bool;
}
