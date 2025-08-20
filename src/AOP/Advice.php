<?php

declare(strict_types=1);

namespace App\AOP;

use Attribute;

/**
 * Base class for all advice types in Spring AOP style
 */
abstract class Advice
{
    public function __construct(public readonly string $pointcut) {}
}
