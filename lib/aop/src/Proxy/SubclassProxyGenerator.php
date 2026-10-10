<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Proxy;

/**
 * CGLIB-like proxy: a subclass overriding only the advised methods. `$this` inside the target is the proxy,
 * so self-invocation is advised too.
 */
final class SubclassProxyGenerator implements ProxyGeneratorInterface
{
    public function __construct(private readonly ProxyCodeBuilder $builder = new ProxyCodeBuilder())
    {
    }

    public function generate(\ReflectionClass $target, array $advisedMethods, string $salt = ''): GeneratedProxy
    {
        sort($advisedMethods);
        $className = ProxyClassName::for($target->getName(), ProxyStrategy::SUBCLASS, $advisedMethods, $salt);
        [$file, $class] = $this->builder->createClass($className, $target);
        $class->setExtends($target->getName());
        $class->addMethod('__aopGetTarget')->setPublic()->setReturnType('object')->setBody('return $this;');

        foreach ($advisedMethods as $name) {
            $method = $target->getMethod($name);
            $args = $this->builder->forwardedArguments($method);
            $direct = sprintf('parent::%s(%s)', $name, $args);
            $invoke = sprintf(
                'self::__aopRuntime()->invoke($this, $this, \\%s::class, %s, [%s], fn (array $args): mixed => parent::%s(...$args))',
                $target->getName(),
                var_export($name, true),
                $args,
                $name,
            );

            $class->addMember($this->builder->signature($method)->setBody(
                "if (null === self::__aopRuntime()) {\n"
                .'    '.str_replace("\n", "\n    ", $this->builder->returnStatement($method, $direct, true))."\n"
                ."}\n"
                .$this->builder->returnStatement($method, $invoke, false),
            ));
        }

        return new GeneratedProxy($className, $this->builder->print($file), ProxyStrategy::SUBCLASS, $advisedMethods);
    }
}
