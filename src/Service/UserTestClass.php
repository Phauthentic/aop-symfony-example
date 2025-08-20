<?php

declare(strict_types=1);

namespace App\Service;

/**
 *
 */
class UserTestClass
{
    public function test(string $test): void
    {
        echo "Executing test() and printing:\n";
        echo $test;
    }
}
