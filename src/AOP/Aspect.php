<?php

declare(strict_types=1);

namespace App\AOP;

use Attribute;

/**
 * Aspect attribute - marks a class as an AOP aspect
 * Equivalent to Spring's @Aspect
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Aspect
{
    public function __construct() {}
}
