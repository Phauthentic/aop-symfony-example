<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Symfony\Tests\Fixtures\Service;

use Phauthentic\AOP\Symfony\Tests\Fixtures\Log;

class PlainService
{
    public function greet(string $name): string
    {
        Log::add('greet');

        return 'Hello '.$name;
    }

    public function normalize(string &$name): void
    {
        $name = trim($name);
    }
}
