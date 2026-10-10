<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Fixtures\Targets;

interface ReaderInterface
{
    public function find(): ?object;
}
