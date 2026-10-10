<?php

declare(strict_types=1);

namespace App\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD)]
final class Retry
{
    public function __construct(public readonly int $attempts = 3)
    {
    }
}
