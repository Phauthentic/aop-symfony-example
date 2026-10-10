# Advice

An aspect is a class marked `#[Aspect]`.
Each advice is a public, non-static method marked with exactly one advice attribute.
A method may carry either one advice attribute or one `#[Pointcut]`, never both and never two.
`AspectMetadataReader` throws `AspectDefinitionException` otherwise.

## The five advice types

| Attribute | Constructor | Behaviour |
|---|---|---|
| `#[Before(string $pointcut)]` | `Before('...')` | Runs before the target. Throwing stops the call; the exception propagates to the caller and to outer `AfterThrowing`/`After` advice. |
| `#[AfterReturning(string $pointcut, ?string $returning = null)]` | `AfterReturning('...', returning: 'result')` | Runs after a normal return. Receives the result when `returning` names a parameter. Cannot replace the result. |
| `#[AfterThrowing(string $pointcut, ?string $throwing = null)]` | `AfterThrowing('...', throwing: 'e')` | Runs when the target or an inner advice throws. Receives the exception when `throwing` names a parameter. The exception is rethrown afterwards. |
| `#[After(string $pointcut)]` | `After('...')` | Runs always, like `finally`. Receives neither result nor exception. |
| `#[Around(string $pointcut)]` | `Around('...')` | Receives a `ProceedingJoinPointInterface`. Its return value becomes the method result. May call `proceed()` zero, one or many times. |

The attributes live in `Phauthentic\AOP\Attribute\` and target methods only.
`#[Aspect]`, `#[Order(int $value)]` and `#[Proxyable(ProxyStrategy $strategy)]` target classes.

### Around

```php
use Phauthentic\AOP\Attribute\Around;
use Phauthentic\AOP\JoinPoint\ProceedingJoinPointInterface;

#[Around('execution(* App\Service\**::*(..))')]
public function around(ProceedingJoinPointInterface $pjp): mixed
{
    $result = $pjp->proceed();              // continue with the original arguments
    $other  = $pjp->proceed(['changed']);   // run the rest of the chain again with new arguments

    return $result;
}
```

`proceed(?array $args = null)` replaces the arguments for the remaining chain when given.
The replaced arguments are what inner advice sees in `getArgs()` and what reaches the target.
Not calling `proceed()` skips the target and all inner advice.

By-reference parameters stay connected to the caller's variables.
`proceed()` without arguments lets the target write to them directly.
`proceed($args)` writes back after the rest of the chain returns: the caller's variable receives what the target left in the replaced slot, or the replacement value if the target did not change it.
If the chain throws, the caller's variables keep their previous values.
`getArgs()` and `args()` bindings hand advice copies of the values, so assigning to them never reaches the caller.

The return type of an `#[Around]` method is yours to choose; `mixed` is the safe default.
The runtime does not coerce the value to the target's return type.
When the final result of the chain does not satisfy the declared return type, `AopRuntime` throws an `AdviceReturnException` naming the Around advice, the returned type and the method's declared type.
`int` is accepted for `float`, and `static` accepts the raw target instance because an interface proxy wraps it afterwards.

### AfterReturning and AfterThrowing filters

The type of the `returning` or `throwing` parameter filters when the advice runs:

```php
#[AfterReturning('execution(* App\Service\**::*(..))', returning: 'result')]
public function onString(string $result): void { /* only for string results */ }

#[AfterThrowing('execution(* App\Service\**::*(..))', throwing: 'e')]
public function onRuntime(\RuntimeException $e): void { /* only for RuntimeException and subclasses */ }
```

A result or exception that does not satisfy the parameter type skips the advice silently (`BindingPlan::acceptsReturnValue()` / `acceptsThrowable()`).
`mixed`, an untyped parameter or `\Throwable` accept everything.
Union and intersection types are honoured.
A nullable type accepts `null`.

Declaring `returning: 'x'` without a `$x` parameter is a `BindingException`.
Leaving `returning` out is fine; the advice then runs after every normal return without seeing the value.

## Parameters

`ParameterBinder` decides once, at weave time, where every advice parameter comes from.
Parameters may appear in any order.

| Parameter | Source |
|---|---|
| Typed `JoinPointInterface` | The join point. |
| Typed `ProceedingJoinPointInterface` | The join point with `proceed()`. Only allowed in `#[Around]`, and required there. |
| Named like `returning:` | The return value, if its type matches. |
| Named like `throwing:` | The thrown exception, if its type matches. |
| Named like a binding in the pointcut | The bound value: an argument, the proxy, the target, or an attribute instance. See [Binding](pointcuts.md#binding). |
| Anything else | `BindingException` at weave time: `Cannot bind parameter $x of Aspect::method(): ...`. |

An advice method with no parameters is valid.

```php
#[Before('execution(* App\Service\StringService::greet(..)) && args(name, ..)')]
public function before(string $name): void
{
    if ('' === $name) {
        throw new \InvalidArgumentException('Name must not be empty');
    }
}
```

## The join point

`JoinPointInterface`:

| Method | Returns |
|---|---|
| `getArgs(): array` | The current arguments, positional, with variadic keys preserved. After `proceed($args)` inner advice sees the replaced arguments. By-reference arguments are returned as values. |
| `getThis(): object` | The proxy the call was made on. |
| `getTarget(): object` | The advised instance: the proxy itself for subclass proxies, the wrapped object for interface proxies. |
| `getSignature(): MethodSignature` | `className`, `methodName`, `method` (a `\ReflectionMethod`), `toShortString()` (`UserService::findUser(..)`), `toLongString()` (`App\Service\UserService::findUser(int, ...string)`). |
| `toShortString(): string` | Shortcut for `getSignature()->toShortString()`. |

`ProceedingJoinPointInterface` adds `proceed(?array $args = null): mixed`.

`MethodSignature::className` is the class that declares the method, which may be a parent of the target class.

Outside advice, a target can reach its own proxy with `AopContext::currentProxy()`; see [Proxies](proxies.md#self-invocation-and-aopcontext).

## Ordering

`AdvisorRegistry` sorts all advisors once with `AdvisorOrderComparator`.
The sort key, in priority order:

1. The aspect's `#[Order(n)]` value, ascending.
   Lower values run outermost: first on the way in, last on the way out.
   The default is `PHP_INT_MAX`.
2. The aspect class name, as a string.
3. The advice type: Around, Before, After, AfterReturning, AfterThrowing.
4. The declaration index of the advice inside its aspect (method order in the class).

Key 2 means that aspects with the **same** order value run as blocks, sorted by class name, and never interleave.
Two unordered aspects `Alpha` and `Zeta`, each with `#[Before]` methods `a()` and `b()`, run as `Alpha::a, Alpha::b, Zeta::a, Zeta::b`.
Spring leaves the order between such aspects undefined; this library makes it deterministic but still arbitrary, so give each aspect its own `#[Order]` whenever relative order matters.

Within the chain, Around sits outside Before, which sits outside After, AfterReturning and AfterThrowing.
Because After wraps AfterReturning and AfterThrowing, it runs after them on the way out, which is the same as Spring Framework 5.2.7 and later.

### Worked example: two aspects, five advice types

Two aspects with every advice type, `A` with `#[Order(1)]` and `B` with `#[Order(2)]`, both matching `Greeter::greet()`.
This is `AopRuntimeTest::twoAspectsFiveAdviceTypesRunInSpecOrder()` in `lib/aop/tests/Unit/Runtime/AopRuntimeTest.php`.

```php
#[Aspect]
#[Order(1)]
final class RecordingAspectA
{
    #[Around('execution(* App\Service\**::*(..))')]
    public function around(ProceedingJoinPointInterface $pjp): mixed
    {
        Log::add('A.around>');
        try {
            return $pjp->proceed();
        } finally {
            Log::add('A.around<');
        }
    }

    #[Before('execution(* App\Service\**::*(..))')]
    public function before(JoinPointInterface $jp): void { Log::add('A.before'); }

    #[After('execution(* App\Service\**::*(..))')]
    public function after(JoinPointInterface $jp): void { Log::add('A.after'); }

    #[AfterReturning('execution(* App\Service\**::*(..))', returning: 'result')]
    public function afterReturning(mixed $result): void { Log::add('A.afterReturning'); }

    #[AfterThrowing('execution(* App\Service\**::*(..))', throwing: 'e')]
    public function afterThrowing(\Throwable $e): void { Log::add('A.afterThrowing'); }
}
// RecordingAspectB is identical with #[Order(2)] and the prefix "B.".
```

Chain built by the runtime (outermost first):

```
A.around, A.before, A.after, A.afterReturning, A.afterThrowing,
B.around, B.before, B.after, B.afterReturning, B.afterThrowing,
target
```

Trace when `greet()` returns normally:

```
A.around>
A.before
B.around>
B.before
target
B.afterReturning
B.after
B.around<
A.afterReturning
A.after
A.around<
```

Trace when `greet()` throws (the exception reaches the caller afterwards):

```
A.around>
A.before
B.around>
B.before
target
B.afterThrowing
B.after
B.around<
A.afterThrowing
A.after
A.around<
```

The same order applies in the demo app, where `LoggingAspect` is `#[Order(1)]`, `ValidationAspect` `#[Order(2)]`, `RetryAspect` `#[Order(3)]` and `CachingAspect` `#[Order(10)]`.
See [Examples](examples.md) for the resulting trace.

## Aspect instances

Aspects are plain objects; the library never instantiates them itself.
`AspectInstanceResolverInterface::get(string $aspectClass): object` supplies them.

- With the bundle, `ContainerAspectInstanceResolver` pulls the aspect from a service locator on first use.
  Aspects stay private services, may depend on anything, and a cycle between an aspect and a service it advises is fine because the aspect is resolved only when the first advised call happens.
- With `ProxyFactory::fromAspects()`, `ArrayAspectInstanceResolver` holds the instances you pass.
  It also accepts closures that create the aspect lazily.

`AdviceInvoker` caches the instance after the first resolution, so an aspect is resolved once per interceptor.

## Validation summary

All of these fail at weave time (container build with the bundle, `ProxyFactory` construction standalone) with `AspectDefinitionException` or `BindingException`:

- A class without `#[Aspect]` used as an aspect.
- A method with more than one advice or pointcut attribute.
- A non-public or static method carrying an advice or pointcut attribute.
- A `#[Pointcut]` method that declares parameters or does not return `void`.
- An `#[Around]` method without a `ProceedingJoinPointInterface` parameter.
- A non-`Around` method with a `ProceedingJoinPointInterface` parameter.
- `returning`/`throwing` naming a parameter that does not exist.
- A parameter that is neither a join point, nor `returning`/`throwing`, nor bound by the pointcut.
