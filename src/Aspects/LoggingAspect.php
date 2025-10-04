<?php

declare(strict_types=1);

namespace App\Aspects;

use App\AOP\Before;
use App\AOP\Around;
use App\AOP\AfterReturning;
use App\AOP\After;
use App\AOP\Aspect;
use App\AOP\JoinPoint;
use App\AOP\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;

#[Aspect]
class LoggingAspect
{
    private LoggerInterface $logger;

    public function __construct()
    {
        $this->logger = LoggerFactory::createComponentLogger('LoggingAspect');
    }

    #[Before("App\Service\UserService::*")]
    public function beforeUserServiceMethod(JoinPoint $joinPoint): void
    {
        $this->logger->info('UserService method execution started', [
            'method' => $joinPoint->getSignature(),
            'arguments' => $joinPoint->getArguments()
        ]);
        
        // Example: Modify arguments if needed
        // $joinPoint->setArgument(0, 'Modified: ' . $joinPoint->getArgument(0));
    }

    #[Around("App\Service\UserService::tellName")]
    public function aroundTellNameMethod(JoinPoint $joinPoint)
    {
        $this->logger->info('Intercepting tellName method call', [
            'method' => $joinPoint->getSignature(),
            'arguments' => $joinPoint->getArguments()
        ]);
        
        // Execute the original method
        $result = $joinPoint->proceed();
        
        $this->logger->info('tellName method execution completed', [
            'result' => $result
        ]);
        
        return $result;
    }

    #[AfterReturning("App\Service\UserService::tellName")]
    public function afterReturningTellNameMethod($result)
    {
        $this->logger->info('tellName method returned successfully', [
            'return_value' => $result
        ]);
        
        // Return the result unchanged for logging-only advice
        return $result;
    }

    #[After("App\Service\UserService::*")]
    public function afterUserServiceMethod(JoinPoint $joinPoint): void
    {
        $this->logger->info('UserService method execution completed', [
            'method' => $joinPoint->getSignature(),
            'final_arguments' => $joinPoint->getArguments()
        ]);
    }
}
