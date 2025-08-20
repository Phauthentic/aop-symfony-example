# Simple Symfony AOP Implementation

This is a simplified PHP implementation of Aspect-Oriented Programming (AOP) for Symfony.

## Overview

This AOP implementation provides basic cross-cutting functionality:

- **Aspects**: Classes marked with `#[Aspect]` that implement cross-cutting concerns
- **Advice**: Actions taken by aspects at join points  
- **Join Points**: Points during program execution (method execution)
- **Pointcuts**: Simple patterns that match classes and methods

## Advice Types

The system supports basic advice types:

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

The system supports simple pointcut expressions:

### Class::Method Format
```php
#[Before("App\Service\UserService::getName")]  // Specific method
#[Before("App\Service\UserService::*")]  // All methods in class
```

### Class Name Format
```php
#[Before("App\Service\UserService")]  // All methods in class (shorthand)
```

### Wildcard Support
```php
#[Before("App\Service\*")]  // All classes starting with App\Service\
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
    #[Before("App\Service\*")]
    public function logBefore(): void
    {
        echo "Executing service method\n";
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

See `TestAspect.php` for a simple example showing basic advice types and pointcut expressions.

## Current Status

This is a basic implementation that provides:
- ✅ Aspect detection and advice collection
- ✅ Simple pointcut matching
- ✅ Integration with Symfony DI container
- ⏳ Proxy generation (not yet implemented)
- ⏳ Actual advice execution (not yet implemented)

## Limitations

- Currently only detects and matches advice, doesn't execute it yet
- Proxy generation needs to be implemented
- Only supports method execution join points
- Simplified pointcut expression language
