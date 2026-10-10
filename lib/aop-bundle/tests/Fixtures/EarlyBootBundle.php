<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Symfony\Tests\Fixtures;

use Phauthentic\AOP\Symfony\Tests\Fixtures\Service\LazyService;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Service\PlainService;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Registered before PhauthenticAopBundle: uses woven services before the AOP bundle boots, like a bundle whose boot()
 * instantiates an application logger processor.
 */
final class EarlyBootBundle extends Bundle
{
    /** @var array<string, string> */
    public static array $results = [];

    public function boot(): void
    {
        // The lazy service first: nothing has registered the proxy autoloader yet.
        $lazy = $this->container?->get('fixture.early_lazy');
        $plain = $this->container?->get('fixture.early');
        \assert($plain instanceof PlainService && $lazy instanceof LazyService);

        self::$results = ['ping' => $lazy->ping(), 'greet' => $plain->greet('early')];
    }
}
