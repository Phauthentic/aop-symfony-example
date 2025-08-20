<?php

declare(strict_types=1);

namespace App\AOP\ClassFilter;

use App\AOP\ClassFilter;

/**
 * Class filter for execution() pointcut expressions
 * Handles patterns like: App\Service\*, com.example.service.*
 */
class ExecutionClassFilter implements ClassFilter
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
        $normalizedClass = $className;

        // Handle wildcard patterns
        if (str_ends_with($normalizedPattern, '*')) {
            $basePattern = rtrim($normalizedPattern, '*');
            return str_starts_with($normalizedClass, $basePattern);
        }

        // Handle sub-package patterns (..)
        if (str_contains($normalizedPattern, '..')) {
            $parts = explode('..', $normalizedPattern);
            $prefix = $parts[0];
            $suffix = $parts[1] ?? '';
            
            return str_starts_with($normalizedClass, $prefix) && 
                   (empty($suffix) || str_ends_with($normalizedClass, $suffix));
        }

        // Exact match
        return $normalizedClass === $normalizedPattern;
    }
}
