# Proxies

Advice runs because a generated proxy class stands in for the target.
This page covers the two strategies, how one is chosen, which classes and methods qualify, what the generated code looks like, and where the approach stops working.

## Two strategies

`Proxy\ProxyStrategy` has two cases.

| | `SUBCLASS` | `INTERFACE` |
|---|---|---|
| Spring equivalent | CGLIB proxy | JDK dynamic proxy |
| Generated class | `extends Target`, overrides the advised methods only | `implements` every interface of the target, delegates every interface method to a wrapped instance |
| Works for | Non-final classes (final ones with the bundle's opt-in `strip_final`) | Any class that implements at least one interface, including final ones |
| Inject as | The class or any interface | The interface only; the proxy is **not** an instance of the target class |
| `$this` inside the target | The proxy, so self-invocation is advised | The target, so self-invocation is not advised |
| `getThis()` / `getTarget()` | Both the proxy | The proxy / the wrapped instance |
| Constructor | Runs once, untouched, when the proxy is constructed | Untouched; the proxy's own constructor takes the target instance |
| Protected methods | Advised | Never (not declared by an interface) |
| Final methods | Skipped (advised with the bundle's opt-in `strip_final`) | Advised if an interface declares them |
| Non-interface public methods | Still callable | Do not exist on the proxy |
| Target returns `$this` | The proxy (it is `$this`) | The proxy, via `__aopWrap()` |
| Target returns another instance of its class | That instance, advised only if it is itself a proxy | A new proxy around it sharing the same runtime, via `__aopWrap()` |
| Readonly target class | The proxy is readonly too | The proxy is readonly too |
| Introduced interfaces | Implemented, delegating to one default implementation per proxy | Implemented, delegating to one default implementation per proxy |

Introduced interfaces come from `#[DeclareParents]`; see [Introductions](introductions.md).

The Symfony bundle can subclass final classes when `strip_final` is enabled: it loads a copy of the class file without `final` (`Proxy\FinalStripper`) and passes `allowFinal: true` to `ProxyStrategyResolver::resolve()` and `MethodEligibility::reasonForExclusion()`.
It is off by default and risky; see [strip_final](symfony.md#strip_final-opt-in-risky).

## Auto-selection

`Proxy\ProxyStrategyResolver::resolve(\ReflectionClass $target, ?ProxyStrategy $configured, bool $allowFinal = false): ?ProxyStrategy`:

1. `#[Proxyable(ProxyStrategy::X)]` on the target class wins over everything.
2. Otherwise the configured strategy applies (`proxy_strategy` in the bundle, the constructor argument of `ProxyFactory`).
3. With no configured strategy (`auto` / `null`): non-final classes get `SUBCLASS`; final classes with interfaces get `INTERFACE`; final classes without interfaces get `null`, meaning not proxyable.

An explicit choice that cannot apply raises `ProxyGenerationException`:
`SUBCLASS` on a final class, or `INTERFACE` on a class without interfaces.
The bundle catches this and lists the service under "Skipped services" with the message; `ProxyFactory` lets it propagate.

```php
use Phauthentic\AOP\Attribute\Proxyable;
use Phauthentic\AOP\Proxy\ProxyStrategy;

#[Proxyable(ProxyStrategy::INTERFACE)]
class PreferInterfaceProxy implements SomeInterface { /* ... */ }
```

The bundle adds two more rules on top, described in [Symfony bundle](symfony.md#strategy-rules-specific-to-the-container): services created by a factory or with a `file` fall back to the interface strategy, and tagged services whose public methods an interface proxy would hide are skipped.

## Eligibility

`Proxy\MethodEligibility` returns a human-readable reason for every exclusion; `debug:aop` prints it.

### Classes that are never proxied

| Class | Reason |
|---|---|
| Interface | `interfaces cannot be proxied` |
| Trait | `traits cannot be proxied` |
| Enum | `enums cannot be proxied` |
| Anonymous class | `anonymous classes cannot be proxied` |
| Internal (PHP or extension) class | `internal classes cannot be proxied` |
| Abstract class | `abstract classes cannot be proxied` |

### Methods that are never advised

| Method | Reason |
|---|---|
| Private | `private methods cannot be overridden` |
| Static | `static methods are not called on an instance` |
| Abstract | `abstract methods have no implementation to advise` |
| Name starts with `__` | `constructors, destructors and magic methods are never advised` |
| Returns by reference | `methods returning by reference are not supported` |
| Final, subclass strategy | `final methods cannot be overridden by a subclass proxy` |
| Has an object default value (`new` in initializer) | `parameter $x has an object default value (new in initializer) that cannot be reproduced` |
| Not declared by an interface, interface strategy | `interface proxies can only advise public methods declared by an interface of the target` |

By-reference parameters are eligible: the proxy keeps the `&` in its signature and passes those arguments to the runtime as references, so the target writes through to the caller's variables.
Advice sees their values, see [Around advice](advice.md#around) for `proceed($args)`.

A method that is eligible but matches no advisor is simply not overridden; the proxy keeps the inherited implementation (subclass) or forwards to the target without going through the runtime (interface).

## Generated code

Both generators share `Proxy\ProxyCodeBuilder`, which uses `nette/php-generator`.
The proxy class name is `Phauthentic\AOP\Generated\<Target FQCN>Proxy_<12 hex>`, where the hash covers the target class, the strategy, the sorted advised method names and a salt (the service id plus the proxy directory in the bundle; the bean name in `ProxyFactory`).
Two services of the same class therefore get two proxy classes, each registered with its own bean name.

Every proxy implements `Proxy\AdvisedInterface`:

```php
interface AdvisedInterface
{
    public function __aopSetRuntime(AopRuntime $runtime): void;
    public function __aopGetTarget(): object;
}
```

The runtime is stored once per proxy class, not per instance.
Once any instance of a proxy class has received the runtime, all instances are advised, including those created with `new static` inside the target.
Until then the proxy behaves exactly like the target: every advised method checks `self::__aopRuntime()` and calls through directly if it returns `null`.
The runtime lives in a `static` local variable of a private static method because PHP forbids static properties on readonly classes.

### Subclass proxy

Real output for `App\Service\StringService` from `var/cache/dev/phauthentic_aop/Phauthentic/AOP/Generated/App/Service/StringServiceProxy_aad667b31091.php`, trimmed to two methods:

```php
<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Generated\App\Service;

class StringServiceProxy_aad667b31091 extends \App\Service\StringService implements \Phauthentic\AOP\Proxy\AdvisedInterface
{
    private static function __aopRuntime(?\Phauthentic\AOP\Runtime\AopRuntime $runtime = null): ?\Phauthentic\AOP\Runtime\AopRuntime
    {
        static $current = null;

        return $current = $runtime ?? $current;
    }

    public function __aopSetRuntime(\Phauthentic\AOP\Runtime\AopRuntime $runtime): void
    {
        self::__aopRuntime($runtime);
    }

    public function __aopGetTarget(): object
    {
        return $this;
    }

    public function flaky(string $input): string
    {
        if (null === self::__aopRuntime()) {
            return parent::flaky($input);
        }
        return self::__aopRuntime()->invoke($this, $this, \App\Service\StringService::class, 'flaky', [$input], fn (array $args): mixed => parent::flaky(...$args));
    }

    public function greet(string $name, int $times = 1): string
    {
        if (null === self::__aopRuntime()) {
            return parent::greet($name, $times);
        }
        return self::__aopRuntime()->invoke($this, $this, \App\Service\StringService::class, 'greet', [$name, $times], fn (array $args): mixed => parent::greet(...$args));
    }
}
```

Points to notice:

- Only advised methods are overridden; `StringService` has no constructor, so none is generated.
- Optional parameters are forwarded with their defaults, which is why `args(name)` does not match `greet()` (see [Pointcut expressions](pointcuts.md#args)).
- The closure calls `parent::` with the (possibly replaced) argument list from the chain.
- `void` methods call `parent::x(...)` and `return;` in the early-exit branch; `never` methods have no `return`.

### Interface proxy

Real output for `App\Service\OrderService` (a final class) from `OrderServiceProxy_90e0b43165d3.php`:

```php
<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Generated\App\Service;

class OrderServiceProxy_90e0b43165d3 implements \Phauthentic\AOP\Proxy\AdvisedInterface, \App\Service\OrderServiceInterface
{
    private static function __aopRuntime(?\Phauthentic\AOP\Runtime\AopRuntime $runtime = null): ?\Phauthentic\AOP\Runtime\AopRuntime
    {
        static $current = null;

        return $current = $runtime ?? $current;
    }

    public function __aopSetRuntime(\Phauthentic\AOP\Runtime\AopRuntime $runtime): void
    {
        self::__aopRuntime($runtime);
    }

    public function __construct(
        private readonly \App\Service\OrderServiceInterface $__aopTarget,
    ) {
    }

    public function __aopGetTarget(): object
    {
        return $this->__aopTarget;
    }

    private function __aopWrap(mixed $result): mixed
    {
        if ($result === $this->__aopTarget) {
            return $this;
        }
        if (!$result instanceof \App\Service\OrderService) {
            return $result;
        }

        return new static($result);
    }

    public function placeOrder(string $sku, int $qty): string
    {
        if (null === self::__aopRuntime()) {
            return $this->__aopTarget->placeOrder($sku, $qty);
        }
        return self::__aopRuntime()->invoke($this, $this->__aopTarget, \App\Service\OrderService::class, 'placeOrder', [$sku, $qty], fn (array $args): mixed => $this->__aopTarget->placeOrder(...$args));
    }
}
```

Points to notice:

- The constructor parameter is typed with the intersection of the target's minimal interface set (interfaces not implied by another one), or `object` when there is none.
- Every interface method is generated, advised or not; unadvised ones forward to the target directly.
- Static interface methods forward to the target class statically.
- Results of methods whose return type could hold the proxy (`mixed`, `object`, `static`, an implemented interface, or no type) pass through `__aopWrap()`.
  `placeOrder(): string` cannot, so it does not.
- A return type naming the target class itself (or `self`) becomes `static`, because the proxy is not a `OrderService`.

### Signature copying

`ProxyCodeBuilder::signature()` copies the method signature with these adjustments:

- `self` and `parent` in types are resolved to the declaring class and its parent.
- Method attributes are dropped, except `#[\ReturnTypeWillChange]`.
- Parameter attributes are dropped, except `#[\SensitiveParameter]`.
- Constant default values are made absolute (`\App\Foo::BAR`); non-public constants are inlined by value.
- Abstract and final modifiers are removed; visibility stays public or protected.

## Self-invocation and `AopContext`

`Phauthentic\AOP\Runtime\AopContext::currentProxy()` returns the proxy whose advised method is currently executing.
It mirrors Spring's `AopContext.currentProxy()`.
`AopRuntime::invoke()` sets it for the whole invocation, advice included, and restores the previous value in a `finally` block.
Nested calls through other proxies therefore restore the outer proxy, also after an exception.
Outside an advised call it returns `null`.
It never throws.
Unlike Spring there is no `exposeProxy` switch: the context is always maintained, because a push and a pop per call is cheap.

```php
final class OrderService implements OrderServiceInterface
{
    public function placeOrder(Order $order): void
    {
        $proxy = AopContext::currentProxy();
        \assert($proxy instanceof OrderServiceInterface);
        $proxy->audit($order); // advised, `$this->audit($order)` would not be
    }
}
```

With the subclass strategy `$this` already is the proxy, so you do not need it there.

## Limitations

- Self-invocation with interface proxies is not advised.
  Inside the target `$this` is the plain object, so `$this->other()` bypasses the proxy.
  Call `AopContext::currentProxy()->other()` instead (see below), or use the subclass strategy (make the class non-final).
  `bin/console debug:aop` lists the `$this->advisedMethod()` calls it finds in interface-proxied classes.
- `new self` inside a subclass-proxied target creates a plain, unadvised target.
  `new static` creates another proxy instance, which is advised.
- Methods returning by reference are never advised; by-reference parameters are advised.
  `getArgs()` and `args()` bindings hand advice the values, so changing them in advice does not reach the caller; `proceed($args)` does.
- Private, static, final (subclass strategy) and magic methods are never advised.
- A proxy cannot be unserialised in a process that has not loaded its class.
  With the bundle the proxy autoloader is registered at boot, so `unserialize()` works after the kernel booted; without it, call `ProxyClassWriter::load()` or include the file first.
- When an interface declares a return type naming a parent class of the target, the interface proxy repeats that type and returns the raw target result instead of a proxy, because the proxy is not an instance of that parent.
- `#[Around]` advice returning a value incompatible with the target's declared return type throws an `AdviceReturnException`.
- Constructor calls are never advised, and neither are calls made before any instance of the proxy class has received the runtime.
