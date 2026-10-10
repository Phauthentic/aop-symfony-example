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
    public function __construct(private readonly ContainerInterface $locator)
    {
    }

    public function get(string $aspectClass): object
    {
        $aspect = $this->locator->get($aspectClass);
        if (!$aspect instanceof $aspectClass) {
            throw new AspectDefinitionException(sprintf('Service for aspect "%s" is of type "%s".', $aspectClass, get_debug_type($aspect)));
        }

        return $aspect;
    }
}
