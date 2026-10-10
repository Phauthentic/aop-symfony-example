<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Proxy;

/**
 * JDK-like proxy: implements every interface of the target and delegates to the wrapped instance.
 * Self-invocation inside the target bypasses advice. A method returning the target itself returns the proxy.
 */
final class InterfaceProxyGenerator implements ProxyGeneratorInterface
{
    private const TARGET = '$this->__aopTarget';

    public function __construct(private readonly ProxyCodeBuilder $builder = new ProxyCodeBuilder())
    {
    }

    public function generate(\ReflectionClass $target, array $advisedMethods): GeneratedProxy
    {
        sort($advisedMethods);
        $className = ProxyClassName::for($target->getName(), ProxyStrategy::INTERFACE, $advisedMethods);
        [$file, $class] = $this->builder->createClass($className, $target);

        $interfaces = self::minimalInterfaces($target);
        foreach ($interfaces as $interface) {
            $class->addImplement($interface);
        }
        $class->addMethod('__construct')->setPublic()
            ->addPromotedParameter('__aopTarget')->setPrivate()->setReadOnly()
            ->setType([] === $interfaces ? 'object' : implode('&', $interfaces));
        $class->addMethod('__aopGetTarget')->setPublic()->setReturnType('object')->setBody('return '.self::TARGET.';');

        foreach (self::interfaceMethods($target) as $name => $declaration) {
            $args = $this->builder->forwardedArguments($declaration);
            if ($declaration->isStatic()) {
                $body = $this->builder->returnStatement($declaration, sprintf('\\%s::%s(%s)', $target->getName(), $name, $args), false);
            } elseif (!in_array($name, $advisedMethods, true)) {
                $body = $this->builder->returnStatement($declaration, sprintf('%s->%s(%s)', self::TARGET, $name, $args), false, self::TARGET);
            } else {
                $invoke = sprintf(
                    '$this->__aopRuntime->invoke($this, %s, \\%s::class, %s, [%s], fn (array $args): mixed => %s->%s(...$args))',
                    self::TARGET,
                    $target->getName(),
                    var_export($name, true),
                    $args,
                    self::TARGET,
                    $name,
                );
                $direct = sprintf('%s->%s(%s)', self::TARGET, $name, $args);
                $body = "if (!isset(\$this->__aopRuntime)) {\n"
                    .'    '.str_replace("\n", "\n    ", $this->builder->returnStatement($declaration, $direct, true, self::TARGET))."\n"
                    ."}\n"
                    .$this->builder->returnStatement($declaration, $invoke, false, self::TARGET);
            }
            $class->addMember($this->builder->signature($declaration)->setStatic($declaration->isStatic())->setBody($body));
        }

        return new GeneratedProxy($className, $this->builder->print($file), ProxyStrategy::INTERFACE, $advisedMethods);
    }

    /**
     * Interfaces not already implied by another one, so the transitive set is implemented exactly once
     * (and `Traversable` never appears on its own).
     *
     * @param \ReflectionClass<covariant object> $target
     *
     * @return list<string>
     */
    private static function minimalInterfaces(\ReflectionClass $target): array
    {
        $all = array_values(array_diff($target->getInterfaceNames(), [AdvisedInterface::class]));

        return array_values(array_filter(
            $all,
            static fn (string $candidate): bool => [] === array_filter($all, static fn (string $other): bool => $other !== $candidate && is_subclass_of($other, $candidate)),
        ));
    }

    /**
     * First interface declaration per method name.
     *
     * @param \ReflectionClass<covariant object> $target
     *
     * @return array<string, \ReflectionMethod>
     */
    private static function interfaceMethods(\ReflectionClass $target): array
    {
        $methods = [];
        foreach ($target->getInterfaces() as $interface) {
            if (AdvisedInterface::class === $interface->getName()) {
                continue;
            }
            foreach ($interface->getMethods() as $method) {
                $methods[$method->getName()] ??= $method;
            }
        }

        return $methods;
    }
}
