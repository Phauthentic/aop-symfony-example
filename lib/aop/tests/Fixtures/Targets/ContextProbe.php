<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Fixtures\Targets;

use Phauthentic\AOP\Runtime\AopContext;

/**
 * Records AopContext::currentProxy() before and after an optional nested call and optionally throws.
 */
final class ContextProbe implements ContextProbeInterface
{
    /** @var list<object|null> */
    public static array $seen = [];

    public function __construct(private readonly ?ContextProbeInterface $nested = null, private readonly bool $throw = false)
    {
    }

    public function probe(): void
    {
        self::$seen[] = AopContext::currentProxy();
        $this->nested?->probe();
        self::$seen[] = AopContext::currentProxy();
        if ($this->throw) {
            throw new \LogicException('boom');
        }
    }
}
