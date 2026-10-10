<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Pointcut;

use Phauthentic\AOP\Pointcut\Expression\Ast\BindingCandidateNode;
use Phauthentic\AOP\Pointcut\Expression\Ast\ParamListNode;
use Phauthentic\AOP\Pointcut\Expression\Ast\TypePatternNode;
use Phauthentic\AOP\Pointcut\Expression\Ast\WildcardParamNode;

/**
 * Shared by args()/@args(): the positional entry list and arity handling.
 *
 * @internal
 */
trait ArgumentList
{
    /**
     * @var list<TypePatternNode|BindingSpec|null> null = `*`
     */
    private array $entries = [];
    private bool $ellipsis = false;

    /**
     * @param array<string, BindingSpec> $bindings
     */
    private function initArguments(ParamListNode $params, array $bindings, BindingKind $kind): void
    {
        $this->ellipsis = $params->trailingEllipsis;
        foreach ($params->params as $param) {
            $this->entries[] = match (true) {
                $param instanceof WildcardParamNode => null,
                $param instanceof BindingCandidateNode => $bindings[$param->name] ?? new BindingSpec($param->name, $kind, null),
                default => $param,
            };
        }
    }

    public function matchesClass(\ReflectionClass $class, StaticMatchContext $ctx): bool
    {
        return true;
    }

    public function isRuntime(): bool
    {
        foreach ($this->entries as $entry) {
            if (null !== $entry) {
                return true;
            }
        }

        return false;
    }

    public function bindings(): array
    {
        $bindings = [];
        foreach ($this->entries as $entry) {
            if ($entry instanceof BindingSpec) {
                $bindings[$entry->name] = $entry;
            }
        }

        return $bindings;
    }

    /**
     * Can a call to $method possibly be made with an argument count this list accepts?
     */
    private function arityFeasible(\ReflectionMethod $method): bool
    {
        $wanted = count($this->entries);
        $max = $method->isVariadic() ? PHP_INT_MAX : $method->getNumberOfParameters();
        $min = $method->getNumberOfRequiredParameters();

        return $this->ellipsis ? $max >= $wanted : ($min <= $wanted && $wanted <= $max);
    }

    /**
     * @param array<int|string, mixed> $args
     */
    private function runtimeArityMatches(array $args): bool
    {
        return $this->ellipsis ? count($args) >= count($this->entries) : count($args) === count($this->entries);
    }
}
