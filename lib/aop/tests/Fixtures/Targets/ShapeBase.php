<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Fixtures\Targets;

abstract class ShapeBase
{
    abstract public function sides(): int;
}
