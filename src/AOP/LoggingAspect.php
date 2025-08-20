<?php

declare(strict_types=1);

namespace App\AOP;

use App\AOP\JoinPoint;

/**
 * Comprehensive logging aspect demonstrating all AOP advice types
 * This aspect will be applied to UserService methods
 */
#[Aspect]
class LoggingAspect
{
    #[Before("App\Service\UserService::*")]
    public function beforeUserServiceMethod(JoinPoint $joinPoint): void
    {
        echo "🔍 BEFORE: UserService method execution started\n";
        echo "   Method: " . $joinPoint->getSignature() . "\n";
        echo "   Arguments: " . json_encode($joinPoint->getArguments()) . "\n";
        
        // Example: Modify arguments if needed
        // $joinPoint->setArgument(0, 'Modified: ' . $joinPoint->getArgument(0));
    }

    #[Around("App\Service\UserService::tellName")]
    public function aroundTellNameMethod(JoinPoint $joinPoint)
    {
        echo "🔄 AROUND: Intercepting tellName method call\n";
        echo "   Method: " . $joinPoint->getSignature() . "\n";
        echo "   Arguments: " . json_encode($joinPoint->getArguments()) . "\n";
        
        // Execute the original method
        $result = $joinPoint->proceed();
        
        echo "   Result: " . json_encode($result) . "\n";
        echo "🔄 AROUND: tellName method execution completed\n";
        
        return $result;
    }

    #[AfterReturning("App\Service\UserService::tellName")]
    public function afterReturningTellNameMethod($result): void
    {
        echo "✅ AFTER RETURNING: tellName method returned successfully\n";
        echo "   Return value: " . json_encode($result) . "\n";
    }

    #[After("App\Service\UserService::*")]
    public function afterUserServiceMethod(JoinPoint $joinPoint): void
    {
        echo "🏁 AFTER: UserService method execution completed (finally)\n";
        echo "   Method: " . $joinPoint->getSignature() . "\n";
        echo "   Final arguments: " . json_encode($joinPoint->getArguments()) . "\n";
    }
}
