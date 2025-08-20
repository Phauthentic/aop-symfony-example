<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Simple test service to verify AOP functionality
 */
class TestService
{
    public function getMessage(): string
    {
        return "Hello from TestService!";
    }

    public function processData(string $data): string
    {
        return "Processed: " . $data;
    }
}
