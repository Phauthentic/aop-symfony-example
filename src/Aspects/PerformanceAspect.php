<?php

declare(strict_types=1);

namespace App\Aspects;

use App\AOP\Around;
use App\AOP\Aspect;
use App\AOP\JoinPoint;

#[Aspect]
class PerformanceAspect
{
    #[Around("App\Service\UserService::*")]
    public function measureExecutionTime(JoinPoint $joinPoint)
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        
        echo "⏱️  PERFORMANCE: Starting execution measurement\n";
        echo "   Method: " . $joinPoint->getSignature() . "\n";
        echo "   Arguments: " . json_encode($joinPoint->getArguments()) . "\n";
        echo "   Start time: " . date('H:i:s.') . substr(microtime(), 2, 3) . "\n";
        echo "   Start memory: " . number_format($startMemory) . " bytes\n";
        
        try {
            // Execute the original method with potentially modified arguments
            $result = $joinPoint->proceed();
            
            $endTime = microtime(true);
            $endMemory = memory_get_usage();
            $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
            $memoryUsed = $endMemory - $startMemory;
            
            echo "⏱️  PERFORMANCE: Execution completed successfully\n";
            echo "   Execution time: " . number_format($executionTime, 2) . " ms\n";
            echo "   Memory used: " . number_format($memoryUsed) . " bytes\n";
            echo "   End time: " . date('H:i:s.') . substr(microtime(), 2, 3) . "\n";
            
            return $result;
            
        } catch (\Throwable $e) {
            $endTime = microtime(true);
            $executionTime = ($endTime - $startTime) * 1000;
            
            echo "⏱️  PERFORMANCE: Execution failed after " . number_format($executionTime, 2) . " ms\n";
            echo "   Error: " . $e->getMessage() . "\n";
            
            throw $e; // Re-throw the exception
        }
    }
}
