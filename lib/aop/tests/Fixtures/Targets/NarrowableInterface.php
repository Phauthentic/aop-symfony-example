<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Fixtures\Targets;

interface NarrowableInterface
{
    public function get(): ?object;
}
