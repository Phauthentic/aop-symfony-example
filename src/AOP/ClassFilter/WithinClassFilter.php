<?php

declare(strict_types=1);

namespace App\AOP\ClassFilter;

use App\AOP\ClassFilter;

/**
 * Class filter for within() pointcut expressions
 * Matches join points within certain types or packages
 */
class WithinClassFilter implements ClassFilter
{
    private string $pattern;

    public function __construct(string $pattern)
    {
        $this->pattern = $pattern;
    }

    public function matches(string $className): bool
    {
        // Convert Java-style dot notation to PHP namespace notation
        $normalizedPattern = str_replace('.', '\\', $this->pattern);
        
        // Handle wildcard patterns for packages
        if (str_ends_with($normalizedPattern, '*')) {
            $basePackage = rtrim($normalizedPattern, '*');
            return str_starts_with($className, $basePackage);
        }

        // Handle sub-package patterns (..)
        if (str_contains($normalizedPattern, '..')) {
            $parts = explode('..', $normalizedPattern);
            $basePackage = $parts[0];
            return str_starts_with($className, $basePackage);
        }

        // Exact class match
        return $className === $normalizedPattern;
    }
}
