<?php

declare(strict_types=1);

namespace App\Aspects;

use App\AOP\Before;
use App\AOP\Around;
use App\AOP\AfterReturning;
use App\AOP\After;
use App\AOP\Aspect;
use App\AOP\JoinPoint;

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
    public function afterReturningTellNameMethod($result)
    {
        echo "✅ AFTER RETURNING: tellName method returned successfully\n";
        echo "   Return value: " . json_encode($result) . "\n";
        
        // Return the result unchanged for logging-only advice
        return $result;
    }

    #[After("App\Service\UserService::*")]
    public function afterUserServiceMethod(JoinPoint $joinPoint): void
    {
        echo "🏁 AFTER: UserService method execution completed (finally)\n";
        echo "   Method: " . $joinPoint->getSignature() . "\n";
        echo "   Final arguments: " . json_encode($joinPoint->getArguments()) . "\n";
    }
}
