<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Pointcut;

use Phauthentic\AOP\Pointcut\Pattern\NamePattern;

final class BeanPointcut implements PointcutInterface
{
    public function __construct(private readonly NamePattern $pattern)
    {
    }

    public function matchesClass(\ReflectionClass $class, StaticMatchContext $ctx): bool
    {
        return null !== $ctx->beanName && $this->pattern->matches($ctx->beanName);
    }

    public function matchesMethod(\ReflectionMethod $method, StaticMatchContext $ctx): bool
    {
        return $this->matchesClass($ctx->targetClass, $ctx);
    }

    public function isRuntime(): bool
    {
        return false;
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
