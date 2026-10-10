<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Symfony\Tests\Fixtures\Service;

use Phauthentic\AOP\Symfony\Tests\Fixtures\Log;

final class FinalNoInterfaceService
{
    public function run(): string
    {
        Log::add('run');

        return 'run';
    }

    final public function stop(): string
    {
        return 'stop';
    }
}
