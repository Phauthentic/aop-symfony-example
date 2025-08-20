<?php

/**
 * Auto-generated AOP proxy for App\Service\TestService
 */

declare(strict_types=1);

namespace App\Service;

/**
 * AOP Proxy for App\Service\TestService
 * Generated automatically with advice injection
 * This is a complete copy of the original class with injected AOP advice
 */
class TestServiceProxy
{
    private \App\AOP\JoinPoint $joinPoint;
    private \App\AOP\TargetSource $targetSource;

    public function getMessage(): string
    {
        // Create temporary instance for JoinPoint target (to avoid infinite loops)
        $tempTarget = new \App\Service\TestService();
        $this->joinPoint = new \App\AOP\JoinPoint($tempTarget, 'getMessage', [], [], $this);

        // Execute Before advice
        $aspect = new \App\AOP\SpringStyleAspect();
        $aspect->logServiceMethods($this->joinPoint);

        // Execute Before advice
        $aspect = new \App\AOP\SpringStyleAspect();
        $aspect->validateSetterMethods($this->joinPoint);

        // Execute Before advice
        $aspect = new \App\AOP\TestAspect();
        $aspect->beforeTestServiceMethod($this->joinPoint);

        // Execute original method body directly
        // Get potentially modified arguments from JoinPoint
        $executionArgs = $this->joinPoint->getArguments();

        // Create a temporary instance and call the method with modified arguments
        $tempInstance = new \App\Service\TestService();
        $result = call_user_func_array([$tempInstance, 'getMessage'], $executionArgs);

        // Execute AfterReturning advice
        $aspect = new \App\AOP\SpringStyleAspect();
        $aspect->logGetterMethods($result);

        // Execute After advice (finally)
        $aspect = new \App\AOP\TestAspect();
        $aspect->afterTestServiceMethod($this->joinPoint);

        return $result;
    }

    public function processData(string $data): string
    {
        // Create temporary instance for JoinPoint target (to avoid infinite loops)
        $tempTarget = new \App\Service\TestService();
        $this->joinPoint = new \App\AOP\JoinPoint($tempTarget, 'processData', [$data], [], $this);

        // Execute Before advice
        $aspect = new \App\AOP\SpringStyleAspect();
        $aspect->logServiceMethods($this->joinPoint);

        // Execute Before advice
        $aspect = new \App\AOP\SpringStyleAspect();
        $aspect->validateSetterMethods($this->joinPoint);

        // Execute Before advice
        $aspect = new \App\AOP\TestAspect();
        $aspect->beforeTestServiceMethod($this->joinPoint);

        // Execute original method body directly
        // Get potentially modified arguments from JoinPoint
        $executionArgs = $this->joinPoint->getArguments();

        // Create a temporary instance and call the method with modified arguments
        $tempInstance = new \App\Service\TestService();
        $result = call_user_func_array([$tempInstance, 'processData'], $executionArgs);

        // Execute AfterReturning advice
        $aspect = new \App\AOP\SpringStyleAspect();
        $aspect->logGetterMethods($result);

        // Execute After advice (finally)
        $aspect = new \App\AOP\TestAspect();
        $aspect->afterTestServiceMethod($this->joinPoint);

        return $result;
    }
}
