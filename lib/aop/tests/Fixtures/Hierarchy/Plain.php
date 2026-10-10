<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Fixtures\Hierarchy;

class Plain
{
    public function find(int $id): ?object
    {
        return null;
    }
}
