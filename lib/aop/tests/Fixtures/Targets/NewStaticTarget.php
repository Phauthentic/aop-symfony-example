<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Fixtures\Targets;

class NewStaticTarget
{
    final public function __construct()
    {
    }

    public function withNewStatic(): static
    {
        return new static();
    }

    public function name(): string
    {
        return 'new-static';
    }
}
