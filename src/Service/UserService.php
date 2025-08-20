<?php

declare(strict_types=1);

namespace App\Service;

/**
 *
 */
class UserService
{
    public function tellName(string $test): void
    {
        echo "Executing test() and printing:\n";
        echo $test;
    }
}
