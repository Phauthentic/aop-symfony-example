<?php

declare(strict_types=1);

namespace App\AOP\MethodMatcher;

use App\AOP\MethodMatcher;

/**
 * Method matcher for execution() pointcut expressions
 * Handles method name patterns with wildcards
 */
class ExecutionMethodMatcher implements MethodMatcher
{
    private string $pattern;

    public function __construct(string $pattern)
    {
        $this->pattern = $pattern;
    }

    public function matches(string $methodName): bool
    {
        // Handle wildcard patterns
        if ($this->pattern === '*') {
            return true;
        }

        // Handle prefix patterns (get*, set*)
        if (str_ends_with($this->pattern, '*')) {
            $prefix = rtrim($this->pattern, '*');
            return str_starts_with($methodName, $prefix);
        }

        // Handle suffix patterns (*Service, *Method)
        if (str_starts_with($this->pattern, '*')) {
            $suffix = ltrim($this->pattern, '*');
            return str_ends_with($methodName, $suffix);
        }

        // Exact match
        return $methodName === $this->pattern;
    }
}
