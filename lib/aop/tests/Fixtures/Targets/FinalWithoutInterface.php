<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Fixtures\Targets;

final class FinalWithoutInterface
{
    public function run(): string
    {
        return 'run';
    }
}
