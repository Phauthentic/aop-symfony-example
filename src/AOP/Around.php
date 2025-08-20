<?php

declare(strict_types=1);

namespace App\AOP;

use Attribute;

/**
 * Around advice - executed around the join point
 * Equivalent to Spring's @Around
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Around extends Advice {}
