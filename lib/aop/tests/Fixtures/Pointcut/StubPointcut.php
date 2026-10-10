<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Fixtures\Pointcut;

use Phauthentic\AOP\Pointcut\PointcutInterface;
use Phauthentic\AOP\Pointcut\RuntimeMatchContext;
use Phauthentic\AOP\Pointcut\StaticMatchContext;

final class StubPointcut implements PointcutInterface
{
    public function __construct(
        private readonly bool $class = true,
        private readonly bool $method = true,
        private readonly bool $runtime = false,
    ) {
    }

    public function matchesClass(\ReflectionClass $class, StaticMatchContext $ctx): bool
    {
        return $this->class;
    }

    public function matchesMethod(\ReflectionMethod $method, StaticMatchContext $ctx): bool
    {
        return $this->method;
    }

    public function isRuntime(): bool
    {
        return $this->runtime;
    }

    public function matchesRuntime(RuntimeMatchContext $ctx): bool
    {
        return true;
    }

    public function bindings(): array
    {
        return [];
    }
}
