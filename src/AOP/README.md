# Symfony AOP Implementation - Runtime Interception Approach

## Overview

This AOP implementation provides cross-cutting functionality using **runtime interception** instead of proxy generation. This approach is more suitable for PHP and avoids the complexity and issues associated with compile-time proxy generation.

## Key Differences from Proxy Generation

### ❌ **Problems with Proxy Generation Approach**
- **Broken Runtime**: Generated proxy classes don't exist, causing fatal errors
- **Overly Aggressive**: Replaces ALL services, including core Symfony services
- **Complexity**: Requires code generation, compilation, and class loading
- **Maintenance**: Generated code needs to be managed and updated
- **PHP Limitations**: PHP's dynamic nature makes static proxy generation problematic

### ✅ **Benefits of Runtime Interception**
- **Simple**: No code generation or compilation needed
- **Reliable**: Works with existing services without modification
- **Flexible**: Can be easily enabled/disabled at runtime
- **Maintainable**: Less code, fewer moving parts
- **PHP-Friendly**: Leverages PHP's dynamic capabilities

## Architecture

### 1. **RuntimeInterceptor**
The core component that handles advice execution:
```php
$interceptor = new RuntimeInterceptor($container);
$result = $interceptor->intercept($target, $methodName, $args, $originalMethod);
```

### 2. **ServiceWrapper**
Optional wrapper for services that need AOP interception:
```php
$wrapper = new ServiceWrapper($originalService, $interceptor);
$result = $wrapper->someMethod($args); // Automatically intercepted
```

### 3. **SimpleAopCompilerPass**
Registers advice without modifying service definitions:
```php
// Only registers the interceptor service
// Does NOT replace existing services
```

## Usage

### Basic Setup
1. **Register the compiler pass** in your kernel:
```php
protected function build(ContainerBuilder $container): void
{
    $container->addCompilerPass(new SimpleAopCompilerPass());
}
```

2. **Create aspects** with advice annotations:
```php
#[Aspect]
class LoggingAspect
{
    #[Before("App\Service\*::*")]
    public function logBefore(JoinPoint $joinPoint): void
    {
        // Log method execution
    }
}
```

3. **Use the interceptor** in your services:
```php
class UserService
{
    public function __construct(private RuntimeInterceptor $interceptor) {}
    
    public function someMethod($arg)
    {
        // Manual interception
        return $this->interceptor->intercept(
            $this, 
            'someMethod', 
            [$arg], 
            fn() => $this->originalMethod($arg)
        );
    }
}
```

### Automatic Interception
For automatic interception, wrap your services:
```php
// In a factory or service definition
$service = new UserService();
$wrapper = new ServiceWrapper($service, $interceptor);

// All method calls are automatically intercepted
$result = $wrapper->someMethod($arg);
```

## Advice Types

### Before Advice
```php
#[Before("App\Service\*::*")]
public function beforeAdvice(JoinPoint $joinPoint): void
{
    // Execute before method call
    // Can modify arguments: $joinPoint->setArgument(0, $value);
    // Can request early return: $joinPoint->setEarlyReturn($value);
}
```

### Around Advice
```php
#[Around("App\Service\*::*")]
public function aroundAdvice(JoinPoint $joinPoint)
{
    // Execute before method call
    $result = $joinPoint->proceed(); // Call original method
    // Execute after method call
    return $result;
}
```

### AfterReturning Advice
```php
#[AfterReturning("App\Service\*::*")]
public function afterReturningAdvice($result)
{
    // Execute after successful return
    return $result; // Can modify return value
}
```

### AfterThrowing Advice
```php
#[AfterThrowing("App\Service\*::*")]
public function afterThrowingAdvice(\Throwable $exception): void
{
    // Execute when exception is thrown
    // Exception is re-thrown after advice execution
}
```

### After Advice
```php
#[After("App\Service\*::*")]
public function afterAdvice(JoinPoint $joinPoint): void
{
    // Execute after method call (finally block)
    // Always executes, even with exceptions
}
```

## Pointcut Expressions

### Simple Patterns
```php
"App\Service\UserService::getName"     // Specific method
"App\Service\UserService::*"           // All methods in class
"App\Service\*::*"                     // All methods in all service classes
```

### Advanced Patterns (Future Enhancement)
```php
"execution(* App\Service\*.*(..))"     // Spring-style execution
"within(App\Service\*)"                // Within package
"@annotation(Loggable)"                // Annotation-based
```

## Performance Considerations

### Runtime Overhead
- **Minimal**: Only intercepts methods with matching advice
- **Efficient**: Uses simple string matching for pointcuts
- **Cached**: Advice mapping is built once at container compilation

### Memory Usage
- **Low**: No generated proxy classes stored in memory
- **Shared**: Single interceptor instance handles all advice
- **Clean**: No temporary files or generated code

## Migration from Proxy Generation

If you were using the old proxy generation approach:

1. **Remove old compiler pass**:
```php
// Remove this
$container->addCompilerPass(new AopCompilerPass());
```

2. **Add new compiler pass**:
```php
// Add this
$container->addCompilerPass(new SimpleAopCompilerPass());
```

3. **Update service usage** (if needed):
```php
// Old: Direct service injection (may have been broken)
$userService = $container->get('user.service');

// New: Use wrapper for AOP interception
$userService = new ServiceWrapper(
    $container->get('user.service'),
    $container->get('aop.runtime_interceptor')
);
```

## Best Practices

1. **Keep aspects focused** - One concern per aspect
2. **Use specific pointcuts** - Avoid overly broad patterns
3. **Handle exceptions** - Always catch and log errors in advice
4. **Test thoroughly** - AOP can affect method execution flow
5. **Monitor performance** - Ensure advice doesn't add significant overhead

## Future Enhancements

- **Pointcut Parser**: Full Spring-style pointcut expression support
- **Advice Ordering**: Control execution order of multiple advice
- **Conditional Advice**: Execute advice based on runtime conditions
- **Performance Profiling**: Built-in timing and metrics
- **Hot Reloading**: Update advice without restarting

## Conclusion

This runtime interception approach provides a clean, reliable, and maintainable AOP implementation that's well-suited for PHP and Symfony applications. It eliminates the complexity of proxy generation while maintaining all the benefits of aspect-oriented programming.
