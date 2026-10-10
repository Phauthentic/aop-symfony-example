<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Fixtures\Hierarchy;

interface RepositoryInterface extends ReadableInterface
{
    public function save(object $entity): void;
}
