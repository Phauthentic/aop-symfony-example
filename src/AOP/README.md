# Symfony AOP Implementation

This is a PHP implementation of Aspect-Oriented Programming (AOP) that follows Spring AOP patterns and concepts.

## Overview

This AOP implementation provides the same core concepts as Spring AOP:

- **Aspects**: Classes that implement cross-cutting concerns
- **Advice**: Actions taken by aspects at join points
- **Join Points**: Points during program execution (method execution)
- **Pointcuts**: Predicates that match join points

## Advice Types

The system supports all Spring AOP advice types:

### 1. @Before
Executed before the join point (method execution).

```php
#[Before("execution(* App\Service\*.*(..))")]
public function beforeAdvice(JoinPoint $joinPoint): void
{
    echo "Before executing: " . $joinPoint->getSignature() . "\n";
}
```

### 2. @After
Executed after the join point (finally block).

```php
#[After("within(App\Service\*)")]
public function afterAdvice(JoinPoint $joinPoint): void
{
    echo "After executing: " . $joinPoint->getSignature() . "\n";
}
```

### 3. @Around
Executed around the join point, allowing control over method execution.

```php
#[Around("execution(* App\Service\UserService::*())")]
public function aroundAdvice(JoinPoint $joinPoint)
{
    echo "Before method execution\n";
    $result = $joinPoint->proceed();
    echo "After method execution\n";
    return $result;
}
```

### 4. @AfterReturning
Executed after successful return from the join point.

```php
#[AfterReturning("execution(* App\Service\*::get*(..))")]
public function afterReturningAdvice(JoinPoint $joinPoint, $result): void
{
    echo "Method returned: " . $result . "\n";
}
```

### 5. @AfterThrowing
Executed when the join point throws an exception.

```php
#[AfterThrowing("execution(* App\Service\*::*())")]
public function afterThrowingAdvice(JoinPoint $joinPoint, \Throwable $exception): void
{
    echo "Exception: " . $exception->getMessage() . "\n";
}
```

## Pointcut Expressions

The system supports Spring-like pointcut expressions:

### Execution Expressions
```php
#[Before("execution(* App\Service\*.*(..))")]  // All methods in App\Service namespace
#[Before("execution(public * App\Service\*::get*())")]  // Public getter methods
#[Before("execution(* App\Service\UserService::*())")]  // All methods in UserService
```

### Within Expressions
```php
#[After("within(App\Service\*)")]  // All methods within App\Service namespace
#[After("within(App\Service\UserService)")]  // All methods within UserService class
```

### Annotation-based Pointcuts
```php
#[Before("@annotation(App\AOP\Loggable)")]  // Methods annotated with @Loggable
```

### Simple Class::Method Format
```php
#[Before("App\Service\UserService::getName")]  // Specific method
#[Before("App\Service\UserService::*")]  // All methods in class
```

## Creating Aspects

To create an aspect:

1. Mark the class with `#[Aspect]`
2. Add advice methods with appropriate annotations
3. Use `JoinPoint` parameter to access method information

```php
<?php

namespace App\AOP;

#[Aspect]
class LoggingAspect
{
    #[Before("execution(* App\Service\*.*(..))")]
    public function logBefore(JoinPoint $joinPoint): void
    {
        echo "Executing: " . $joinPoint->getSignature() . "\n";
    }
}
```

## JoinPoint Object

The `JoinPoint` object provides access to:

- `getTarget()`: The target object
- `getMethod()`: The method name
- `getArguments()`: Method arguments
- `getTargetClass()`: The target class name
- `getSignature()`: Full method signature
- `proceed()`: Execute the target method (for Around advice)
- `proceedWithArguments(array $args)`: Execute with modified arguments

## Configuration

The AOP system is automatically configured through Symfony's compiler pass system. No additional configuration is needed.

## Comparison with Spring AOP

| Spring AOP | This Implementation |
|------------|-------------------|
| `@Aspect` | `#[Aspect]` |
| `@Before` | `#[Before]` |
| `@After` | `#[After]` |
| `@Around` | `#[Around]` |
| `@AfterReturning` | `#[AfterReturning]` |
| `@AfterThrowing` | `#[AfterThrowing]` |
| `ProceedingJoinPoint` | `JoinPoint` |
| `execution()` expressions | `execution()` expressions |
| `within()` expressions | `within()` expressions |
| `@annotation()` | `@annotation()` |

## Example Usage

See `ExampleAspect.php` for a complete example showing all advice types and pointcut expressions.

## Limitations

- Currently only supports method execution join points (like Spring AOP)
- Proxy generation is not yet implemented
- Pointcut expression language is simplified compared to Spring's full implementation
