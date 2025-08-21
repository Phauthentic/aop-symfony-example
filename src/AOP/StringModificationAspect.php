<?php

declare(strict_types=1);

namespace App\AOP;

use App\AOP\Before;
use App\AOP\AfterReturning;
use App\AOP\Aspect;
use App\AOP\JoinPoint;

#[Aspect]
class StringModificationAspect
{
    #[Before("App\Service\StringService::*")]
    public function addBeforePrefix(JoinPoint $joinPoint): void
    {
        $arguments = $joinPoint->getArguments();
        
        if (!empty($arguments) && is_string($arguments[0])) {
            // Add "BEFORE:" prefix to the first string argument
            $arguments[0] = "BEFORE: " . $arguments[0];
            $joinPoint->setArguments($arguments);
            
            //echo "🔧 BEFORE: Added 'BEFORE:' prefix to string argument\n";
            //echo "   Modified argument: " . json_encode($arguments[0]) . "\n";
        }
    }
    
    #[AfterReturning("App\Service\StringService::*")]
    public function addAfterSuffix($result): string
    {
        // Ensure we always return a string
        if ($result === null) {
            return "null :AFTER";
        }
        
        if (is_string($result)) {
            // Add " :AFTER" suffix to the return value
            $modifiedResult = $result . " :AFTER";
            
            //echo "🔧 AFTER RETURNING: Added ':AFTER' suffix to result\n";
           // echo "   Original result: " . json_encode($result) . "\n";
           // echo "   Modified result: " . json_encode($modifiedResult) . "\n";
            
            return $modifiedResult;
        }
        
        // Convert non-string results to string and add suffix
        return (string) $result . " :AFTER";
    }
}

