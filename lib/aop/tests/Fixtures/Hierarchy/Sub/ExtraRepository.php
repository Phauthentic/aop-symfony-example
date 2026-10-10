<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Fixtures\Hierarchy\Sub;

use Phauthentic\AOP\Tests\Fixtures\Hierarchy\UserRepository;

class ExtraRepository extends UserRepository
{
    public function extra(): void
    {
    }
}
