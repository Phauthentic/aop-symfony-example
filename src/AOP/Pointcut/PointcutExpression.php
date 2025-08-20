<?php

declare(strict_types=1);

namespace App\AOP\Pointcut;

use App\AOP\ClassFilter;
use App\AOP\MethodMatcher;
use App\AOP\Pointcut;
use App\AOP\ClassFilter\SimpleClassFilter;
use App\AOP\ClassFilter\ExecutionClassFilter;
use App\AOP\ClassFilter\WithinClassFilter;
use App\AOP\ClassFilter\AllClassFilter;
use App\AOP\MethodMatcher\SimpleMethodMatcher;
use App\AOP\MethodMatcher\ExecutionMethodMatcher;
use App\AOP\MethodMatcher\AllMethodMatcher;
use App\AOP\MethodMatcher\AnnotationMethodMatcher;

/**
 * Spring-style pointcut expression parser
 * Supports expressions like:
 * - execution(* App\Service\*.*(..))
 * - within(App\Service\*)
 * - @annotation(App\Annotation\Loggable)
 */
class PointcutExpression implements Pointcut
{
    private string $expression;
    private ClassFilter $classFilter;
    private MethodMatcher $methodMatcher;

    public function __construct(string $expression)
    {
        $this->expression = trim($expression);
        $this->parseExpression();
    }

    private function parseExpression(): void
    {
        // Handle execution() expressions
        if (preg_match('/^execution\s*\(\s*(.+)\s*\)$/', $this->expression, $matches)) {
            $this->parseExecutionExpression($matches[1]);
            return;
        }

        // Handle within() expressions
        if (preg_match('/^within\s*\(\s*(.+)\s*\)$/', $this->expression, $matches)) {
            $this->parseWithinExpression($matches[1]);
            return;
        }

        // Handle @annotation() expressions
        if (preg_match('/^@annotation\s*\(\s*(.+)\s*\)$/', $this->expression, $matches)) {
            $this->parseAnnotationExpression($matches[1]);
            return;
        }

        // Fallback to simple Class::method format
        $this->parseSimpleExpression();
        
        // Ensure properties are always set (safety check)
        if (!isset($this->classFilter) || !isset($this->methodMatcher)) {
            $this->classFilter = new AllClassFilter();
            $this->methodMatcher = new AllMethodMatcher();
        }
    }

    private function parseExecutionExpression(string $pattern): void
    {
        // execution(* App\Service\*.*(..))
        // Simplified parser for: [modifiers] returnType className.methodName(parameters)
        
        $parts = explode('.', $pattern);
        if (count($parts) >= 2) {
            $methodPart = array_pop($parts);
            $classPart = implode('.', $parts);
            
            // Extract method name and parameters
            if (preg_match('/^([^(]+)\s*\(([^)]*)\)$/', $methodPart, $matches)) {
                $methodName = trim($matches[1]);
                // For now, ignore parameters
                
                // Extract class pattern (remove return type if present)
                $classPattern = preg_replace('/^\*?\s*/', '', $classPart);
                
                $this->classFilter = new ExecutionClassFilter($classPattern);
                $this->methodMatcher = new ExecutionMethodMatcher($methodName);
                return;
            }
        }
        
        // Fallback if parsing fails
        $this->classFilter = new AllClassFilter();
        $this->methodMatcher = new AllMethodMatcher();
    }

    private function parseWithinExpression(string $pattern): void
    {
        // within(App\Service\*)
        $this->classFilter = new WithinClassFilter($pattern);
        $this->methodMatcher = new AllMethodMatcher(); // Match all methods
    }

    private function parseAnnotationExpression(string $annotation): void
    {
        // @annotation(App\Annotation\Loggable)
        $this->classFilter = new AllClassFilter(); // Match all classes
        $this->methodMatcher = new AnnotationMethodMatcher($annotation);
    }

    private function parseSimpleExpression(): void
    {
        // Fallback to our existing simple format: Class::method
        if (str_contains($this->expression, '::')) {
            [$class, $method] = explode('::', $this->expression, 2);
            $this->classFilter = new SimpleClassFilter(trim($class));
            $this->methodMatcher = new SimpleMethodMatcher(trim($method));
        } else {
            $this->classFilter = new SimpleClassFilter($this->expression);
            $this->methodMatcher = new AllMethodMatcher();
        }
    }

    public function matches(string $class, string $method): bool
    {
        return $this->classFilter->matches($class) && $this->methodMatcher->matches($method);
    }

    public function getClassFilter(): ClassFilter
    {
        return $this->classFilter;
    }

    public function getMethodMatcher(): MethodMatcher
    {
        return $this->methodMatcher;
    }

    public function getExpression(): string
    {
        return $this->expression;
    }
}
