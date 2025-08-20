<?php

declare(strict_types=1);

namespace App\AOP\MethodMatcher;

use App\AOP\MethodMatcher;

/**
 * Method matcher for @annotation() pointcut expressions
 * Matches methods that have a specific annotation
 */
class AnnotationMethodMatcher implements MethodMatcher
{
    private string $annotationClass;

    public function __construct(string $annotationClass)
    {
        $this->annotationClass = $annotationClass;
    }

    public function matches(string $methodName): bool
    {
        // For now, this is a simplified implementation
        // In a full implementation, we would need access to the ReflectionMethod
        // to check for the actual annotation
        
        // This would require extending the MethodMatcher interface to include
        // a matches(ReflectionMethod $method) method
        
        // For demonstration purposes, we'll return true
        return true;
    }

    /**
     * Enhanced matcher that can check actual method annotations
     */
    public function matchesMethod(\ReflectionMethod $method): bool
    {
        $attributes = $method->getAttributes($this->annotationClass);
        return !empty($attributes);
    }
}
