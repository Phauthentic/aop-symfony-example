<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Fixtures;

use Phauthentic\AOP\Advice\AdviceDefinition;
use Phauthentic\AOP\Advice\AdviceType;
use Phauthentic\AOP\Aspect\ArrayAspectInstanceResolver;
use Phauthentic\AOP\Binding\BindingPlan;
use Phauthentic\AOP\Binding\ParameterBinder;
use Phauthentic\AOP\Interceptor\MethodInterceptorInterface;
use Phauthentic\AOP\JoinPoint\MethodInvocation;
use Phauthentic\AOP\JoinPoint\MethodSignature;
use Phauthentic\AOP\Pointcut\Expression\PointcutExpressionCompiler;
use Phauthentic\AOP\Tests\Fixtures\Aspect\ProbeAspect;
use Phauthentic\AOP\Tests\Fixtures\Service\Greeter;

/**
 * Test helpers to wire a ProbeAspect method into an interceptor and run it against Greeter::greet().
 */
final class Chain
{
    public static function plan(string $method, AdviceType $type, ?string $returning = null, ?string $throwing = null): BindingPlan
    {
        $advice = new AdviceDefinition(ProbeAspect::class, $method, $type, 'execution(* *::*(..))', $returning, $throwing, 0);
        $pointcut = (new PointcutExpressionCompiler())->compile($advice->expression);

        return (new ParameterBinder())->plan(new \ReflectionMethod(ProbeAspect::class, $method), $advice, $pointcut);
    }

    /**
     * @param list<MethodInterceptorInterface>          $interceptors
     * @param \Closure(array<int|string, mixed>): mixed $target
     * @param array<int|string, mixed>                  $args
     */
    public static function invocation(array $interceptors, \Closure $target, array $args = ['World']): MethodInvocation
    {
        $greeter = new Greeter();

        return new MethodInvocation(
            $greeter,
            $greeter,
            MethodSignature::fromReflection(new \ReflectionMethod(Greeter::class, 'greet')),
            $args,
            $interceptors,
            $target,
        );
    }

    public static function resolver(ProbeAspect $probe): ArrayAspectInstanceResolver
    {
        return new ArrayAspectInstanceResolver([ProbeAspect::class => $probe]);
    }
}
