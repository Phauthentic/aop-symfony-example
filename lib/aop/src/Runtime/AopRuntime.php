<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Runtime;

use Phauthentic\AOP\Advisor\AdvisorChainFactory;
use Phauthentic\AOP\Interceptor\MethodInterceptorInterface;
use Phauthentic\AOP\JoinPoint\MethodInvocation;
use Phauthentic\AOP\JoinPoint\MethodSignature;
use Phauthentic\AOP\Pointcut\StaticMatchContext;
use Phauthentic\AOP\Proxy\ProxyStrategy;

/**
 * Entry point called by generated proxies. Interceptor chains are built lazily and cached per proxy class and method.
 */
final class AopRuntime
{
    /** @var array<string, array{0: list<MethodInterceptorInterface>, 1: MethodSignature}> */
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
     * @param array<int|string, mixed>                  $args
     * @param \Closure(array<int|string, mixed>): mixed $targetInvoker
     */
    public function invoke(object $proxy, object $target, string $class, string $method, array $args, \Closure $targetInvoker): mixed
    {
        [$interceptors, $signature] = $this->chains[$proxy::class.'::'.$method] ??= $this->build($proxy, $class, $method);

        return (new MethodInvocation($proxy, $target, $signature, $args, $interceptors, $targetInvoker))->proceed();
    }

    /**
     * @param class-string $class
     *
     * @return array{0: list<MethodInterceptorInterface>, 1: MethodSignature}
     */
    private function build(object $proxy, string $class, string $method): array
    {
        // A subclass proxy is an instance of the target class, an interface proxy is not; `this()` matching depends on it.
        $strategy = $proxy instanceof $class ? ProxyStrategy::SUBCLASS : ProxyStrategy::INTERFACE;
        $rc = new \ReflectionClass($class);
        $rm = $rc->getMethod($method);

        return [
            $this->factory->chain($rc, $rm, new StaticMatchContext($rc, $this->beanName($proxy, $class), $strategy)),
            MethodSignature::fromReflection($rm),
        ];
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
