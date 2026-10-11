<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Symfony\Tests\Fixtures\Service;

final class SelfCallingService implements SelfCallingInterface
{
    public function outer(): string
    {
        return 'outer>'.$this->inner();
    }

    public function inner(): string
    {
        return 'inner';
    }
}
