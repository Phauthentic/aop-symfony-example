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

    public function generate(\ReflectionClass $target, array $advisedMethods, string $salt = '', array $introductions = []): GeneratedProxy
    {
        sort($advisedMethods);
        ksort($introductions);
        $className = ProxyClassName::for($target->getName(), ProxyStrategy::SUBCLASS, $advisedMethods, $salt, $introductions);
        [$file, $class] = $this->builder->createClass($className, $target);
        $class->setExtends($target->getName());
        $class->addMethod('__aopGetTarget')->setPublic()->setReturnType('object')->setBody('return $this;');

        $this->builder->addIntroductions($class, $target, $introductions, $advisedMethods, '$this');

        // Advised introduced methods were added above.
        foreach (array_diff($advisedMethods, array_keys(MethodEligibility::introducedMethods($target, array_keys($introductions)))) as $name) {
            $method = $target->getMethod($name);
            $args = $this->builder->forwardedArguments($method);
            $direct = sprintf('parent::%s(%s)', $name, $args);
            $invoke = sprintf(
                '%s->invoke($this, $this, \\%s::class, %s, %s, fn (array $args): mixed => parent::%s(...$args))',
                ProxyCodeBuilder::RUNTIME,
                $target->getName(),
                var_export($name, true),
                $this->builder->argumentArray($method),
                $name,
            );

            $class->addMember($this->builder->signature($method)->setBody(
                'if (null === '.ProxyCodeBuilder::RUNTIME.") {\n"
                .'    '.str_replace("\n", "\n    ", $this->builder->returnStatement($method, $direct, true))."\n"
                ."}\n"
                .$this->builder->returnStatement($method, $invoke, false),
            ));
        }

        return new GeneratedProxy($className, $this->builder->print($file), ProxyStrategy::SUBCLASS, $advisedMethods, $introductions);
    }
}
