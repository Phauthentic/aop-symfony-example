<?php

declare(strict_types=1);

namespace App\AOP;

use App\AOP\ClassFilter\SimpleClassFilter;
use App\AOP\MethodMatcher\SimpleMethodMatcher;

/**
 * Pointcut: A predicate that matches join points
 * In Spring AOP, pointcuts are used to define where advice should be applied
 * 
 * This is the core Spring AOP Pointcut interface
 */
interface Pointcut
{
    /**
     * Check if this pointcut matches the given class and method
     */
    public function matches(string $class, string $method): bool;

    /**
     * Get the class filter component
     */
    public function getClassFilter(): ClassFilter;

    /**
     * Get the method matcher component
     */
    public function getMethodMatcher(): MethodMatcher;

    /**
     * Get the original expression string
     */
    public function getExpression(): string;
}