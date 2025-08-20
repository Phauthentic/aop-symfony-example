<?php

/**
 * Auto-generated AOP proxy for App\Service\UserService
 */

declare(strict_types=1);

namespace App\Service;

/**
 * AOP Proxy for App\Service\UserService
 * Generated automatically with advice injection
 * This is a complete copy of the original class with injected AOP advice
 */
class UserServiceProxy
{
    private \App\AOP\JoinPoint $joinPoint;
    private \App\AOP\TargetSource $targetSource;

    public function tellName(string $test): void
    {
        // Create temporary instance for JoinPoint target (to avoid infinite loops)
        $tempTarget = new \App\Service\UserService();
        // Create interceptor chain for Around advice
        $interceptors = [function($joinPoint) { $aspect = new \App\AOP\LoggingAspect(); return $aspect->aroundTellNameMethod($joinPoint); }, function($joinPoint) { $aspect = new \App\AOP\PerformanceAspect(); return $aspect->measureExecutionTime($joinPoint); }, function($joinPoint) { $aspect = new \App\AOP\SpringStyleAspect(); return $aspect->profileUserServiceMethods($joinPoint); }];
        $this->joinPoint = new \App\AOP\JoinPoint($tempTarget, 'tellName', [$test], $interceptors, $this);

        // Execute Before advice
        $aspect = new \App\AOP\ArgumentModificationAspect();
        $aspect->modifyArguments($this->joinPoint);

        // Execute Before advice
        $aspect = new \App\AOP\LoggingAspect();
        $aspect->beforeUserServiceMethod($this->joinPoint);

        // Execute Before advice
        $aspect = new \App\AOP\SpringStyleAspect();
        $aspect->logServiceMethods($this->joinPoint);

        // Execute Before advice
        $aspect = new \App\AOP\SpringStyleAspect();
        $aspect->validateSetterMethods($this->joinPoint);

        // Execute method through Around advice interceptor chain
        $result = $this->joinPoint->proceed();

        // Execute AfterReturning advice
        $aspect = new \App\AOP\ArgumentModificationAspect();
        $aspect->modifyResult($result);

        // Execute AfterReturning advice
        $aspect = new \App\AOP\LoggingAspect();
        $aspect->afterReturningTellNameMethod($result);

        // Execute AfterReturning advice
        $aspect = new \App\AOP\SpringStyleAspect();
        $aspect->logGetterMethods($result);

        // Execute After advice (finally)
        $aspect = new \App\AOP\ArgumentModificationAspect();
        $aspect->logFinalState($this->joinPoint);

        // Execute After advice (finally)
        $aspect = new \App\AOP\LoggingAspect();
        $aspect->afterUserServiceMethod($this->joinPoint);
    }
}
