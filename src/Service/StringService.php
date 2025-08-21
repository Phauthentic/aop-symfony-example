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
}

