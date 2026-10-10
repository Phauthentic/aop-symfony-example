<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Fixtures\Targets;

use Phauthentic\AOP\Tests\Fixtures\Log;

final class ReaderRepo implements ReaderInterface, RepoInterface
{
    public function find(): object
    {
        Log::add('find');

        return new \stdClass();
    }
}
