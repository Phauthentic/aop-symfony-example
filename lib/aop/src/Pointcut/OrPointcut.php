<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Pointcut;

final class OrPointcut implements PointcutInterface
{
    public function __construct(private readonly PointcutInterface $left, private readonly PointcutInterface $right)
    {
    }

    public function matchesClass(\ReflectionClass $class, StaticMatchContext $ctx): bool
    {
        return $this->left->matchesClass($class, $ctx) || $this->right->matchesClass($class, $ctx);
    }

    public function matchesMethod(\ReflectionMethod $method, StaticMatchContext $ctx): bool
    {
        return $this->left->matchesMethod($method, $ctx) || $this->right->matchesMethod($method, $ctx);
    }

    public function isRuntime(): bool
    {
        return $this->left->isRuntime() || $this->right->isRuntime();
    }

    public function matchesRuntime(RuntimeMatchContext $ctx): bool
    {
        // A branch whose static part failed must not be rescued by its runtime part, so re-check statics.
        return RuntimeEvaluation::matches($this->left, $ctx) || RuntimeEvaluation::matches($this->right, $ctx);
    }

    public function bindings(): array
    {
        return [...$this->left->bindings(), ...$this->right->bindings()];
    }
}
