<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Pointcut;

use Phauthentic\AOP\Pointcut\Pattern\ModifierPattern;
use Phauthentic\AOP\Pointcut\Pattern\NamePattern;
use Phauthentic\AOP\Pointcut\Pattern\ParameterPattern;
use Phauthentic\AOP\Pointcut\Pattern\TypePattern;

final class ExecutionPointcut implements PointcutInterface
{
    /**
     * @param TypePattern|null $returnType    null = any (including no declared return type)
     * @param TypePattern|null $declaringType null = any
     */
    public function __construct(
        private readonly ModifierPattern $modifier,
        private readonly ?TypePattern $returnType,
        private readonly ?TypePattern $declaringType,
        private readonly NamePattern $name,
        private readonly ParameterPattern $params,
    ) {
    }

    public function matchesClass(\ReflectionClass $class, StaticMatchContext $ctx): bool
    {
        if (null === $this->declaringType) {
            return true;
        }
        foreach (self::hierarchy($class) as $type) {
            if ($this->declaringType->matchesType($type)) {
                return true;
            }
        }

        return false;
    }

    public function matchesMethod(\ReflectionMethod $method, StaticMatchContext $ctx): bool
    {
        return $this->modifier->matches($method)
            && $this->name->matches($method->getName())
            && $this->params->matches($method)
            && (null === $this->returnType || $this->returnType->matchesDeclared($method->getReturnType()))
            && $this->declaringTypeMatches($ctx->targetClass, $method->getName());
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

    /**
     * The target class itself, or any ancestor/interface that declares the method.
     *
     * @param \ReflectionClass<object> $target
     */
    private function declaringTypeMatches(\ReflectionClass $target, string $method): bool
    {
        if (null === $this->declaringType || $this->declaringType->matchesType($target)) {
            return true;
        }
        foreach (self::hierarchy($target) as $type) {
            if ($type->hasMethod($method)
                && $type->getMethod($method)->getDeclaringClass()->getName() === $type->getName()
                && $this->declaringType->matchesType($type)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param \ReflectionClass<object> $class
     *
     * @return list<\ReflectionClass<object>> the class, its parents and all interfaces
     */
    private static function hierarchy(\ReflectionClass $class): array
    {
        $types = [$class];
        for ($parent = $class->getParentClass(); false !== $parent; $parent = $parent->getParentClass()) {
            $types[] = $parent;
        }
        foreach ($class->getInterfaces() as $interface) {
            $types[] = $interface;
        }

        return $types;
    }
}
