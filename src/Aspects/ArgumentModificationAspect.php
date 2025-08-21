<?php

declare(strict_types=1);

namespace App\Aspects;

use App\AOP\Before;
use App\AOP\AfterReturning;
use App\AOP\After;
use App\AOP\Aspect;
use App\AOP\JoinPoint;

#[Aspect]
class ArgumentModificationAspect
{
    #[Before("App\Service\UserService::tellName")]
    public function modifyArguments(JoinPoint $joinPoint): void
    {
        echo "🔧 BEFORE: Modifying arguments...\n";
        echo "   Original arguments: " . json_encode($joinPoint->getArguments()) . "\n";
        
        // Modify the first argument (add a prefix)
        $args = $joinPoint->getArguments();
        if (!empty($args) && is_string($args[0])) {
            $args[0] = "MODIFIED: " . $args[0];
            $joinPoint->setArguments($args);
        }
        
        echo "   Modified arguments: " . json_encode($joinPoint->getArguments()) . "\n";
    }

    #[AfterReturning("App\Service\UserService::tellName")]
    public function modifyResult($result): void
    {
        echo "🔧 AFTER RETURNING: Intercepting result...\n";
        echo "   Original result: " . json_encode($result) . "\n";
        
        // Note: In this case, tellName returns void, so we can't modify it
        // But this demonstrates the pattern for methods that return values
    }

    #[After("App\Service\UserService::tellName")]
    public function logFinalState(JoinPoint $joinPoint): void
    {
        echo "🔧 AFTER: Final state logging...\n";
        echo "   Final arguments used: " . json_encode($joinPoint->getArguments()) . "\n";
        echo "   Arguments were modified: " . ($joinPoint->areArgumentsModified() ? 'YES' : 'NO') . "\n";
    }
}
