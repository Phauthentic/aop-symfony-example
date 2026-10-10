<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Fixtures\Service;

final class UserService
{
    public function findUser(int $id, string ...$flags): void
    {
    }
}
