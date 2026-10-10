# Standalone usage

`phauthentic/aop` has no framework dependency.
`Proxy\ProxyFactory` builds the advisor registry, the runtime and the proxies for you.

## ProxyFactory API

```php
final class ProxyFactory
{
    public function __construct(
        AdvisorRegistry $advisors,
        AspectInstanceResolverInterface $aspects,
        ?ProxyStrategy $strategy = null,   // null = auto
        ?string $cacheDir = null,          // null = eval() the generated code instead of writing files
    );

    /** @param list<object> $aspectInstances instances of #[Aspect] classes */
    public static function fromAspects(array $aspectInstances, ?ProxyStrategy $strategy = null, ?string $cacheDir = null): self;

    public function runtime(): AopRuntime;

    /** @return class-string the proxy class, or $class itself when nothing matches */
    public function createProxyClass(string $class, ?string $beanName = null): string;

    /** Subclass strategy: constructs an advised instance with the given constructor arguments. */
    public function newInstance(string $class, array $constructorArguments = [], ?string $beanName = null): object;

    /** Interface strategy: wraps an existing instance. Returns $target itself when nothing matches. */
    public function createProxy(object $target, ?string $beanName = null): object;
}
```

Which method to call depends on the strategy the class gets:

| Strategy | Use | Why |
|---|---|---|
| Subclass (non-final class) | `newInstance($class, $args)` or `new (createProxyClass($class))(...)` plus `__aopSetRuntime()` | The proxy must be constructed instead of the target. |
| Interface (final class with interface) | `createProxy($instance)` | The proxy wraps an instance you already built. |

Calling the wrong one raises `ProxyGenerationException` with a message pointing to the right one.
A final class without interfaces raises `ProxyGenerationException` from `createProxyClass()` when at least one advisor matches it.

`fromAspects()` reads each aspect's metadata, compiles all pointcuts (including cross-aspect named pointcut references) and keeps the instances in an `ArrayAspectInstanceResolver`.
Any `PointcutSyntaxException`, `BindingException` or `AspectDefinitionException` surfaces there, at construction time, before any proxy exists.

## Complete runnable example

Save as `standalone.php` next to a `vendor/` directory where `phauthentic/aop` is installed and run `php standalone.php`.

```php
<?php

declare(strict_types=1);

require __DIR__.'/vendor/autoload.php';

use Phauthentic\AOP\Attribute\Around;
use Phauthentic\AOP\Attribute\Aspect;
use Phauthentic\AOP\Attribute\Before;
use Phauthentic\AOP\JoinPoint\JoinPointInterface;
use Phauthentic\AOP\JoinPoint\ProceedingJoinPointInterface;
use Phauthentic\AOP\Proxy\ProxyFactory;

interface GreeterInterface
{
    public function greet(string $name): string;
}

class Greeter implements GreeterInterface
{
    public function __construct(private readonly string $prefix = 'Hello')
    {
    }

    public function greet(string $name): string
    {
        return sprintf('%s, %s!', $this->prefix, $name);
    }
}

final class FinalGreeter implements GreeterInterface
{
    public function greet(string $name): string
    {
        return 'Hi '.$name;
    }
}

#[Aspect]
final class TimingAspect
{
    #[Before('execution(* GreeterInterface+::greet(..)) && args(name)')]
    public function check(JoinPointInterface $jp, string $name): void
    {
        echo "before {$jp->toShortString()} with {$name}\n";
    }

    #[Around('execution(* *::greet(..))')]
    public function time(ProceedingJoinPointInterface $pjp): mixed
    {
        $start = hrtime(true);
        try {
            return $pjp->proceed();
        } finally {
            printf("%s took %d ns\n", $pjp->toShortString(), hrtime(true) - $start);
        }
    }
}

$factory = ProxyFactory::fromAspects([new TimingAspect()]);

// Non-final class: subclass proxy, constructed like the target.
$greeter = $factory->newInstance(Greeter::class, ['Hey']);
echo $greeter->greet('Ada'), "\n";          // $greeter instanceof Greeter === true

// Final class with an interface: interface proxy wrapping an existing instance.
$final = $factory->createProxy(new FinalGreeter());
echo $final->greet('Grace'), "\n";          // instanceof GreeterInterface, not instanceof FinalGreeter

// Manual construction from the proxy class name.
$class = $factory->createProxyClass(Greeter::class);
$manual = new $class('Yo');
$manual->__aopSetRuntime($factory->runtime());
echo $manual->greet('Linus'), "\n";
echo $class, "\n";
```

Output:

```
before Greeter::greet(..) with Ada
Greeter::greet(..) took 123031 ns
Hey, Ada!
before FinalGreeter::greet(..) with Grace
FinalGreeter::greet(..) took 25839 ns
Hi Grace
before Greeter::greet(..) with Linus
Greeter::greet(..) took 16372 ns
Yo, Linus!
Phauthentic\AOP\Generated\GreeterProxy_6ce072330f1d
```

The timings will differ; the hash in the class name will not, as long as the class, strategy and advised methods stay the same.

## Writing proxies to disk

Pass a cache directory to keep generated classes between requests:

```php
$factory = ProxyFactory::fromAspects([new TimingAspect()], cacheDir: __DIR__.'/var/aop');
```

`ProxyClassWriter` writes `<cacheDir>/Phauthentic/AOP/Generated/<Namespace>/<Class>Proxy_<hash>.php` atomically and `require`s it.
The file is rewritten on every `createProxyClass()` call for a class not yet in the factory's in-memory map, so the directory is a cache, not a build artefact you have to invalidate.
To load a previously written proxy in another process without the factory, `require` the file; the class declares itself and needs `__aopSetRuntime()` before it advises anything.

Without a cache directory the code is passed to `eval()`.

## Bean names

`createProxyClass($class, 'app.greeter')`, `newInstance($class, $args, 'app.greeter')` and `createProxy($instance, 'app.greeter')` register the name with the runtime so that `bean(app.greeter)` matches.
Each bean name gets its own proxy class.

## Lazy aspect instances

`ArrayAspectInstanceResolver` accepts closures as well as objects:

```php
use Phauthentic\AOP\Advisor\AdvisorRegistry;
use Phauthentic\AOP\Aspect\ArrayAspectInstanceResolver;

$resolver = new ArrayAspectInstanceResolver([
    TimingAspect::class => static fn (): TimingAspect => new TimingAspect(),
]);
```

Building the `AdvisorRegistry` by hand means reading metadata with `AspectMetadataReader`, resolving named pointcuts with `MetadataNamedPointcutResolver`, and compiling with `AdvisorFactory::fromMetadata()`; `ProxyFactory::fromAspects()` is that code, so copy it from `lib/aop/src/Proxy/ProxyFactory.php` when you need the resolver to be lazy.

## Integrating with another container

Implement `Aspect\AspectInstanceResolverInterface` to fetch aspects from your container, build an `AdvisorRegistry` as above, and construct `ProxyFactory` with both.
Then, wherever your container instantiates a service, call `createProxyClass()` (subclass) or `createProxy()` (interface) with the service id as bean name.
`lib/aop-bundle/src/Compiler/ProxyWeavingPass.php` is the reference implementation of this for Symfony.
