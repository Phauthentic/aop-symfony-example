<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Fixtures\Targets;

abstract class AbstractTarget
{
    abstract public function todo(): string;

    public function done(): string
    {
        return 'done';
    }
}
