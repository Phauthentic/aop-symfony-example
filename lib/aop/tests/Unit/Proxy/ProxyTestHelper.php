<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Unit\Proxy;

use Phauthentic\AOP\Advisor\AdvisorChainFactory;
use Phauthentic\AOP\Advisor\AdvisorRegistry;
use Phauthentic\AOP\Aspect\ArrayAspectInstanceResolver;
use Phauthentic\AOP\Binding\ParameterBinder;
use Phauthentic\AOP\Runtime\AopRuntime;

/**
 * Shared assertions helpers for generated proxy tests.
 */
final class ProxyTestHelper
{
    public static function emptyRuntime(): AopRuntime
    {
        return new AopRuntime(new AdvisorChainFactory(new AdvisorRegistry([]), new ArrayAspectInstanceResolver([]), new ParameterBinder()));
    }

    /**
     * Comparable description of a method signature; `self`/`parent` resolved against the declaring class.
     *
     * @return array<string, mixed>
     */
    public static function signature(\ReflectionMethod $method): array
    {
        $params = [];
        foreach ($method->getParameters() as $p) {
            $params[] = [
                'name' => $p->getName(),
                'type' => self::type($p->getType(), $method),
                'optional' => $p->isOptional(),
                'variadic' => $p->isVariadic(),
                'byRef' => $p->isPassedByReference(),
                'default' => $p->isDefaultValueAvailable() ? $p->getDefaultValue() : '<none>',
                'attributes' => array_map(static fn (\ReflectionAttribute $a): string => $a->getName(), $p->getAttributes()),
            ];
        }

        return [
            'name' => $method->getName(),
            'public' => $method->isPublic(),
            'protected' => $method->isProtected(),
            'static' => $method->isStatic(),
            'return' => self::type($method->getReturnType(), $method),
            'params' => $params,
        ];
    }

    private static function type(?\ReflectionType $type, \ReflectionMethod $method): ?string
    {
        if (null === $type) {
            return null;
        }
        $declaring = $method->getDeclaringClass();
        $parent = $declaring->getParentClass();

        return (string) preg_replace_callback(
            '/(?<![\w\\\\])(self|parent)(?![\w\\\\])/i',
            static fn (array $m): string => 'self' === strtolower($m[1]) ? $declaring->getName() : (false === $parent ? 'parent' : $parent->getName()),
            (string) $type,
        );
    }
}
