<?php

declare(strict_types=1);

namespace App\AOP\MethodMatcher;

use App\AOP\MethodMatcher;

/**
 * Simple method matcher implementation that supports exact matches and wildcards.
 */
class SimpleMethodMatcher implements MethodMatcher
{
    private string $pattern;

    public function __construct(string $pattern)
    {
        $this->pattern = $pattern;
    }

    public function matches(string $methodName): bool
    {
        // Wildcard matches all methods
        if ($this->pattern === '*') {
            return true;
        }

        // Exact method match
        return $methodName === $this->pattern;
    }
}
