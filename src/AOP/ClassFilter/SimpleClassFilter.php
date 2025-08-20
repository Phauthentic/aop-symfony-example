<?php

declare(strict_types=1);

namespace App\AOP\ClassFilter;

use App\AOP\ClassFilter;

/**
 * Simple class filter implementation that supports exact matches and wildcard patterns.
 */
class SimpleClassFilter implements ClassFilter
{
    private string $pattern;

    public function __construct(string $pattern)
    {
        $this->pattern = $pattern;
    }

    public function matches(string $className): bool
    {
        // Handle wildcard patterns like "App\Service\*"
        if (str_ends_with($this->pattern, '*')) {
            $baseNamespace = rtrim($this->pattern, '*');
            return str_starts_with($className, $baseNamespace);
        }

        // Exact class match
        return $className === $this->pattern;
    }
}
