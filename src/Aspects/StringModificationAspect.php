<?php

declare(strict_types=1);

namespace App\Aspects;

use App\AOP\Before;
use App\AOP\AfterReturning;
use App\AOP\AfterThrowing;
use App\AOP\Aspect;
use App\AOP\JoinPoint;

#[Aspect]
class StringModificationAspect
{
    #[Before("*::*")]
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
    
    #[AfterReturning("*::*")]
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
    
    #[AfterThrowing("*::*")]
    public function handleStringServiceException(\Throwable $exception): void
    {
        echo "🚨 AFTER THROWING: Method threw an exception\n";
        echo "   Exception: " . get_class($exception) . "\n";
        echo "   Message: " . $exception->getMessage() . "\n";
        echo "   File: " . $exception->getFile() . ":" . $exception->getLine() . "\n";
        
        // You could log the exception, send notifications, etc.
        // The exception will be re-thrown after this advice executes
    }
}
