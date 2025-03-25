<?php

declare(strict_types=1);

namespace App\AOP;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Around {
    public function __construct(public readonly string $pointcut) {}
}
