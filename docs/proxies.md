# Proxies

Advice runs because a generated proxy class stands in for the target.
This page covers the two strategies, how one is chosen, which classes and methods qualify, what the generated code looks like, and where the approach stops working.

## Two strategies

`Proxy\ProxyStrategy` has two cases.

| | `SUBCLASS` | `INTERFACE` |
|---|---|---|
| Spring equivalent | CGLIB proxy | JDK dynamic proxy |
| Generated class | `extends Target`, overrides the advised methods only | `implements` every interface of the target, delegates every interface method to a wrapped instance |
| Works for | Non-final classes | Any class that implements at least one interface, including final ones |
| Inject as | The class or any interface | The interface only; the proxy is **not** an instance of the target class |
| `$this` inside the target | The proxy, so self-invocation is advised | The target, so self-invocation is not advised |
| `getThis()` / `getTarget()` | Both the proxy | The proxy / the wrapped instance |
| Constructor | Runs once, untouched, when the proxy is constructed | Untouched; the proxy's own constructor takes the target instance |
| Protected methods | Advised | Never (not declared by an interface) |
| Final methods | Skipped | Advised if an interface declares them |
| Non-interface public methods | Still callable | Do not exist on the proxy |
| Target returns `$this` | The proxy (it is `$this`) | The proxy, via `__aopWrap()` |
| Target returns another instance of its class | That instance, advised only if it is itself a proxy | A new proxy around it sharing the same runtime, via `__aopWrap()` |
| Readonly target class | The proxy is readonly too | The proxy is readonly too |

## Auto-selection

`Proxy\ProxyStrategyResolver::resolve(\ReflectionClass $target, ?ProxyStrategy $configured): ?ProxyStrategy`:

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
| Has a by-reference parameter | `parameter $x is passed by reference, which is not supported` |
| Has an object default value (`new` in initializer) | `parameter $x has an object default value (new in initializer) that cannot be reproduced` |
| Not declared by an interface, interface strategy | `interface proxies can only advise public methods declared by an interface of the target` |

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

Until `__aopSetRuntime()` has been called the proxy behaves exactly like the target: every advised method checks `isset($this->__aopRuntime)` and calls through directly if it is unset.
Calls made from inside the constructor are therefore never advised.

### Subclass proxy

Real output for `App\Service\StringService` from `var/cache/dev/phauthentic_aop/Phauthentic/AOP/Generated/App/Service/StringServiceProxy_aad667b31091.php`, trimmed to two methods:

```php
<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Generated\App\Service;

class StringServiceProxy_aad667b31091 extends \App\Service\StringService implements \Phauthentic\AOP\Proxy\AdvisedInterface
{
    private \Phauthentic\AOP\Runtime\AopRuntime $__aopRuntime;

    public function __aopSetRuntime(\Phauthentic\AOP\Runtime\AopRuntime $runtime): void
    {
        $this->__aopRuntime = $runtime;
    }

    public function __aopGetTarget(): object
    {
        return $this;
    }

    public function flaky(string $input): string
    {
        if (!isset($this->__aopRuntime)) {
            return parent::flaky($input);
        }
        return $this->__aopRuntime->invoke($this, $this, \App\Service\StringService::class, 'flaky', [$input], fn (array $args): mixed => parent::flaky(...$args));
    }

    public function greet(string $name, int $times = 1): string
    {
        if (!isset($this->__aopRuntime)) {
            return parent::greet($name, $times);
        }
        return $this->__aopRuntime->invoke($this, $this, \App\Service\StringService::class, 'greet', [$name, $times], fn (array $args): mixed => parent::greet(...$args));
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
    private \Phauthentic\AOP\Runtime\AopRuntime $__aopRuntime;

    public function __aopSetRuntime(\Phauthentic\AOP\Runtime\AopRuntime $runtime): void
    {
        $this->__aopRuntime = $runtime;
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
        $proxy = new static($result);
        if (isset($this->__aopRuntime)) {
            $proxy->__aopSetRuntime($this->__aopRuntime);
        }

        return $proxy;
    }

    public function placeOrder(string $sku, int $qty): string
    {
        if (!isset($this->__aopRuntime)) {
            return $this->__aopTarget->placeOrder($sku, $qty);
        }
        return $this->__aopRuntime->invoke($this, $this->__aopTarget, \App\Service\OrderService::class, 'placeOrder', [$sku, $qty], fn (array $args): mixed => $this->__aopTarget->placeOrder(...$args));
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

## Limitations

- Self-invocation with interface proxies is not advised.
  Inside the target `$this` is the plain object, so `$this->other()` bypasses the proxy.
  Use the subclass strategy (make the class non-final) if you need it.
- `new static` inside a subclass-proxied target creates another proxy instance, but without a runtime, so it is not advised.
  `new self` creates a plain target.
- By-reference parameters and return values disable advice for that method.
- Private, static, final (subclass strategy) and magic methods are never advised.
- A proxy cannot be unserialised in a process that has not loaded its class.
  With the bundle the proxy autoloader is registered at boot, so `unserialize()` works after the kernel booted; without it, call `ProxyClassWriter::load()` or include the file first.
- When an interface declares a return type naming a parent class of the target, the interface proxy repeats that type and returns the raw target result instead of a proxy, because the proxy is not an instance of that parent.
- `#[Around]` advice returning a value incompatible with the target's declared return type throws an `AdviceReturnException`.
- Constructor calls are never advised, and neither are calls made before `__aopSetRuntime()`.
