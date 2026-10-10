<?php

declare(strict_types=1);

namespace App\Demo;

final class Trace
{
    /** @var list<string> */
    public array $lines = [];

    public function add(string $line): void
    {
        $this->lines[] = $line;
    }
}
