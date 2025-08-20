<?php

declare(strict_types=1);

namespace App\AOP;

/**
 * Pointcut: A predicate that matches join points
 * In Spring AOP, pointcuts are used to define where advice should be applied
 */
class Pointcut
{
    private string $expression;
    private ?string $class = null;
    private ?string $method = null;
    private array $modifiers = [];

    public function __construct(string $expression)
    {
        $this->expression = $expression;
        $this->parseExpression($expression);
    }

    /**
     * Simple pointcut expression parsing
     * Supports: Class::method, Class::*, Class
     */
    private function parseExpression(string $expression): void
    {
        $expression = trim($expression);
        
        // Simple class::method format
        if (str_contains($expression, '::')) {
            [$class, $method] = explode('::', $expression, 2);
            $this->class = trim($class);
            $this->method = trim($method);
            return;
        }

        // Default: treat as class name (all methods)
        $this->class = $expression;
        $this->method = '*';
    }



    public function matches(string $class, string $method): bool
    {
        // Check if class matches
        if ($this->class && $this->class !== '*') {
            // Handle wildcard patterns like "App\Service\*"
            if (str_ends_with($this->class, '*')) {
                $baseNamespace = rtrim($this->class, '*');
                if (!str_starts_with($class, $baseNamespace)) {
                    return false;
                }
            } else {
                // Exact class match
                if ($class !== $this->class) {
                    return false;
                }
            }
        }

        // Check if method matches
        if ($this->method && $this->method !== '*' && $this->method !== $method) {
            return false;
        }

        return true;
    }

    public function getExpression(): string
    {
        return $this->expression;
    }

    public function getClass(): ?string
    {
        return $this->class;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function getModifiers(): array
    {
        return $this->modifiers;
    }


}