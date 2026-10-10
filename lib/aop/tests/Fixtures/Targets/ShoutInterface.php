<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Fixtures\Targets;

interface ShoutInterface
{
    public function shout(string $text): string;
}
