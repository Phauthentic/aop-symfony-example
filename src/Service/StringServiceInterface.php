<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Interface for string processing services
 * This allows AOP proxies to implement the same contract as the original service
 */
interface StringServiceInterface
{
    public function processString(string $input): string;
    
    public function reverseString(string $input): string;
    
    public function uppercaseString(string $input): string;
    
    public function riskyStringOperation(string $input): string;
}
