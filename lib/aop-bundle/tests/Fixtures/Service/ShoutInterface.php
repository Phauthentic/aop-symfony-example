<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Symfony\Tests\Fixtures\Service;

interface ShoutInterface
{
    public function shout(string $text): string;
}
