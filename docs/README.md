# Documentation

Developer documentation for `phauthentic/aop` (the framework-free core, namespace `Phauthentic\AOP\`, in `lib/aop`) and `phauthentic/aop-bundle` (the Symfony integration, namespace `Phauthentic\AOP\Symfony\`, in `lib/aop-bundle`).

## Reading order

Read top to bottom if you are new to the library.
Jump straight to a page if you know what you are looking for.

| # | Page | Read it when |
|---|---|---|
| 1 | [Concepts](concepts.md) | You want the vocabulary (join point, pointcut, advice, aspect, advisor, proxy, weaving) and how a call flows through a proxy. |
| 2 | [Pointcut expressions](pointcuts.md) | You write or debug an expression and need the grammar, every designator, and the pattern rules. |
| 3 | [Advice](advice.md) | You write an aspect and need the five advice types, parameter binding, and execution order. |
| 4 | [Proxies](proxies.md) | You wonder why a method is not advised, which strategy a class gets, or what the generated code looks like. |
| 5 | [Symfony bundle](symfony.md) | You install, configure, or troubleshoot the bundle, or want to know how weaving fits into the container build. |
| 6 | [Standalone usage](standalone.md) | You use the core without Symfony through `ProxyFactory`. |
| 7 | [Examples](examples.md) | You want complete, copy-pasteable aspects: logging, validation, retry, caching, interface-proxied final class. |
| 8 | [Architecture](architecture.md) | You contribute to the library and need the package layout, class responsibilities, extension points, and the test setup. |

## Quick commands

```bash
bin/console app:aop-demo   # runs the demo scenarios in src/ and prints the advice trace
bin/console debug:aop      # lists advised services, methods, advisors and skipped services
composer check             # php-cs-fixer, phpstan (level max), phpunit
```

## Conventions in these pages

Every code example is taken from, or checked against, the code in `lib/` and `src/`.
Where the documentation and the code disagree, the code wins; please open an issue or fix the page.
