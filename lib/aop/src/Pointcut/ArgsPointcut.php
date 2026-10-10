<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Pointcut;

use Phauthentic\AOP\Pointcut\Expression\Ast\ParamListNode;

/**
 * TODO C4: placeholder, replace with the real implementation.
 */
final class ArgsPointcut implements PointcutInterface
{
    /**
     * @param list<string> $bindingNames names of the `BindingCandidateNode`s in $params that are advice parameters
     */
    public function __construct(public readonly ParamListNode $params, public readonly array $bindingNames)
    {
    }

    public function matchesClass(\ReflectionClass $class, StaticMatchContext $ctx): bool
    {
        return true; // TODO C4
    }

    public function matchesMethod(\ReflectionMethod $method, StaticMatchContext $ctx): bool
    {
        return true; // TODO C4
    }

    public function isRuntime(): bool
    {
        return true; // TODO C4
    }

    public function matchesRuntime(RuntimeMatchContext $ctx): bool
    {
        return true; // TODO C4
    }

    public function bindings(): array
    {
        return []; // TODO C4
    }
}
