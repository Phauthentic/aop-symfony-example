<?php

declare(strict_types=1);

namespace App\Aspects;

use App\AOP\Before;
use App\AOP\AfterReturning;
use App\AOP\Around;
use App\AOP\Aspect;
use App\AOP\JoinPoint;

#[Aspect]
class FlowControlAspect
{
    /**
     * Cache aspect - returns cached value if available, otherwise allows execution
     */
    #[Before("App\Service\StringService::processString")]
    public function checkCache(JoinPoint $joinPoint): void
    {
        $arguments = $joinPoint->getArguments();
        $cacheKey = 'processString_' . md5(serialize($arguments));
        
        // Simulate cache check
        if ($this->isCached($cacheKey)) {
            $cachedValue = $this->getCachedValue($cacheKey);
            echo "🔍 CACHE HIT: Returning cached value for '{$arguments[0]}'\n";
            
            // Set early return - this will skip method execution and return immediately
            $joinPoint->setEarlyReturn($cachedValue);
        } else {
            echo "🔍 CACHE MISS: Will execute method for '{$arguments[0]}'\n";
        }
    }
    
    /**
     * Validation aspect - prevents execution if validation fails
     */
    #[Before("App\Service\StringService::uppercaseString")]
    public function validateInput(JoinPoint $joinPoint): void
    {
        $arguments = $joinPoint->getArguments();
        $input = $arguments[0];
        
        if (empty(trim($input))) {
            echo "❌ VALIDATION FAILED: Empty string not allowed\n";
            
            // Set early return with error message
            $joinPoint->setEarlyReturn("ERROR: Empty string not allowed");
        } elseif (strlen($input) > 100) {
            echo "❌ VALIDATION FAILED: String too long (max 100 characters)\n";
            
            // Set early return with error message
            $joinPoint->setEarlyReturn("ERROR: String too long (max 100 characters)");
        } else {
            echo "✅ VALIDATION PASSED: Input '{$input}' is valid\n";
        }
    }
    
    /**
     * Rate limiting aspect - skips method execution if rate limit exceeded
     */
    #[Before("App\Service\StringService::reverseString")]
    public function checkRateLimit(JoinPoint $joinPoint): void
    {
        $methodName = $joinPoint->getMethod();
        
        if ($this->isRateLimited($methodName)) {
            echo "⏱️  RATE LIMIT: Method {$methodName} is rate limited\n";
            
            // Skip method execution but allow AfterReturning advice to run
            $joinPoint->skipMethodExecution();
        } else {
            echo "⏱️  RATE LIMIT: Method {$methodName} execution allowed\n";
        }
    }
    
    /**
     * Around advice that can completely control execution
     */
    #[Around("App\Service\StringService::riskyStringOperation")]
    public function aroundRiskyOperation(JoinPoint $joinPoint)
    {
        $arguments = $joinPoint->getArguments();
        $input = $arguments[0];
        
        echo "🔄 AROUND: Intercepting riskyStringOperation with input '{$input}'\n";
        
        // Check if we should allow this operation
        if (str_contains(strtolower($input), 'dangerous')) {
            echo "🚫 AROUND: Blocking dangerous operation\n";
            return "BLOCKED: Operation contains 'dangerous' keyword";
        }
        
        // Check if we should use cached result
        if (strlen($input) < 5) {
            echo "🔄 AROUND: Using cached result for short input\n";
            return "CACHED: " . strtoupper($input);
        }
        
        echo "🔄 AROUND: Proceeding with normal execution\n";
        
        // Execute the original method
        $result = $joinPoint->proceed();
        
        echo "🔄 AROUND: Method completed, result: {$result}\n";
        
        return $result;
    }
    
    /**
     * AfterReturning advice that can modify results
     */
    #[AfterReturning("App\Service\StringService::*")]
    public function enhanceResults($result)
    {
        if (is_string($result) && !str_starts_with($result, 'ERROR:') && !str_starts_with($result, 'BLOCKED:') && !str_starts_with($result, 'CACHED:')) {
            echo "✨ ENHANCING: Adding enhancement to result\n";
            return $result . " [Enhanced by FlowControlAspect]";
        }
        
        return $result;
    }
    
    // Helper methods for demo purposes
    
    private function isCached(string $key): bool
    {
        // Simulate cache check - return true for inputs containing "cached"
        return str_contains($key, 'cached');
    }
    
    private function getCachedValue(string $key): string
    {
        // Simulate cached value
        return "CACHED_RESULT_" . substr($key, -8);
    }
    
    private function isRateLimited(string $methodName): bool
    {
        // Simulate rate limiting - limit reverseString to every other call
        static $callCount = 0;
        $callCount++;
        
        return $methodName === 'reverseString' && ($callCount % 2 === 0);
    }
}
