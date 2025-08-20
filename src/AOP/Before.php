<?php

declare(strict_types=1);

namespace App\AOP;

use Attribute;

/**
 * Before advice - executed before the join point
 * Equivalent to Spring's @Before
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Before extends Advice {}
