# symfony-aop

Aspect-oriented programming for PHP 8.2+ with a Symfony bundle.
Pointcut expressions, advice types and ordering follow Spring AOP, adapted to PHP.

- `lib/aop` is the framework-free core: pointcut language, advice, proxy generation.
- `lib/aop-bundle` is the Symfony bundle: aspect discovery, compile-time weaving, `debug:aop`.
- `src/` is a demo application.

## Install

```bash
composer require phauthentic/aop phauthentic/aop-bundle
```

Register `Phauthentic\AOP\Symfony\PhauthenticAopBundle` in `config/bundles.php` and add `config/packages/phauthentic_aop.yaml`:

```yaml
phauthentic_aop:
    include: ['App\**']
    # strip_final: false   # opt-in and risky: subclass proxies for final classes, see docs/symfony.md
```

## Quick start

```php
use Phauthentic\AOP\Attribute\{Aspect, Before};
use Phauthentic\AOP\JoinPoint\JoinPointInterface;

#[Aspect]
final class LoggingAspect
{
    public function __construct(private readonly LoggerInterface $logger) {}

    #[Before('execution(* App\Service\**::*(..))')]
    public function before(JoinPointInterface $jp): void
    {
        $this->logger->info('calling '.$jp->toShortString());
    }
}
```

Services in `App\Service` are now woven.
No further configuration is needed.

```bash
bin/console debug:aop      # what is advised, and by whom
bin/console app:aop-demo   # runs the demo scenarios
```

## Spring to PHP

| Spring | symfony-aop |
|---|---|
| `@Aspect` | `#[Aspect]` |
| `@Order` | `#[Order(int)]`, lower runs outermost |
| `@Pointcut` | `#[Pointcut('...')]` on a method, referenced as `Class::name()` |
| `@Before`, `@After`, `@Around` | `#[Before]`, `#[After]`, `#[Around]` |
| `@AfterReturning(returning=)` | `#[AfterReturning(pointcut, returning:)]` |
| `@AfterThrowing(throwing=)` | `#[AfterThrowing(pointcut, throwing:)]` |
| `JoinPoint`, `ProceedingJoinPoint` | `JoinPointInterface`, `ProceedingJoinPointInterface` |
| `execution(* com.x..*.*(..))` | `execution(* App\X\**::*(..))` |
| `within`, `this`, `target`, `args`, `bean` | same |
| `@annotation`, `@within`, `@target`, `@args` | same, matching PHP attributes |
| `argNames` | not needed, PHP reflection provides parameter names |
| `AopContext.currentProxy()` | `AopContext::currentProxy()` |
| JDK dynamic proxy | interface proxy |
| CGLIB proxy | subclass proxy |
| Introduction, `@DeclareParents` | `#[DeclareParents(typePattern, interface, defaultImpl)]` on the aspect class |
| `perthis`, `pertarget` | `#[Aspect(scope: AspectScope::PER_THIS)]`, `#[Aspect(scope: AspectScope::PER_TARGET)]` |

## Proxy strategies

- Subclass: a generated subclass overrides the advised methods.
  Used for non-final classes.
  Self-invocation is advised.
- Interface: a generated class implements the same interface and delegates to the target.
  Used for final classes that implement an interface.
  Inject the interface.
  Self-invocation is not advised.

Both are written to the kernel build directory (`var/cache/<env>/phauthentic_aop/` by default) and never touch `src/`.

## Limitations

- Only method execution is a join point.
- Interface proxies skip self-invocation; use `AopContext::currentProxy()`, `debug:aop` lists the affected calls.
- Methods returning by reference are not advised; methods with by-reference parameters are.
- Private, static, final (subclass strategy) and magic methods are not advised.

## Documentation

Start at the [documentation index](docs/README.md).

- [Concepts](docs/concepts.md): terminology, Spring mapping, invocation and weaving flows
- [Pointcut expressions](docs/pointcuts.md): grammar, designators, patterns, binding, errors
- [Advice](docs/advice.md): the five advice types, parameters, ordering
- [Proxies](docs/proxies.md): strategies, eligibility, generated code, limitations
- [Introductions](docs/introductions.md): `#[DeclareParents]`, introduced interfaces and their default implementations
- [Symfony bundle](docs/symfony.md): installation, configuration, weaving, `debug:aop`, troubleshooting
- [Standalone usage](docs/standalone.md): `ProxyFactory` without Symfony
- [Examples](docs/examples.md): logging, validation, retry, caching, interface-proxied final class
- [Architecture](docs/architecture.md): package layout, extension points, tests

## Development

```bash
composer check   # php-cs-fixer, phpstan max, phpunit
```
