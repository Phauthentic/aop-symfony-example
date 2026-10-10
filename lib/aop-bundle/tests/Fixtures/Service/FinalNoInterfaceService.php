<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Symfony\Tests\Fixtures\Service;

final class FinalNoInterfaceService
{
    public function run(): string
    {
        return 'run';
    }
}
