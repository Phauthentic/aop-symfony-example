<?php

declare(strict_types=1);

namespace App\AOP;

use App\AOP\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;

/**
 * Proxy Advice Executor Trait
 * 
 * This trait provides helper methods for executing AOP advice
 * in generated proxy classes. It's used by both dynamic and extending proxies.
 */
trait ProxyAdviceExecutor
{
    private LoggerInterface $logger;

    private function getLogger(): LoggerInterface
    {
        if (!isset($this->logger)) {
            $this->logger = LoggerFactory::createComponentLogger('ProxyAdviceExecutor');
        }
        return $this->logger;
    }

    /**
     * Execute Before advice for a join point
     */
    private function executeBeforeAdvice(JoinPoint $joinPoint): void
    {
        // Get all aspects that have Before advice for this method
        $aspects = $this->getAspectsWithBeforeAdvice($joinPoint);
        
        foreach ($aspects as $aspect) {
            try {
                $aspect->executeBeforeAdvice($joinPoint);
            } catch (\Throwable $e) {
                // Log error but continue with other advice
                $this->getLogger()->error('Error executing Before advice', [
                    'error' => $e->getMessage(),
                    'method' => $joinPoint->getMethod(),
                    'target_class' => $joinPoint->getTargetClass()
                ]);
            }
        }
    }

    /**
     * Execute AfterReturning advice for a join point
     */
    private function executeAfterReturningAdvice(JoinPoint $joinPoint, $result)
    {
        // Get all aspects that have AfterReturning advice for this method
        $aspects = $this->getAspectsWithAfterReturningAdvice($joinPoint);
        
        foreach ($aspects as $aspect) {
            try {
                $result = $aspect->executeAfterReturningAdvice($joinPoint, $result);
            } catch (\Throwable $e) {
                // Log error but continue with other advice
                $this->getLogger()->error('Error executing AfterReturning advice', [
                    'error' => $e->getMessage(),
                    'method' => $joinPoint->getMethod(),
                    'target_class' => $joinPoint->getTargetClass()
                ]);
            }
        }
        
        return $result;
    }

    /**
     * Execute AfterThrowing advice for a join point
     */
    private function executeAfterThrowingAdvice(JoinPoint $joinPoint, \Throwable $exception): void
    {
        // Get all aspects that have AfterThrowing advice for this method
        $aspects = $this->getAspectsWithAfterThrowingAdvice($joinPoint);
        
        foreach ($aspects as $aspect) {
            try {
                $aspect->executeAfterThrowingAdvice($joinPoint, $exception);
            } catch (\Throwable $e) {
                // Log error but continue with other advice
                $this->getLogger()->error('Error executing AfterThrowing advice', [
                    'error' => $e->getMessage(),
                    'method' => $joinPoint->getMethod(),
                    'target_class' => $joinPoint->getTargetClass()
                ]);
            }
        }
    }

    /**
     * Execute After advice for a join point
     */
    private function executeAfterAdvice(JoinPoint $joinPoint): void
    {
        // Get all aspects that have After advice for this method
        $aspects = $this->getAspectsWithAfterAdvice($joinPoint);
        
        foreach ($aspects as $aspect) {
            try {
                $aspect->executeAfterAdvice($joinPoint);
            } catch (\Throwable $e) {
                // Log error but continue with other advice
                $this->getLogger()->error('Error executing After advice', [
                    'error' => $e->getMessage(),
                    'method' => $joinPoint->getMethod(),
                    'target_class' => $joinPoint->getTargetClass()
                ]);
            }
        }
    }

    /**
     * Get aspects with Before advice for a join point
     */
    private function getAspectsWithBeforeAdvice(JoinPoint $joinPoint): array
    {
        return $this->getAspectsByAdviceType($joinPoint, 'Before');
    }

    /**
     * Get aspects with AfterReturning advice for a join point
     */
    private function getAspectsWithAfterReturningAdvice(JoinPoint $joinPoint): array
    {
        return $this->getAspectsByAdviceType($joinPoint, 'AfterReturning');
    }

    /**
     * Get aspects with AfterThrowing advice for a join point
     */
    private function getAspectsWithAfterThrowingAdvice(JoinPoint $joinPoint): array
    {
        return $this->getAspectsByAdviceType($joinPoint, 'AfterThrowing');
    }

    /**
     * Get aspects with After advice for a join point
     */
    private function getAspectsWithAfterAdvice(JoinPoint $joinPoint): array
    {
        return $this->getAspectsByAdviceType($joinPoint, 'After');
    }

    /**
     * Get aspects by advice type for a join point
     */
    private function getAspectsByAdviceType(JoinPoint $joinPoint, string $adviceType): array
    {
        // This is a simplified implementation
        // In a real system, you'd query the AOP framework for matching aspects
        
        $aspects = [];
        $methodName = $joinPoint->getMethod();
        $targetClass = $joinPoint->getTargetClass();
        
        // Scan for aspects that match this method and advice type
        $aspectsDir = __DIR__ . '/../Aspects';
        $files = glob($aspectsDir . '/*.php');
        
        foreach ($files as $file) {
            $aspectClassName = 'App\\Aspects\\' . basename($file, '.php');
            
            if (class_exists($aspectClassName)) {
                $reflection = new \ReflectionClass($aspectClassName);
                $attributes = $reflection->getAttributes(\App\AOP\Aspect::class);
                
                if (!empty($attributes)) {
                    // Check if this aspect has the specified advice type for this method
                    if ($this->aspectHasAdviceForMethod($reflection, $adviceType, $targetClass, $methodName)) {
                        $aspects[] = new $aspectClassName();
                    }
                }
            }
        }
        
        return $aspects;
    }

    /**
     * Check if an aspect has advice of the specified type for a method
     */
    private function aspectHasAdviceForMethod(\ReflectionClass $aspectClass, string $adviceType, string $targetClass, string $methodName): bool
    {
        $adviceClassMap = [
            'Before' => \App\AOP\Before::class,
            'AfterReturning' => \App\AOP\AfterReturning::class,
            'AfterThrowing' => \App\AOP\AfterThrowing::class,
            'After' => \App\AOP\After::class,
            'Around' => \App\AOP\Around::class,
        ];
        
        $adviceClass = $adviceClassMap[$adviceType] ?? null;
        if (!$adviceClass) {
            return false;
        }
        
        foreach ($aspectClass->getMethods() as $method) {
            $attributes = $method->getAttributes($adviceClass);
            
            if (!empty($attributes)) {
                $attribute = $attributes[0];
                $advice = $attribute->newInstance();
                
                // Check if the pointcut matches the target class and method
                if ($this->pointcutMatches($advice->pointcut, $targetClass, $methodName)) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * Check if a pointcut expression matches a target class and method
     */
    private function pointcutMatches(string $pointcut, string $targetClass, string $methodName): bool
    {
        // Simple pointcut matching - in a real system you'd use a proper pointcut parser
        
        // Check for exact class::method matches
        if (strpos($pointcut, $targetClass . '::' . $methodName) !== false) {
            return true;
        }
        
        // Check for class::* matches
        if (strpos($pointcut, $targetClass . '::*') !== false) {
            return true;
        }
        
        // Check for wildcard matches
        if (strpos($pointcut, '*::*') !== false) {
            return true;
        }
        
        return false;
    }
}
