<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Proxy;

/**
 * Decides which classes and methods a generated proxy can advise. A non-null return value is the human readable
 * reason for the exclusion (reported by `debug:aop`).
 */
final class MethodEligibility
{
    /**
     * @param \ReflectionClass<covariant object>|null $target the proxied class when it differs from the declaring class
     */
    public static function reasonForExclusion(\ReflectionMethod $method, ProxyStrategy $strategy, ?\ReflectionClass $target = null): ?string
    {
        $reason = match (true) {
            $method->isPrivate() => 'private methods cannot be overridden',
            $method->isStatic() => 'static methods are not called on an instance',
            $method->isAbstract() => 'abstract methods have no implementation to advise',
            str_starts_with($method->getName(), '__') => 'constructors, destructors and magic methods are never advised',
            $method->returnsReference() => 'methods returning by reference are not supported',
            ProxyStrategy::SUBCLASS === $strategy && $method->isFinal() => 'final methods cannot be overridden by a subclass proxy',
            default => null,
        };
        if (null !== $reason) {
            return $reason;
        }

        foreach ($method->getParameters() as $parameter) {
            if ($parameter->isDefaultValueAvailable() && !$parameter->isDefaultValueConstant() && is_object($parameter->getDefaultValue())) {
                return sprintf('parameter $%s has an object default value (new in initializer) that cannot be reproduced', $parameter->getName());
            }
        }

        if (ProxyStrategy::INTERFACE === $strategy && !self::declaredByInterface($method, $target ?? $method->getDeclaringClass())) {
            return 'interface proxies can only advise public methods declared by an interface of the target';
        }

        return null;
    }

    /**
     * @param \ReflectionClass<covariant object> $class
     */
    public static function isClassProxyable(\ReflectionClass $class): ?string
    {
        return match (true) {
            $class->isInterface() => 'interfaces cannot be proxied',
            $class->isTrait() => 'traits cannot be proxied',
            $class->isEnum() => 'enums cannot be proxied',
            $class->isAnonymous() => 'anonymous classes cannot be proxied',
            $class->isInternal() => 'internal classes cannot be proxied',
            $class->isAbstract() => 'abstract classes cannot be proxied',
            default => null,
        };
    }

    /**
     * @param \ReflectionClass<covariant object> $target
     */
    private static function declaredByInterface(\ReflectionMethod $method, \ReflectionClass $target): bool
    {
        if (!$method->isPublic()) {
            return false;
        }
        foreach ($target->getInterfaces() as $interface) {
            if ($interface->hasMethod($method->getName())) {
                return true;
            }
        }

        return false;
    }
}
