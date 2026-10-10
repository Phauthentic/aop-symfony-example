<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Fixtures\Targets;

interface NarrowedInterface extends NarrowableInterface
{
    public function get(): object;
}
