# Application Aspects

This directory contains application-specific aspects that implement cross-cutting concerns using the AOP framework.

## Structure

- **`@AOP/`** - Core AOP framework classes (clean, no app-specific code)
- **`@Aspects/`** - Application-specific aspect implementations

## Available Aspects

### 1. FlowControlAspect
Demonstrates advanced flow control capabilities:
- **Caching**: Early returns for cached values
- **Validation**: Early returns for invalid inputs
- **Rate Limiting**: Method execution skipping
- **Around Advice**: Complete execution control

### 2. StringModificationAspect
Shows basic string manipulation:
- **Before Advice**: Adds prefixes to input arguments
- **AfterReturning Advice**: Adds suffixes to return values
- **AfterThrowing Advice**: Exception handling and logging

### 3. LoggingAspect
Comprehensive logging for UserService:
- **Before Advice**: Log method entry
- **Around Advice**: Method interception and profiling
- **AfterReturning Advice**: Log successful returns
- **After Advice**: Log method completion

### 4. PerformanceAspect
Performance monitoring and metrics:
- **Around Advice**: Execution time and memory usage measurement
- **Exception Handling**: Performance tracking for failures

### 5. SpringStyleAspect
Spring AOP style patterns:
- **execution() expressions**: Method execution matching
- **within() expressions**: Class-level matching
- **Getter/Setter patterns**: Common method pattern matching

### 6. ArgumentModificationAspect
Demonstrates argument manipulation:
- **Before Advice**: Modify method arguments
- **AfterReturning Advice**: Intercept return values
- **After Advice**: Log final state

### 7. TestAspect
Simple testing aspect:
- **Before Advice**: Log method calls
- **After Advice**: Log method completion

## Creating New Aspects

To create a new aspect:

1. **Create the class** in `src/Aspects/`
2. **Mark with `#[Aspect]`** attribute
3. **Add advice methods** with appropriate annotations
4. **Use `JoinPoint`** for method information and control

### Example

```php
<?php

declare(strict_types=1);

namespace App\Aspects;

use App\AOP\Before;
use App\AOP\Aspect;
use App\AOP\JoinPoint;

#[Aspect]
class MyCustomAspect
{
    #[Before("App\Service\MyService::*")]
    public function beforeMyServiceMethod(JoinPoint $joinPoint): void
    {
        echo "Before executing: " . $joinPoint->getSignature() . "\n";
        
        // You can:
        // - Modify arguments: $joinPoint->setArguments($newArgs)
        // - Set early return: $joinPoint->setEarlyReturn($value)
        // - Skip execution: $joinPoint->skipMethodExecution()
    }
}
```

## Best Practices

1. **Keep aspects focused** on a single cross-cutting concern
2. **Use descriptive names** that indicate the aspect's purpose
3. **Document pointcut expressions** clearly
4. **Handle exceptions gracefully** in advice methods
5. **Use early returns sparingly** - prefer Around advice for complex control
6. **Test aspects thoroughly** as they affect multiple methods

## Integration

Aspects are automatically discovered and applied by:
1. **AopCompilerPass**: Scans container for aspect services
2. **AopProxyGenerator**: Generates proxy classes with advice injection
3. **Service Container**: Manages aspect lifecycle and dependencies

No additional configuration is needed - just create your aspect class and it will be automatically integrated into the AOP system.
