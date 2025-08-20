<?php

/**
 * Auto-generated proxy class for UserService
 */

declare(strict_types=1);

namespace App\Service;

/**
 * Proxy class for UserService with injected logging
 */
class UserServiceProxy extends UserService
{
    public function tellName(string $test): void
    {
        echo "PROXY START: tellName method called";
        parent::tellName($test);
        echo "PROXY END: tellName method finished";
    }
}
