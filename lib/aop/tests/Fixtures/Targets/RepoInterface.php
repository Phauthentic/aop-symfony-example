<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Fixtures\Targets;

/**
 * Declares the same method as ReaderInterface with a narrower return type.
 */
interface RepoInterface
{
    public function find(): object;
}
