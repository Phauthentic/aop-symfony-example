<?php

declare(strict_types=1);

namespace App\Service;

class StringService
{
    public function processString(string $input): string
    {
        return "Processed: " . $input;
    }
    
    public function reverseString(string $input): string
    {
        return strrev($input);
    }
    
    public function uppercaseString(string $input): string
    {
        return strtoupper($input);
    }
    
    public function riskyStringOperation(string $input): string
    {
        // Simulate a risky operation that might fail
        if (strlen($input) > 10) {
            throw new \InvalidArgumentException("Input string is too long (max 10 characters): " . $input);
        }
        
        if (str_contains($input, 'error')) {
            throw new \RuntimeException("Input contains forbidden word: 'error'");
        }
        
        return "Safely processed: " . $input;
    }
}

