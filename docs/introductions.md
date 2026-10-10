# Introductions

An introduction makes advised objects implement an interface they do not implement themselves, and supplies the implementation on their behalf.
Spring calls it `@DeclareParents`, AspectJ calls it an inter-type declaration; see the [Spring reference](https://docs.spring.io/spring-framework/reference/core/aop/ataspectj/introductions.html).
The typical use is a mixin: usage tracking, an audit trail, or a marker interface with behaviour, added to a whole layer without touching its classes.

## The attribute

```php
#[DeclareParents(typePattern: 'App\Service\*Service', interface: Auditable::class, defaultImpl: AuditTrail::class)]
```

`#[DeclareParents]` goes on an `#[Aspect]` class and is repeatable.

| Argument | Meaning |
|---|---|
| `typePattern` | A type pattern as in `within()`: `*` within a namespace segment, `**` for any number of segments, a trailing `+` for the type and all its subtypes. It is matched against the target class. |
| `interface` | The interface the proxy implements. |
| `defaultImpl` | A concrete class implementing `interface`, whose public constructor can be called without arguments. |

Spring derives the interface from the type of an annotated static field.
PHP has no field types on attributes, so the interface is an explicit argument.

The aspect is validated when its metadata is read (`AspectMetadataReader`), so a bundle build or `ProxyFactory::fromAspects()` fails fast.
An `AspectDefinitionException` naming the aspect class is thrown when the interface does not exist or is a class, when the default implementation does not implement the interface, or when it is abstract or needs constructor arguments.
When two introductions of the same interface apply to one target class, `AdvisorRegistry::introductionsFor()` throws an `AspectDefinitionException` naming both aspects.
A target that already implements the interface gets nothing introduced.

## Example

The demo application makes every service auditable.
The interface and its default implementation live in `src/Contract/`:

```php
namespace App\Contract;

interface Auditable
{
    public function recordAudit(string $entry): void;

    /** @return list<string> */
    public function auditTrail(): array;
}

final class AuditTrail implements Auditable
{
    /** @var list<string> */
    private array $entries = [];

    public function recordAudit(string $entry): void
    {
        $this->entries[] = $entry;
    }

    public function auditTrail(): array
    {
        return $this->entries;
    }
}
```

The aspect introduces the interface and, like Spring's usage tracking example, uses it from advice through a `this()` binding:

```php
namespace App\Aspect;

#[Aspect]
#[Order(0)]
#[DeclareParents('App\Service\*Service', Auditable::class, AuditTrail::class)]
final class AuditIntroductionAspect
{
    #[AfterReturning('App\Aspect\CommonPointcuts::serviceLayer() && this(auditable)')]
    public function record(JoinPointInterface $jp, Auditable $auditable): void
    {
        $auditable->recordAudit($jp->getSignature()->methodName);
    }
}
```

`this(auditable)` binds the proxy, which now is an `Auditable`.
Any code holding a service can use the introduced interface:

```php
if ($orders instanceof Auditable) {
    $orders->auditTrail(); // ['placeOrder']
}
```

`bin/console app:aop-demo` prints the audit trail of both demo services:

```
StringService audit trail: reverse, upper, upper, greet, flaky
OrderService audit trail: placeOrder
```

## How it works

Both proxy strategies support introductions (see [Proxies](proxies.md)).
The generated proxy class adds every introduced interface to its `implements` list.
For each method of an introduced interface that the target does not declare publicly, the proxy gets a method that delegates to the default implementation.
A public target method of the same name is used instead: the subclass proxy inherits it, the interface proxy forwards to the target.

Each proxy instance gets its own default implementation instance, created on the first call of an introduced method.
The instances live in a `WeakMap` keyed by the proxy, held in a static variable of the private method `__aopIntroduction()`, so readonly targets work too and the instance is released together with the proxy.

The proxy class name hash includes the introduced interfaces and their implementations, so changing an introduction produces a new proxy class.
A class matched by an introduction is proxied even when no advice applies to any of its methods.

## Pointcuts and introduced methods

Introduced methods are join points like target methods, so advice applies to them.

| Expression | Matches an introduced `Auditable` method? | Matches a target method of a class with `Auditable` introduced? |
|---|---|---|
| `execution(* App\Contract\Auditable::*(..))` | Yes | No |
| `execution(* App\Service\StringService::*(..))` | No: the target class does not declare it | Yes |
| `execution(* *(..))` | Yes | Yes |
| `this(App\Contract\Auditable)` | Yes | Yes: the proxy implements the interface |
| `target(App\Contract\Auditable)` | No: the target class does not implement it | No |
| `within(App\Contract\Auditable+)` | No: `within()` is about the target class | No |

`JoinPointInterface::getSignature()` of an introduced method describes the interface method, and `getTarget()` is the target, as for any other method.

## Symfony

The bundle applies introductions while weaving (`ProxyWeavingPass`), records them per service in the weaving report and lists them in the "Introduced interfaces" section of `bin/console debug:aop` (see [Symfony bundle](symfony.md#debugaop)).

The woven service implements the introduced interface, but the bundle registers no autowiring alias for it.
A constructor parameter typed with the introduced interface is therefore not autowired.
Inject the service by its id or its own type and check `instanceof`, or add an alias yourself, for example `App\Contract\Auditable: '@App\Service\OrderService'`.

## Standalone

`ProxyFactory::fromAspects()` reads `#[DeclareParents]` like the advice attributes, and `createProxyClass()`, `newInstance()` and `createProxy()` apply matching introductions (see [Standalone usage](standalone.md)).
To build the registry by hand, pass the result of `AdvisorFactory::introductionsFromMetadata()` as the second argument of `AdvisorRegistry`.

## Limitations

- The default implementation is created without constructor arguments; it cannot receive services.
  Give it setters and call them from advice if it needs collaborators.
- A lazy service woven with the subclass strategy is wrapped in a Symfony lazy proxy of the target class, which does not implement the introduced interface; the inner instance does.
- `clone` and `unserialize()` give the copy a new, empty default implementation instance on first use.
  A wither on an interface-proxied target returns a new proxy with its own default implementation instance.
- With the interface strategy, a public target method that only an introduced interface declares is forwarded to the target but not advised.
- When two introduced interfaces declare a method of the same name, the first interface in name order provides the implementation.
