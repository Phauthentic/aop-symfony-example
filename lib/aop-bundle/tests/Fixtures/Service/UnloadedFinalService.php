<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Symfony\Tests\Fixtures\Service;

/**
 * Referenced by name only, so it is not loaded before ProxyWeavingPassTest weaves it with strip_final.
 */
final class UnloadedFinalService
{
    final public function run(): string
    {
        return 'run';
    }
}
