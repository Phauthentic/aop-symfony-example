<?php

declare(strict_types=1);

namespace App\Service;

use App\Attribute\Retry;

class StringService implements StringServiceInterface
{
    private int $flakyCalls = 0;

    public function reverse(string $input): string
    {
        return strrev($input);
    }

    public function upper(string $input): string
    {
        return strtoupper($input);
    }

    public function greet(string $name, int $times = 1): string
    {
        return implode(' ', array_fill(0, $times, sprintf('Hello, %s!', $name)));
    }

    #[Retry(3)]
    public function flaky(string $input): string
    {
        if (++$this->flakyCalls <= 2) {
            throw new \RuntimeException('flaky failure '.$this->flakyCalls);
        }

        return $input;
    }
}
