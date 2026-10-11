<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Symfony\Tests\Fixtures\Service;

class ArgsService
{
    public function scale(int $factor): int
    {
        return $factor * 2;
    }

    public function label(string $name): string
    {
        return 'label:'.$name;
    }
}
