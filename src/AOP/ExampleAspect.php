<?php

declare(strict_types=1);

namespace App\AOP;

/**
 * Example aspect demonstrating all advice types
 * This is equivalent to Spring AOP aspects
 */
#[Aspect]
class ExampleAspect
{
    /**
     * Before advice - executed before method execution
     */
    #[Before("execution(* App\Service\*.*(..))")]
    public function beforeServiceMethod(JoinPoint $joinPoint): void
    {
        echo "Before executing: " . $joinPoint->getSignature() . "\n";
    }

    /**
     * After advice - executed after method execution (finally)
     */
    #[After("within(App\Service\*)")]
    public function afterServiceMethod(JoinPoint $joinPoint): void
    {
        echo "After executing: " . $joinPoint->getSignature() . "\n";
    }

    /**
     * Around advice - executed around method execution
     */
    #[Around("execution(* App\Service\UserService::*())")]
    public function aroundUserServiceMethod(JoinPoint $joinPoint)
    {
        echo "Around before: " . $joinPoint->getSignature() . "\n";
        
        try {
            $result = $joinPoint->proceed();
            echo "Around after (success): " . $joinPoint->getSignature() . "\n";
            return $result;
        } catch (\Exception $e) {
            echo "Around after (exception): " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    /**
     * AfterReturning advice - executed after successful return
     */
    #[AfterReturning("execution(* App\Service\*::get*(..))")]
    public function afterReturningGetter(JoinPoint $joinPoint, $result): void
    {
        echo "Getter method returned: " . (is_scalar($result) ? $result : gettype($result)) . "\n";
    }

    /**
     * AfterThrowing advice - executed when exception is thrown
     */
    #[AfterThrowing("execution(* App\Service\*::*())")]
    public function afterThrowingServiceMethod(JoinPoint $joinPoint, \Throwable $exception): void
    {
        echo "Exception in " . $joinPoint->getSignature() . ": " . $exception->getMessage() . "\n";
    }

    /**
     * Annotation-based pointcut example
     */
    #[Before("@annotation(App\AOP\Loggable)")]
    public function beforeLoggableMethod(JoinPoint $joinPoint): void
    {
        echo "Logging execution of: " . $joinPoint->getSignature() . "\n";
    }
}
