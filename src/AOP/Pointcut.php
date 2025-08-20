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
     * Parse Spring-like pointcut expressions
     * Supports: execution(), within(), args(), @annotation()
     */
    private function parseExpression(string $expression): void
    {
        $expression = trim($expression);
        
        // Simple class::method format
        if (str_contains($expression, '::')) {
            [$class, $method] = explode('::', $expression);
            $this->class = trim($class);
            $this->method = trim($method);
            return;
        }

        // execution() expression
        if (preg_match('/execution\s*\(\s*([^)]+)\s*\)/', $expression, $matches)) {
            $this->parseExecutionExpression($matches[1]);
            return;
        }

        // within() expression
        if (preg_match('/within\s*\(\s*([^)]+)\s*\)/', $expression, $matches)) {
            $this->class = trim($matches[1]);
            $this->method = '*';
            return;
        }

        // @annotation() expression
        if (preg_match('/@annotation\s*\(\s*([^)]+)\s*\)/', $expression, $matches)) {
            $this->modifiers['annotation'] = trim($matches[1]);
            return;
        }

        // Default: treat as class name
        $this->class = $expression;
        $this->method = '*';
    }

    /**
     * Parse execution expression like: "public * com.example.*.*(..)"
     */
    private function parseExecutionExpression(string $executionExpr): void
    {
        $parts = explode(' ', trim($executionExpr));
        
        if (count($parts) >= 3) {
            $this->modifiers['visibility'] = $parts[0];
            $this->modifiers['returnType'] = $parts[1];
            
            $methodSignature = $parts[2];
            if (str_contains($methodSignature, '(')) {
                [$fullMethod, $params] = explode('(', $methodSignature, 2);
                $this->modifiers['parameters'] = rtrim($params, ')');
            }
            
            // Extract class and method from signature
            $methodParts = explode('.', $fullMethod);
            if (count($methodParts) >= 2) {
                $this->method = end($methodParts);
                array_pop($methodParts);
                $this->class = implode('.', $methodParts);
            }
        }
    }

    public function matches(string $class, string $method): bool
    {
        // Check if class matches
        if ($this->class && $this->class !== '*' && $this->class !== $class) {
            return false;
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

    /**
     * Check if this pointcut has annotation-based matching
     */
    public function hasAnnotationMatching(): bool
    {
        return isset($this->modifiers['annotation']);
    }

    /**
     * Get the annotation class for matching
     */
    public function getAnnotationClass(): ?string
    {
        return $this->modifiers['annotation'] ?? null;
    }
}