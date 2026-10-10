# Advice

An aspect is a class marked `#[Aspect]`.
Each advice is a method marked with one of the advice attributes and a pointcut expression.

| Attribute | Behaviour |
|---|---|
| `#[Before]` | Runs before the target. Throwing stops the call. |
| `#[AfterReturning(pointcut, returning: 'name')]` | Runs after a normal return and receives the result. It cannot replace the result. |
| `#[AfterThrowing(pointcut, throwing: 'name')]` | Runs when the target throws, receives the exception, then the exception is rethrown. |
| `#[After]` | Runs always, like `finally`. |
| `#[Around]` | Receives a `ProceedingJoinPointInterface`. Its return value becomes the method result. `proceed()` may be called zero or more times, `proceed($args)` replaces the arguments. |

## Advice parameters

- A parameter typed `JoinPointInterface` or `ProceedingJoinPointInterface` receives the join point.
- A parameter named like `returning` or `throwing` receives the result or the exception, restricted by its type.
- A parameter named like a binding in the pointcut receives the bound value.
- Any other parameter is a `BindingException` at container build time.

## Join point

`getArgs()`, `getThis()` (the proxy), `getTarget()`, `getSignature()` and `toShortString()`.

## Ordering

1. `#[Order(n)]` on the aspect class, ascending.
   Lower values have higher precedence and run outermost.
   The default is `PHP_INT_MAX`.
2. Within one aspect: Around, Before, After, AfterReturning, AfterThrowing.
3. Advice of the same type in one aspect runs in declaration order.

The highest precedence runs first on the way in and last on the way out.

## Example

```php
#[Aspect]
#[Order(3)]
final class RetryAspect
{
    #[Around('@annotation(retry)')]
    public function around(ProceedingJoinPointInterface $pjp, Retry $retry): mixed
    {
        for ($attempt = 1; ; ++$attempt) {
            try {
                return $pjp->proceed();
            } catch (\Throwable $e) {
                if ($attempt >= $retry->attempts) {
                    throw $e;
                }
            }
        }
    }
}
```

The complete demo lives in `src/Aspect`, run it with `bin/console app:aop-demo`.
