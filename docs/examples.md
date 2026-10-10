# Examples

Every example on this page is in the demo application under `src/` and runs with `bin/console app:aop-demo`.
The examples share two things: a `Trace` collector so the output shows what ran, and the `StringService` / `OrderService` targets.

## Shared pieces

`src/Demo/Trace.php`, a plain service that collects lines:

```php
<?php

declare(strict_types=1);

namespace App\Demo;

final class Trace
{
    /** @var list<string> */
    public array $lines = [];

    public function add(string $line): void
    {
        $this->lines[] = $line;
    }
}
```

`src/Service/StringService.php`, a non-final class, so it gets a subclass proxy:

```php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Attribute\Retry;

class StringService implements StringServiceInterface
{
    private int $flakyCalls = 0;

    public function reverse(string $input): string
    {
        return strrev($input);
    }

    public function upper(string $input): string
    {
        return strtoupper($input);
    }

    public function greet(string $name, int $times = 1): string
    {
        return implode(' ', array_fill(0, $times, sprintf('Hello, %s!', $name)));
    }

    #[Retry(3)]
    public function flaky(string $input): string
    {
        if (++$this->flakyCalls <= 2) {
            throw new \RuntimeException('flaky failure '.$this->flakyCalls);
        }

        return $input;
    }
}
```

`StringServiceInterface` declares the same four methods.

`src/Aspect/CommonPointcuts.php`, a named pointcut shared by the aspects:

```php
<?php

declare(strict_types=1);

namespace App\Aspect;

use Phauthentic\AOP\Attribute\Aspect;
use Phauthentic\AOP\Attribute\Pointcut;

#[Aspect]
final class CommonPointcuts
{
    #[Pointcut('execution(* App\Service\**::*(..))')]
    public function serviceLayer(): void
    {
    }
}
```

Configuration, `config/packages/phauthentic_aop.yaml`:

```yaml
phauthentic_aop:
    include: ['App\**']
```

## 1. Logging with Before and After

`src/Aspect/LoggingAspect.php`.
`#[Order(1)]` makes it the outermost aspect, so its lines frame everything else.

```php
<?php

declare(strict_types=1);

namespace App\Aspect;

use App\Demo\Trace;
use Phauthentic\AOP\Attribute\After;
use Phauthentic\AOP\Attribute\Aspect;
use Phauthentic\AOP\Attribute\Before;
use Phauthentic\AOP\Attribute\Order;
use Phauthentic\AOP\JoinPoint\JoinPointInterface;

#[Aspect]
#[Order(1)]
final class LoggingAspect
{
    public function __construct(private readonly Trace $trace)
    {
    }

    #[Before('App\Aspect\CommonPointcuts::serviceLayer()')]
    public function before(JoinPointInterface $jp): void
    {
        $this->trace->add('log before '.$jp->toShortString());
    }

    #[After('App\Aspect\CommonPointcuts::serviceLayer()')]
    public function after(JoinPointInterface $jp): void
    {
        $this->trace->add('log after '.$jp->toShortString());
    }
}
```

`toShortString()` yields `StringService::reverse(..)`: the short name of the declaring class, the method, and a literal `(..)`.
Use `$jp->getSignature()->toLongString()` for the FQCN and parameter types, and `$jp->getArgs()` for the values.

## 2. Validation with args binding

`src/Aspect/ValidationAspect.php`.
The pointcut binds the first argument of `greet()` to `$name`; the advice needs no join point at all.

```php
<?php

declare(strict_types=1);

namespace App\Aspect;

use Phauthentic\AOP\Attribute\Aspect;
use Phauthentic\AOP\Attribute\Before;
use Phauthentic\AOP\Attribute\Order;

#[Aspect]
#[Order(2)]
final class ValidationAspect
{
    #[Before('execution(* App\Service\StringService::greet(..)) && args(name, ..)')]
    public function before(string $name): void
    {
        if ('' === $name) {
            throw new \InvalidArgumentException('Name must not be empty');
        }
    }
}
```

`args(name, ..)` rather than `args(name)`: the proxy forwards the optional `$times` with its default, so the call always has two arguments.
The `string` type of `$name` is also a runtime check; a non-string first argument would skip this advice rather than bind it.
Throwing from `#[Before]` prevents the target from running; the exception passes through `LoggingAspect::after()` on the way out, which is why the trace below shows `log after` for the failed call.

## 3. Retry with a custom attribute

`src/Attribute/Retry.php`:

```php
<?php

declare(strict_types=1);

namespace App\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD)]
final class Retry
{
    public function __construct(public readonly int $attempts = 3)
    {
    }
}
```

`src/Aspect/RetryAspect.php`.
`@annotation(retry)` with a parameter `Retry $retry` matches methods carrying `#[Retry]` and hands the attribute instance to the advice.

```php
<?php

declare(strict_types=1);

namespace App\Aspect;

use App\Attribute\Retry;
use App\Demo\Trace;
use Phauthentic\AOP\Attribute\Around;
use Phauthentic\AOP\Attribute\Aspect;
use Phauthentic\AOP\Attribute\Order;
use Phauthentic\AOP\JoinPoint\ProceedingJoinPointInterface;

#[Aspect]
#[Order(3)]
final class RetryAspect
{
    public function __construct(private readonly Trace $trace)
    {
    }

    #[Around('@annotation(retry)')]
    public function around(ProceedingJoinPointInterface $pjp, Retry $retry): mixed
    {
        for ($attempt = 1;; ++$attempt) {
            $this->trace->add(sprintf('retry attempt %d/%d', $attempt, $retry->attempts));
            try {
                return $pjp->proceed();
            } catch (\Throwable $e) {
                $this->trace->add(sprintf('retry attempt %d failed: %s', $attempt, $e->getMessage()));
                if ($attempt >= $retry->attempts) {
                    throw $e;
                }
            }
        }
    }
}
```

Each `proceed()` runs the rest of the chain again, inner advice included.
The expression contains no `execution()` part, so it applies to any method with `#[Retry]` in any included class.

## 4. Caching with Around and ordering

`src/Aspect/CachingAspect.php`.
`#[Order(10)]` puts it innermost, so a cache hit still produces the logging lines but skips the target.

```php
<?php

declare(strict_types=1);

namespace App\Aspect;

use App\Demo\Trace;
use Phauthentic\AOP\Attribute\Around;
use Phauthentic\AOP\Attribute\Aspect;
use Phauthentic\AOP\Attribute\Order;
use Phauthentic\AOP\JoinPoint\ProceedingJoinPointInterface;

#[Aspect]
#[Order(10)]
final class CachingAspect
{
    /** @var array<string, mixed> */
    private array $cache = [];

    public function __construct(private readonly Trace $trace)
    {
    }

    #[Around('execution(* App\Service\StringService::upper(..))')]
    public function around(ProceedingJoinPointInterface $pjp): mixed
    {
        $label = sprintf('%s(%s)', $pjp->getSignature()->methodName, implode(',', array_filter($pjp->getArgs(), is_string(...))));
        $key = $pjp->getSignature()->toLongString().serialize($pjp->getArgs());
        if (\array_key_exists($key, $this->cache)) {
            $this->trace->add('cache hit '.$label);

            return $this->cache[$key];
        }
        $this->trace->add('cache miss '.$label);

        return $this->cache[$key] = $pjp->proceed();
    }
}
```

Were the order `1` instead of `10`, a cache hit would return before `LoggingAspect::before()` ran and no `log before` line would appear.

## 5. Interface-proxied final class

`src/Service/OrderService.php` is final, so the auto strategy generates an interface proxy.

```php
<?php

declare(strict_types=1);

namespace App\Service;

final class OrderService implements OrderServiceInterface
{
    public function placeOrder(string $sku, int $qty): string
    {
        return sprintf('Order %s x%d placed', $sku, $qty);
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Service;

interface OrderServiceInterface
{
    public function placeOrder(string $sku, int $qty): string;
}
```

Consumers must inject `OrderServiceInterface`, as `AopDemoCommand` does:

```php
public function __construct(
    private readonly StringServiceInterface $strings,
    private readonly OrderServiceInterface $orders,
    private readonly Trace $trace,
) {
    parent::__construct();
}
```

A constructor typed `OrderService $orders` would receive the proxy, which is not an `OrderService`, and fail with a `TypeError`.
The container alias `OrderServiceInterface` points at the woven `App\Service\OrderService` definition, which now holds the proxy; the original is moved to `App\Service\OrderService.aop_target`.
Inside `OrderService`, `$this->placeOrder()` would bypass advice; `StringService`, being non-final, has no such restriction.

## Running it

`src/Command/AopDemoCommand.php` calls the services and prints the trace:

```
$ bin/console app:aop-demo
reverse('hello') = olleh
upper('hello') = HELLO
upper('hello') = HELLO
greet('World', 2) = Hello, World! Hello, World!
greet('') failed: InvalidArgumentException: Name must not be empty
flaky('x') = x
placeOrder('SKU-1', 2) = Order SKU-1 x2 placed
Trace:
log before StringService::reverse(..)
log after StringService::reverse(..)
log before StringService::upper(..)
cache miss upper(hello)
log after StringService::upper(..)
log before StringService::upper(..)
cache hit upper(hello)
log after StringService::upper(..)
log before StringService::greet(..)
log after StringService::greet(..)
log before StringService::greet(..)
log after StringService::greet(..)
log before StringService::flaky(..)
retry attempt 1/3
retry attempt 1 failed: flaky failure 1
retry attempt 2/3
retry attempt 2 failed: flaky failure 2
retry attempt 3/3
log after StringService::flaky(..)
log before OrderService::placeOrder(..)
log after OrderService::placeOrder(..)
```

Reading the trace against the orders:

- `reverse`: only `LoggingAspect` (order 1) matches.
- `upper`: logging wraps `CachingAspect` (order 10); the second call is a hit and never reaches the target.
- `greet('')`: logging's `before` runs, then `ValidationAspect` (order 2) throws, then logging's `after` runs because `#[After]` behaves like `finally`.
- `flaky`: logging wraps `RetryAspect` (order 3), which calls `proceed()` three times.
- `placeOrder`: the final class is advised through its interface proxy.

`tests/E2E/AopDemoCommandTest.php` asserts this exact output.
