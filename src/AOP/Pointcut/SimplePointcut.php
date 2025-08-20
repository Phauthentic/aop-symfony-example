<?php

declare(strict_types=1);

namespace App\AOP\Pointcut;

use App\AOP\ClassFilter\SimpleClassFilter;
use App\AOP\MethodMatcher\SimpleMethodMatcher;
use App\AOP\Pointcut;

/**
 * Simple pointcut implementation for basic Class::method expressions
 * This replaces the old concrete Pointcut class
 */
class SimplePointcut implements Pointcut
{
    private string $expression;
    private \App\AOP\ClassFilter $classFilter;
    private \App\AOP\MethodMatcher $methodMatcher;

    public function __construct(string $expression)
    {
        $this->expression = $expression;
        $this->parseExpression($expression);
    }

    /**
     * Parse pointcut expression and create appropriate filters
     * Supports: Class::method, Class::*, Class, and wildcard patterns
     */
    private function parseExpression(string $expression): void
    {
        $expression = trim($expression);
        
        if (str_contains($expression, '::')) {
            [$class, $method] = explode('::', $expression, 2);
            $class = trim($class);
            $method = trim($method);
        } else {
            $class = $expression;
            $method = '*';
        }

        $this->classFilter = new SimpleClassFilter($class);
        $this->methodMatcher = new SimpleMethodMatcher($method);
    }

    public function matches(string $class, string $method): bool
    {
        return $this->classFilter->matches($class) && $this->methodMatcher->matches($method);
    }

    public function getClassFilter(): \App\AOP\ClassFilter
    {
        return $this->classFilter;
    }

    public function getMethodMatcher(): \App\AOP\MethodMatcher
    {
        return $this->methodMatcher;
    }

    public function getExpression(): string
    {
        return $this->expression;
    }
}
