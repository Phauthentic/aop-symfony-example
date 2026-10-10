<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Symfony\Tests\Fixtures\Excluded;

class ExcludedService
{
    public function run(): string
    {
        return 'run';
    }
}
