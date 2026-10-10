<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Symfony\Tests\Fixtures\Service;

use Phauthentic\AOP\Symfony\Tests\Fixtures\Log;

class LazyService
{
    public function __construct()
    {
        Log::add('lazy constructed');
    }

    public function ping(): string
    {
        Log::add('ping');

        return 'pong';
    }
}
