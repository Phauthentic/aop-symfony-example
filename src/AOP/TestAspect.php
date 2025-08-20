<?php

declare(strict_types=1);

namespace App\AOP;

/**
 * Simple test aspect to verify AOP functionality
 */
#[Aspect]
class TestAspect
{
    #[Before("App\Service\TestService::*")]
    public function beforeTestServiceMethod(): void
    {
        // Simple logging - this will be visible in error logs
        error_log('AOP BEFORE: TestService method called');
    }

    #[After("App\Service\TestService")]
    public function afterTestServiceMethod(): void
    {
        error_log('AOP AFTER: TestService method completed');
    }
}
