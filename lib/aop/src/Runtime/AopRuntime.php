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
 * Entry point called by generated proxies. Interceptor chains are built lazily and cached per class, method and proxy strategy.
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
     * Must be called before the first invocation of the class' methods.
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
        // A subclass proxy is an instance of the target class, an interface proxy is not; `this()` matching depends on it.
        $strategy = $proxy instanceof $class ? ProxyStrategy::SUBCLASS : ProxyStrategy::INTERFACE;
        [$interceptors, $signature] = $this->chains[$class.'::'.$method.'@'.$strategy->value] ??= $this->build($class, $method, $strategy);

        return (new MethodInvocation($proxy, $target, $signature, $args, $interceptors, $targetInvoker))->proceed();
    }

    /**
     * @param class-string $class
     *
     * @return array{0: list<MethodInterceptorInterface>, 1: MethodSignature}
     */
    private function build(string $class, string $method, ProxyStrategy $strategy): array
    {
        $rc = new \ReflectionClass($class);
        $rm = $rc->getMethod($method);

        return [
            $this->factory->chain($rc, $rm, new StaticMatchContext($rc, $this->beanNames[$class] ?? null, $strategy)),
            MethodSignature::fromReflection($rm),
        ];
    }
}
