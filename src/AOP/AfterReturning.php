<?php

declare(strict_types=1);

namespace App\AOP;

use Attribute;

/**
 * AfterReturning advice - executed after successful return from join point
 * Equivalent to Spring's @AfterReturning
 */
#[Attribute(Attribute::TARGET_METHOD)]
class AfterReturning extends Advice {}
