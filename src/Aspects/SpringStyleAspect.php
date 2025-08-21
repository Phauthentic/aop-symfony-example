<?php

declare(strict_types=1);

namespace App\Aspects;

use App\AOP\Before;
use App\AOP\Around;
use App\AOP\AfterReturning;
use App\AOP\Aspect;
use App\AOP\JoinPoint;

#[Aspect]
class SpringStyleAspect
{
    /**
     * Matches all public methods in the Service layer
     * Spring equivalent: @Pointcut("execution(public * com.example.service.*.*(..))")
     */
    #[Before("execution(* App\\Service\\*.*(..))")]
    public function logServiceMethods(JoinPoint $joinPoint): void
    {
        echo "🌸 SPRING STYLE: Service method execution\n";
        echo "   Method: " . $joinPoint->getSignature() . "\n";
        echo "   Arguments: " . json_encode($joinPoint->getArguments()) . "\n";
    }

    /**
     * Matches all methods within the UserService class
     * Spring equivalent: @Pointcut("within(com.example.service.UserService)")
     */
    #[Around("within(App\\Service\\UserService)")]
    public function profileUserServiceMethods(JoinPoint $joinPoint)
    {
        $startTime = microtime(true);
        
        echo "🌸 SPRING STYLE: UserService method profiling started\n";
        echo "   Method: " . $joinPoint->getSignature() . "\n";
        
        try {
            $result = $joinPoint->proceed();
            
            $endTime = microtime(true);
            $duration = ($endTime - $startTime) * 1000;
            
            echo "🌸 SPRING STYLE: UserService method completed in " . number_format($duration, 2) . "ms\n";
            
            return $result;
            
        } catch (\Throwable $e) {
            echo "🌸 SPRING STYLE: UserService method failed: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    /**
     * After returning advice for getter methods
     * Spring equivalent: @AfterReturning("execution(* get*(..))")
     */
    #[AfterReturning("execution(* get*(..))")]
    public function logGetterMethods($result)
    {
        echo "🌸 SPRING STYLE: Getter method returned\n";
        echo "   Return value: " . json_encode($result) . "\n";
        
        // Return the result unchanged for logging-only advice
        return $result;
    }

    /**
     * Before advice for setter methods
     * Spring equivalent: @Before("execution(* set*(*)) && args(value)")
     */
    #[Before("execution(* set*(..))")]
    public function validateSetterMethods(JoinPoint $joinPoint): void
    {
        echo "🌸 SPRING STYLE: Setter method validation\n";
        echo "   Method: " . $joinPoint->getSignature() . "\n";
        
        $args = $joinPoint->getArguments();
        if (!empty($args)) {
            echo "   Setting value: " . json_encode($args[0]) . "\n";
            
            // Example validation: prevent null values
            if ($args[0] === null) {
                echo "   ⚠️  WARNING: Attempting to set null value\n";
            }
        }
    }
}
