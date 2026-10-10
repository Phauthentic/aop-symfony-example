<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Proxy;

use Phauthentic\AOP\Attribute\Proxyable;
use Phauthentic\AOP\Exception\ProxyGenerationException;

/**
 * `#[Proxyable]` wins over the configured strategy; `null` configured means auto:
 * non-final → SUBCLASS, final with interfaces → INTERFACE, final without interfaces → null (not proxyable).
 */
final class ProxyStrategyResolver
{
    /**
     * @param \ReflectionClass<covariant object> $target
     */
    public function resolve(\ReflectionClass $target, ?ProxyStrategy $configured): ?ProxyStrategy
    {
        $requested = ($target->getAttributes(Proxyable::class)[0] ?? null)?->newInstance()->strategy ?? $configured;
        $hasInterfaces = [] !== $target->getInterfaceNames();

        if (null === $requested) {
            return !$target->isFinal() ? ProxyStrategy::SUBCLASS : ($hasInterfaces ? ProxyStrategy::INTERFACE : null);
        }
        if (ProxyStrategy::SUBCLASS === $requested && $target->isFinal()) {
            throw new ProxyGenerationException(sprintf('Cannot create a subclass proxy for final class "%s"; use the interface strategy or remove "final".', $target->getName()));
        }
        if (ProxyStrategy::INTERFACE === $requested && !$hasInterfaces) {
            throw new ProxyGenerationException(sprintf('Cannot create an interface proxy for class "%s": it implements no interface.', $target->getName()));
        }

        return $requested;
    }
}
