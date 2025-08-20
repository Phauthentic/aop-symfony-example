<?php

declare(strict_types=1);

namespace App\AOP;

use Attribute;

/**
 * Loggable annotation - marks methods that should be logged
 * Equivalent to Spring's @Loggable or similar custom annotations
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Loggable {}
