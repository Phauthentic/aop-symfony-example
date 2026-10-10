<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Fixtures\Hierarchy;

interface ReadableInterface
{
    public function find(int $id): ?object;
}
