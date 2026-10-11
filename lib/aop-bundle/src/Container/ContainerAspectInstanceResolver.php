<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Symfony\Container;

use Phauthentic\AOP\Aspect\AspectInstanceResolverInterface;
use Phauthentic\AOP\Exception\AspectDefinitionException;
use Psr\Container\ContainerInterface;

/**
 * Resolves aspects lazily from a service locator keyed by aspect class, so aspects stay private and
 * aspect ↔ advised service dependency cycles are broken.
 */
final class ContainerAspectInstanceResolver implements AspectInstanceResolverInterface
{
    /**
     * @var array<class-string, \WeakMap<object, object>> pertarget/perthis aspects live exactly as long as their scope object
     */
    private array $scoped = [];

    public function __construct(private readonly ContainerInterface $locator)
    {
    }

    public function get(string $aspectClass, ?object $scope = null): object
    {
        if (null === $scope) {
            return $this->fetch($aspectClass);
        }

        /** @var \WeakMap<object, object> $map */
        $map = $this->scoped[$aspectClass] ?? new \WeakMap();
        $this->scoped[$aspectClass] = $map;

        // The collector pass makes scoped aspects non-shared, so every fetch is a fresh instance.
        return $map[$scope] ??= $this->fetch($aspectClass);
    }

    /**
     * @param class-string $aspectClass
     */
    private function fetch(string $aspectClass): object
    {
        $aspect = $this->locator->get($aspectClass);
        if (!$aspect instanceof $aspectClass) {
            throw new AspectDefinitionException(sprintf('Service for aspect "%s" is of type "%s".', $aspectClass, get_debug_type($aspect)));
        }

        return $aspect;
    }
}
