# Symfony integration

## Install

```bash
composer require phauthentic/aop phauthentic/aop-bundle
```

Register `Phauthentic\AOP\Symfony\PhauthenticAopBundle` in `config/bundles.php`.

## Configuration

```yaml
phauthentic_aop:
    enabled: true
    proxy_strategy: auto                            # auto | subclass | interface
    proxy_dir: '%kernel.build_dir%/phauthentic_aop'
    include: ['App\**']                             # classes eligible for weaving
    exclude: []                                     # never woven
```

The AOP library, Symfony, Doctrine and all `#[Aspect]` classes are always excluded.

## How it works

1. Classes marked `#[Aspect]` are autoconfigured and tagged `phauthentic_aop.aspect` (requires `autoconfigure: true`).
2. `AspectCollectorPass` reads their attributes, validates every pointcut and binding, and fails the container build with the aspect class and method on error.
3. `ProxyWeavingPass` statically matches every eligible method of every included service against all advisors.
   For each service with at least one match, a proxy class is generated into `proxy_dir` and the service definition is switched to it.
4. At runtime the proxy calls `AopRuntime`, which builds the interceptor chain and runs it.
   Aspect instances are resolved lazily from a service locator, so aspects can depend on any service without cycles.

Proxies live in the build directory next to the dumped container, so `cache:clear` removes them and nothing is written to `src/`.
Every woven service includes the proxy directory's `autoload.php`, so proxy classes are loadable even when another bundle instantiates a woven service before this bundle boots.
A lazy woven service gets a lazy proxy of its target class (the `proxy` tag) instead of a lazy ghost, so the proxy class is only needed once the service is initialized.
Aspect and target classes are tracked as container resources, so in debug mode editing an aspect rebuilds the container.

## Debugging

```bash
bin/console debug:aop
```

Lists every advised service with strategy, methods and advisors, plus skipped services with the reason.

## Proxy strategies

| | `subclass` | `interface` |
|---|---|---|
| Target class | not final | final or not, must implement an interface |
| Injection type | class or interface | the interface only |
| `$this` in target | the proxy, self-invocation is advised | the target, self-invocation is not advised |
| Constructor | untouched, runs once | untouched |
| Target returns `$this` | the proxy | the proxy |
| Target returns another instance of its class (wither) | that instance, advised if it is a proxy | a new proxy around it |

`auto` uses `subclass` for non-final classes and `interface` for final classes that implement an interface.
Final classes without an interface cannot be proxied and are reported by `debug:aop`.
A tagged service is not interface-proxied when it has public methods that no interface declares, because tags such as `kernel.event_subscriber` or `controller.service_arguments` may reference them; `debug:aop` reports it as skipped.
Override per class with `#[Proxyable(ProxyStrategy::INTERFACE)]`.

## Without Symfony

```php
$factory = new ProxyFactory(/* advisors, aspect resolver, strategy, cache dir */);
$proxy = $factory->createProxy($serviceInstance);   // interface strategy
$class = $factory->createProxyClass(Service::class); // subclass strategy
```

## Limitations

- No introductions (`@DeclareParents`) and no `perthis`/`pertarget` aspects.
- Interface proxies do not advise self-invocation.
- By-reference parameters and return values are not advised.
- With the subclass strategy, `new static` inside a target returns an unadvised instance.
- Proxies cannot be unserialized in another process.
- Only method execution is a join point.
