# symfony-aop demo

Demo Symfony application for the `phauthentic/aop` library and the `phauthentic/symfony-aop` bundle.

## What it shows

- `bin/console app:aop-demo` runs the services in `src/Service` and prints what the aspects did.
- `bin/console debug:aop` lists every woven aspect, advice and the methods they apply to.
- `src/Aspect` holds the aspects: logging, caching, retry, validation and the audit introduction.
- `src/Service` holds the advised services.
- The `Auditable` introduction (`src/Contract/Auditable.php`) adds an interface and its implementation to the advised service at compile time.

## Install and run

Both packages are public GitHub repositories without Packagist releases, so they are pulled from the VCS repositories declared in `composer.json`.
PHP 8.3 or later and Composer 2 are required.

```bash
composer install
bin/console cache:clear
bin/console debug:aop
bin/console app:aop-demo
composer check   # php-cs-fixer, phpstan and the E2E tests
```

## Related repositories

- [Phauthentic/aop](https://github.com/Phauthentic/aop) is the framework-free library. The documentation index is `docs/README.md`.
- [Phauthentic/aop-symfony](https://github.com/Phauthentic/aop-symfony) is the Symfony bundle.
