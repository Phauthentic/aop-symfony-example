<?php

declare(strict_types=1);

namespace App\Service;

interface StringServiceInterface
{
    public function reverse(string $input): string;

    public function upper(string $input): string;

    public function greet(string $name, int $times = 1): string;

    public function flaky(string $input): string;
}
