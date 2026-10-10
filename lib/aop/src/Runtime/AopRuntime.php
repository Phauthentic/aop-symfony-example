<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Runtime;

use Phauthentic\AOP\Advice\AdviceType;
use Phauthentic\AOP\Advisor\Advisor;
use Phauthentic\AOP\Advisor\AdvisorChainFactory;
use Phauthentic\AOP\Exception\AdviceReturnException;
use Phauthentic\AOP\Interceptor\MethodInterceptorInterface;
use Phauthentic\AOP\JoinPoint\MethodInvocation;
use Phauthentic\AOP\JoinPoint\MethodSignature;
use Phauthentic\AOP\Pointcut\StaticMatchContext;
use Phauthentic\AOP\Proxy\AdvisedInterface;
use Phauthentic\AOP\Proxy\MethodEligibility;
use Phauthentic\AOP\Proxy\ProxyStrategy;

/**
 * Entry point called by generated proxies. Interceptor chains are built lazily and cached per proxy class and method.
 */
final class AopRuntime
{
    /** @var array<string, array{0: list<MethodInterceptorInterface>, 1: MethodSignature, 2: list<string>}> */
    private array $chains = [];

    /** @var array<string, string> */
    private array $beanNames = [];

    public function __construct(private readonly AdvisorChainFactory $factory)
    {
    }

    /**
     * Must be called before the first invocation of the class' methods. `$class` is a generated proxy class
     * (or any subclass of it), or a target class, which applies to every proxy of it without its own bean name.
     */
    public function registerBeanName(string $class, string $beanName): void
    {
        $this->beanNames[$class] = $beanName;
    }

    /**
     * @param class-string                              $class
     * @param array<int|string, mixed>                  $args          by-reference parameters as references to the caller's variables
     * @param \Closure(array<int|string, mixed>): mixed $targetInvoker
     */
    public function invoke(object $proxy, object $target, string $class, string $method, array $args, \Closure $targetInvoker): mixed
    {
        [$interceptors, $signature, $arounds] = $this->chains[$proxy::class.'::'.$method] ??= $this->build($proxy, $class, $method);

        AopContext::push($proxy);
        try {
            $result = (new MethodInvocation($proxy, $target, $signature, $args, $interceptors, $targetInvoker))->proceed();
        } finally {
            AopContext::pop();
        }

        // Only an Around advice can replace the target's result; the target itself is type-checked by PHP.
        if ([] !== $arounds && !ReturnTypeCheck::accepts($signature->method->getReturnType(), $result, $proxy, $target, $signature->className)) {
            throw new AdviceReturnException(sprintf('Around advice %s returned %s, but %s is declared to return %s.', implode(' or ', $arounds), get_debug_type($result), $signature->toShortString(), (string) $signature->method->getReturnType()));
        }

        return $result;
    }

    /**
     * @param class-string $class
     *
     * @return array{0: list<MethodInterceptorInterface>, 1: MethodSignature, 2: list<string>} interceptors, signature, ids of the Around advice in the chain
     */
    private function build(object $proxy, string $class, string $method): array
    {
        // A subclass proxy is an instance of the target class, an interface proxy is not; `this()` matching depends on it.
        $strategy = $proxy instanceof $class ? ProxyStrategy::SUBCLASS : ProxyStrategy::INTERFACE;
        $rc = new \ReflectionClass($class);
        $introduced = self::introducedInterfaces($proxy, $rc);
        $rm = $rc->hasMethod($method) ? $rc->getMethod($method) : MethodEligibility::introducedMethods($rc, $introduced)[$method];
        $ctx = new StaticMatchContext($rc, $this->beanName($proxy, $class), $strategy, $introduced);
        $advisors = $this->factory->matchedAdvisors($rc, $rm, $ctx);
        $arounds = array_values(array_map(
            static fn (Advisor $a): string => $a->id,
            array_filter($advisors, static fn (Advisor $a): bool => AdviceType::AROUND === $a->advice->type),
        ));

        return [$this->factory->chainFor($advisors, $ctx), MethodSignature::fromReflection($rm), $arounds];
    }

    /**
     * Interfaces the generated proxy class implements beyond the target's and AdvisedInterface. Subclasses of the
     * proxy (e.g. lazy ghosts) are skipped, their own interfaces are not introductions.
     *
     * @param \ReflectionClass<object> $target
     *
     * @return list<class-string>
     */
    private static function introducedInterfaces(object $proxy, \ReflectionClass $target): array
    {
        $class = $proxy::class;
        while (false !== ($parent = get_parent_class($class)) && is_a($parent, AdvisedInterface::class, true)) {
            $class = $parent;
        }

        return array_values(array_diff(class_implements($class) ?: [], $target->getInterfaceNames(), [AdvisedInterface::class]));
    }

    /**
     * Walks up from the runtime class, so subclasses of a proxy (e.g. lazy ghosts) find the proxy's bean name.
     */
    private function beanName(object $proxy, string $class): ?string
    {
        for ($c = $proxy::class; false !== $c; $c = get_parent_class($c)) {
            if (isset($this->beanNames[$c])) {
                return $this->beanNames[$c];
            }
        }

        return $this->beanNames[$class] ?? null;
    }
}
