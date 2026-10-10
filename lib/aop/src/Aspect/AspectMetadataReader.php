<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Aspect;

use Phauthentic\AOP\Advice\AdviceDefinition;
use Phauthentic\AOP\Advice\AdviceType;
use Phauthentic\AOP\Attribute\After;
use Phauthentic\AOP\Attribute\AfterReturning;
use Phauthentic\AOP\Attribute\AfterThrowing;
use Phauthentic\AOP\Attribute\Around;
use Phauthentic\AOP\Attribute\Aspect;
use Phauthentic\AOP\Attribute\Before;
use Phauthentic\AOP\Attribute\Order;
use Phauthentic\AOP\Attribute\Pointcut;
use Phauthentic\AOP\Exception\AspectDefinitionException;

final class AspectMetadataReader
{
    /**
     * @param class-string|\ReflectionClass<covariant object> $class
     */
    public function read(string|\ReflectionClass $class): AspectMetadata
    {
        $rc = $class instanceof \ReflectionClass ? $class : new \ReflectionClass($class);
        $name = $rc->getName();

        if ([] === $rc->getAttributes(Aspect::class)) {
            throw new AspectDefinitionException(sprintf('Class "%s" is not an aspect: missing #[Aspect] attribute.', $name));
        }

        $order = ($rc->getAttributes(Order::class)[0] ?? null)?->newInstance()->value ?? PHP_INT_MAX;
        $pointcuts = [];
        $advices = [];

        foreach ($rc->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() !== $name) {
                continue;
            }

            $where = sprintf('%s::%s()', $name, $method->getName());
            /** @var list<array{AdviceType, Before|After|Around|AfterReturning|AfterThrowing}> $found */
            $found = [];
            foreach (AdviceType::cases() as $type) {
                foreach ($method->getAttributes($type->attributeClass()) as $attr) {
                    /** @var Before|After|Around|AfterReturning|AfterThrowing $inst */
                    $inst = $attr->newInstance();
                    $found[] = [$type, $inst];
                }
            }
            $pointcutAttrs = $method->getAttributes(Pointcut::class);

            if ([] === $found && [] === $pointcutAttrs) {
                continue;
            }
            if (count($found) + count($pointcutAttrs) > 1) {
                throw new AspectDefinitionException(sprintf('%s carries more than one advice/pointcut attribute.', $where));
            }
            if (!$method->isPublic() || $method->isStatic()) {
                throw new AspectDefinitionException(sprintf('%s must be a public, non-static method to carry advice or pointcut attributes (found private/protected or static).', $where));
            }

            if ([] !== $pointcutAttrs) {
                $returnType = $method->getReturnType();
                if (!$returnType instanceof \ReflectionNamedType || 'void' !== $returnType->getName()) {
                    throw new AspectDefinitionException(sprintf('Pointcut method %s must return void.', $where));
                }
                if ($method->getNumberOfParameters() > 0) {
                    throw new AspectDefinitionException(sprintf('Pointcut method %s must not declare parameters.', $where));
                }
                $pointcuts[$method->getName()] = $pointcutAttrs[0]->newInstance()->expression;
                continue;
            }

            [$type, $instance] = $found[0];
            $advices[] = new AdviceDefinition(
                $name,
                $method->getName(),
                $type,
                $instance->pointcut,
                $instance instanceof AfterReturning ? $instance->returning : null,
                $instance instanceof AfterThrowing ? $instance->throwing : null,
                count($advices),
            );
        }

        return new AspectMetadata($name, $order, $pointcuts, $advices);
    }
}
