<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Advisor;

use Phauthentic\AOP\Advice\AdviceType;
use Phauthentic\AOP\Aspect\AspectInstanceResolverInterface;
use Phauthentic\AOP\Binding\BindingPlan;
use Phauthentic\AOP\Binding\ParameterBinder;
use Phauthentic\AOP\Interceptor\AfterAdviceInterceptor;
use Phauthentic\AOP\Interceptor\AfterReturningAdviceInterceptor;
use Phauthentic\AOP\Interceptor\AfterThrowingAdviceInterceptor;
use Phauthentic\AOP\Interceptor\AroundAdviceInterceptor;
use Phauthentic\AOP\Interceptor\BeforeAdviceInterceptor;
use Phauthentic\AOP\Interceptor\MethodInterceptorInterface;
use Phauthentic\AOP\Interceptor\RuntimeGuardInterceptor;
use Phauthentic\AOP\Pointcut\StaticMatchContext;

final class AdvisorChainFactory
{
    /** @var array<string, BindingPlan> */
    private array $plans = [];

    public function __construct(
        private readonly AdvisorRegistry $registry,
        private readonly AspectInstanceResolverInterface $aspects,
        private readonly ParameterBinder $binder,
    ) {
    }

    /**
     * @param \ReflectionClass<object> $class
     *
     * @return list<Advisor>
     */
    public function matchedAdvisors(\ReflectionClass $class, \ReflectionMethod $method, StaticMatchContext $ctx): array
    {
        return array_values(array_filter(
            $this->registry->all(),
            static fn (Advisor $a): bool => $a->pointcut->matchesClass($class, $ctx) && $a->pointcut->matchesMethod($method, $ctx),
        ));
    }

    /**
     * @param \ReflectionClass<object> $class
     *
     * @return list<MethodInterceptorInterface>
     */
    public function chain(\ReflectionClass $class, \ReflectionMethod $method, StaticMatchContext $ctx): array
    {
        $chain = [];
        foreach ($this->matchedAdvisors($class, $method, $ctx) as $advisor) {
            $interceptor = $this->interceptor($advisor);
            $chain[] = $advisor->pointcut->isRuntime() ? new RuntimeGuardInterceptor($advisor->pointcut, $interceptor, $ctx) : $interceptor;
        }

        return $chain;
    }

    private function interceptor(Advisor $advisor): MethodInterceptorInterface
    {
        $advice = $advisor->advice;
        $plan = $this->plans[$advisor->id] ??= $this->binder->plan(new \ReflectionMethod($advice->aspectClass, $advice->methodName), $advice, $advisor->pointcut);
        $args = [$this->aspects, $advice->aspectClass, $advice->methodName, $plan];

        return match ($advice->type) {
            AdviceType::BEFORE => new BeforeAdviceInterceptor(...$args),
            AdviceType::AFTER_RETURNING => new AfterReturningAdviceInterceptor(...$args),
            AdviceType::AFTER_THROWING => new AfterThrowingAdviceInterceptor(...$args),
            AdviceType::AFTER => new AfterAdviceInterceptor(...$args),
            AdviceType::AROUND => new AroundAdviceInterceptor(...$args),
        };
    }
}
