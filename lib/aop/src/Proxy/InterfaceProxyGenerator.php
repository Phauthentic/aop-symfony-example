<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Proxy;

/**
 * JDK-like proxy: implements every interface of the target and delegates to the wrapped instance.
 * Self-invocation inside the target bypasses advice. A method returning the target itself returns the proxy,
 * one returning another instance of the target class (a wither) returns a new proxy around it sharing the runtime.
 */
final class InterfaceProxyGenerator implements ProxyGeneratorInterface
{
    private const TARGET = '$this->__aopTarget';

    public function __construct(private readonly ProxyCodeBuilder $builder = new ProxyCodeBuilder())
    {
    }

    public function generate(\ReflectionClass $target, array $advisedMethods, string $salt = ''): GeneratedProxy
    {
        sort($advisedMethods);
        $className = ProxyClassName::for($target->getName(), ProxyStrategy::INTERFACE, $advisedMethods, $salt);
        [$file, $class] = $this->builder->createClass($className, $target);

        $interfaces = self::minimalInterfaces($target);
        foreach ($interfaces as $interface) {
            $class->addImplement($interface);
        }
        $class->addMethod('__construct')->setPublic()
            ->addPromotedParameter('__aopTarget')->setPrivate()->setReadOnly()
            ->setType([] === $interfaces ? 'object' : implode('&', $interfaces));
        $class->addMethod('__aopGetTarget')->setPublic()->setReturnType('object')->setBody('return '.self::TARGET.';');

        $class->addMethod('__aopWrap')->setPrivate()->setReturnType('mixed')->setBody(sprintf(<<<'PHP'
            if ($result === %1$s) {
                return $this;
            }
            if (!$result instanceof \%2$s) {
                return $result;
            }
            $proxy = new static($result);
            if (isset($this->__aopRuntime)) {
                $proxy->__aopSetRuntime($this->__aopRuntime);
            }

            return $proxy;
            PHP, self::TARGET, $target->getName()))
            ->addParameter('result')->setType('mixed');

        foreach (self::interfaceMethodNames($target) as $name) {
            // The target's own declaration is compatible with every interface declaring the method.
            $method = $target->getMethod($name);
            $signature = $this->builder->signature($method)->setStatic($method->isStatic());
            $returnType = self::targetToStatic($signature->getReturnType(), $target);
            $signature->setReturnType($returnType);
            $wrap = !$method->isStatic() && self::mayHoldProxy($returnType, $target);
            $args = $this->builder->forwardedArguments($method);
            if ($method->isStatic()) {
                $body = $this->builder->returnStatement($method, sprintf('\\%s::%s(%s)', $target->getName(), $name, $args), false);
            } elseif (!in_array($name, $advisedMethods, true)) {
                $body = $this->builder->returnStatement($method, sprintf('%s->%s(%s)', self::TARGET, $name, $args), false, $wrap);
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
                    .'    '.str_replace("\n", "\n    ", $this->builder->returnStatement($method, $direct, true, $wrap))."\n"
                    ."}\n"
                    .$this->builder->returnStatement($method, $invoke, false, $wrap);
            }
            $class->addMember($signature->setBody($body));
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
     * Names of all methods declared by the target's interfaces.
     *
     * @param \ReflectionClass<covariant object> $target
     *
     * @return list<string>
     */
    private static function interfaceMethodNames(\ReflectionClass $target): array
    {
        $names = [];
        foreach ($target->getInterfaces() as $interface) {
            if (AdvisedInterface::class === $interface->getName()) {
                continue;
            }
            foreach ($interface->getMethods() as $method) {
                $names[$method->getName()] = true;
            }
        }

        return array_keys($names);
    }

    /**
     * The proxy is not an instance of the target class, so a return type naming the target class (or `self`,
     * already resolved to it) becomes `static`: the proxy implements every interface the target does, so this
     * stays covariant with the interface declarations as long as they do not name a parent class of the target.
     *
     * ponytail: an interface returning a parent *class* of the target cannot be served by the proxy; that fails at load.
     *
     * @param \ReflectionClass<covariant object> $target
     */
    private static function targetToStatic(?string $type, \ReflectionClass $target): ?string
    {
        return null === $type ? null : (string) preg_replace('/(?<![\w\\\\])\\\\?'.preg_quote($target->getName(), '/').'(?![\w\\\\])/', 'static', $type);
    }

    /**
     * Can a value of this return type be the proxy itself? Then results go through `__aopWrap()`.
     *
     * @param \ReflectionClass<covariant object> $target
     */
    private static function mayHoldProxy(?string $type, \ReflectionClass $target): bool
    {
        if (null === $type) {
            return true;
        }
        foreach (preg_split('/[|&()?]/', $type, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $name) {
            $name = ltrim($name, '\\');
            if (in_array(strtolower($name), ['mixed', 'object', 'static'], true) || (interface_exists($name) && $target->implementsInterface($name))) {
                return true;
            }
        }

        return false;
    }
}
