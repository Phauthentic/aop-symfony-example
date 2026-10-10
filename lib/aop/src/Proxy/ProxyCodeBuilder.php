<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Proxy;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Factory;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\Parameter;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;
use Nette\PhpGenerator\Visibility;
use Phauthentic\AOP\Exception\ProxyGenerationException;
use Phauthentic\AOP\Runtime\AopRuntime;

/**
 * Codegen helpers shared by both proxy generators (see plan §2.5 "Codegen rules").
 *
 * @internal
 */
final class ProxyCodeBuilder
{
    /**
     * Expression resolving the runtime inside a generated proxy method: the instance's own, else the class-level fallback.
     */
    public const RUNTIME = 'self::__aopRuntime($this)';

    private readonly Factory $factory;

    public function __construct()
    {
        $this->factory = new Factory();
    }

    /**
     * Creates the file and the (never final) proxy class with the runtime property and setter.
     *
     * @param \ReflectionClass<covariant object> $target
     *
     * @return array{PhpFile, ClassType}
     */
    public function createClass(string $className, \ReflectionClass $target): array
    {
        $position = (int) strrpos($className, '\\');
        $file = (new PhpFile())->setStrictTypes();
        $class = $file->addNamespace(substr($className, 0, $position))->addClass(substr($className, $position + 1));
        $class->setReadOnly($target->isReadOnly());
        $class->addImplement(AdvisedInterface::class);
        // Per instance, so proxies of the same class wired by different factories or kernels keep their own runtime;
        // the class-level fallback serves instances never wired themselves (`new static` inside the target, instances
        // built before wiring, clones, unserialized copies). A WeakMap in a static local instead of a property keeps
        // the runtime out of serialize(), var_dump() and readonly constraints; PHP rejects static properties on
        // readonly classes.
        $class->addMethod('__aopRuntime')
            ->setPrivate()
            ->setStatic()
            ->setReturnType(AopRuntime::class)
            ->setReturnNullable()
            ->setBody(<<<'PHP'
                static $current = null;
                static $instances = new \WeakMap();
                if (null !== $runtime) {
                    $current = $instances[$instance] = $runtime;
                }

                return $instances[$instance] ?? $current;
                PHP)
            ->setParameters([
                (new Parameter('instance'))->setType('object'),
                (new Parameter('runtime'))->setType(AopRuntime::class)->setNullable()->setDefaultValue(null),
            ]);
        $class->addMethod('__aopSetRuntime')
            ->setPublic()
            ->setReturnType('void')
            ->setBody('self::__aopRuntime($this, $runtime);')
            ->addParameter('runtime')->setType(AopRuntime::class);

        return [$file, $class];
    }

    /**
     * Makes the proxy implement the introduced interfaces. Their methods the target does not declare publicly delegate
     * to one default implementation per proxy instance, created on first use; advised ones route through the runtime
     * like target methods. The instances live in a `WeakMap` keyed by the proxy, which works for readonly classes too.
     *
     * @param \ReflectionClass<covariant object> $target
     * @param array<class-string, class-string>  $introductions    interface => default implementation
     * @param list<string>                       $advisedMethods
     * @param string                             $targetExpression the advised instance as passed to the runtime
     */
    public function addIntroductions(ClassType $class, \ReflectionClass $target, array $introductions, array $advisedMethods, string $targetExpression): void
    {
        if ([] === $introductions) {
            return;
        }
        $instances = [];
        foreach ($introductions as $interface => $impl) {
            $class->addImplement($interface);
            $instances[] = sprintf('    \\%s::class => new \\%s(),', $interface, $impl);
        }
        $class->addMethod('__aopIntroduction')
            ->setPrivate()
            ->setReturnType('object')
            ->setBody("static \$instances = null;\n\$instances ??= new \\WeakMap();\n\$instances[\$this] ??= [];\n\n"
                ."return \$instances[\$this][\$interface] ??= match (\$interface) {\n".implode("\n", $instances)."\n};")
            ->addParameter('interface')->setType('string');

        foreach (MethodEligibility::introducedMethods($target, array_keys($introductions)) as $name => $method) {
            $interface = $method->getDeclaringClass()->getName();
            foreach (array_keys($introductions) as $introduced) {
                if (is_a($introduced, $interface, true)) {
                    $interface = $introduced;
                    break;
                }
            }
            $args = $this->forwardedArguments($method);
            $signature = $this->signature($method)->setStatic($method->isStatic());
            if ($method->isStatic()) {
                $class->addMember($signature->setBody($this->returnStatement($method, sprintf('\\%s::%s(%s)', $introductions[$interface], $name, $args), false)));
                continue;
            }
            $delegate = sprintf('$this->__aopIntroduction(\\%s::class)->%s', $interface, $name);
            if (!in_array($name, $advisedMethods, true)) {
                $class->addMember($signature->setBody($this->returnStatement($method, sprintf('%s(%s)', $delegate, $args), false)));
                continue;
            }
            $invoke = sprintf(
                '%s->invoke($this, %s, \\%s::class, %s, %s, fn (array $args): mixed => %s(...$args))',
                self::RUNTIME,
                $targetExpression,
                $target->getName(),
                var_export($name, true),
                $this->argumentArray($method),
                $delegate,
            );
            $class->addMember($signature->setBody(
                'if (null === '.self::RUNTIME.") {\n"
                .'    '.str_replace("\n", "\n    ", $this->returnStatement($method, sprintf('%s(%s)', $delegate, $args), true))."\n"
                ."}\n"
                .$this->returnStatement($method, $invoke, false),
            ));
        }
    }

    /**
     * Copies the signature of `$method`: `self`/`parent` resolved to the declaring class FQCN / its parent,
     * method attributes dropped except `#[\ReturnTypeWillChange]` (an untyped implementation of an internal interface
     * method would otherwise emit a deprecation), only `#[\SensitiveParameter]` kept on parameters, constant defaults made absolute.
     */
    public function signature(\ReflectionMethod $method): Method
    {
        $copy = $this->factory->fromMethodReflection($method);
        $copy->setComment(null)
            ->setAttributes(self::keepAttribute($copy->getAttributes(), \ReturnTypeWillChange::class))
            ->setFinal(false)
            ->setAbstract(false)
            ->setVisibility($method->isProtected() ? Visibility::Protected : Visibility::Public)
            ->setReturnType($this->resolveRelativeTypes($method->getReturnType() ?? $method->getTentativeReturnType(), $method));

        foreach ($method->getParameters() as $reflection) {
            $parameter = $copy->getParameter($reflection->getName());
            $parameter->setType($this->resolveRelativeTypes($reflection->getType(), $method));
            $parameter->setAttributes(self::keepAttribute($parameter->getAttributes(), \SensitiveParameter::class));
            if ($reflection->isDefaultValueAvailable() && $reflection->isDefaultValueConstant()) {
                $parameter->setDefaultValue($this->constantDefault($reflection, $method));
            }
        }

        return $copy;
    }

    /**
     * `$a, $b, ...$rest`: declared parameters in order, then the variadic tail with its original keys.
     */
    public function forwardedArguments(\ReflectionMethod $method): string
    {
        return implode(', ', array_map(
            static fn (\ReflectionParameter $p): string => ($p->isVariadic() ? '...$' : '$').$p->getName(),
            $method->getParameters(),
        ));
    }

    /**
     * `[&$a, $b, ...$rest]`: the argument array handed to the runtime. By-reference parameters enter it as references,
     * so spreading it into the target binds the caller's variables; a by-reference variadic tail keeps its references when spread.
     */
    public function argumentArray(\ReflectionMethod $method): string
    {
        return '['.implode(', ', array_map(
            static fn (\ReflectionParameter $p): string => ($p->isVariadic() ? '...' : ($p->isPassedByReference() ? '&' : '')).'$'.$p->getName(),
            $method->getParameters(),
        )).']';
    }

    /**
     * Turns an expression into the statement(s) that end the method according to its return type.
     *
     * @param bool $earlyExit statement is followed by more code, so `void` must return explicitly
     * @param bool $wrap      pass the result through the interface proxy's `__aopWrap()`
     */
    public function returnStatement(\ReflectionMethod $method, string $expression, bool $earlyExit, bool $wrap = false): string
    {
        $type = $method->getReturnType();
        $name = $type instanceof \ReflectionNamedType ? $type->getName() : null;

        return match (true) {
            'never' === $name => $expression.';',
            'void' === $name => $expression.';'.($earlyExit ? "\nreturn;" : ''),
            $wrap => 'return $this->__aopWrap('.$expression.');',
            default => 'return '.$expression.';',
        };
    }

    public function print(PhpFile $file): string
    {
        return (new PsrPrinter())->printFile($file);
    }

    /**
     * @param list<\Nette\PhpGenerator\Attribute> $attributes
     *
     * @return list<\Nette\PhpGenerator\Attribute>
     */
    private static function keepAttribute(array $attributes, string $class): array
    {
        return array_values(array_filter($attributes, static fn (\Nette\PhpGenerator\Attribute $a): bool => $class === ltrim($a->getName(), '\\')));
    }

    private function resolveRelativeTypes(?\ReflectionType $type, \ReflectionMethod $method): ?string
    {
        if (null === $type) {
            return null;
        }
        $declaring = $method->getDeclaringClass();

        return (string) preg_replace_callback(
            '/(?<![\w\\\\])(self|parent)(?![\w\\\\])/i',
            static fn (array $m): string => 'self' === strtolower($m[1])
                ? '\\'.$declaring->getName()
                : '\\'.($declaring->getParentClass() ?: throw new \LogicException('"parent" used in a class without parent.'))->getName(),
            (string) $type,
        );
    }

    private function constantDefault(\ReflectionParameter $parameter, \ReflectionMethod $method): mixed
    {
        $name = (string) $parameter->getDefaultValueConstantName();
        if (!str_contains($name, '::')) {
            // Unqualified constants in namespaced code are reported namespace-prefixed even when they fall back to the global one.
            $name = ltrim($name, '\\');
            $name = defined($name) ? $name : substr((string) strrchr('\\'.$name, '\\'), 1);

            return new Literal('\\'.$name);
        }

        [$class, $constant] = explode('::', $name, 2);
        $declaring = $method->getDeclaringClass();
        $class = match (strtolower($class)) {
            'self', 'static' => $declaring->getName(),
            'parent' => ($declaring->getParentClass() ?: $declaring)->getName(),
            default => ltrim($class, '\\'),
        };

        if (!class_exists($class) && !interface_exists($class)) {
            throw new ProxyGenerationException(sprintf('Cannot resolve default value "%s" of parameter $%s.', $name, $parameter->getName()));
        }
        if ((new \ReflectionClassConstant($class, $constant))->isPublic()) {
            return new Literal('\\'.$class.'::'.$constant);
        }

        // Non-public constants are not reachable from the proxy: inline their value.
        return $parameter->getDefaultValue();
    }
}
