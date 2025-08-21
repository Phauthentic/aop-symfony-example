<?php

declare(strict_types=1);

namespace App\AOP;

use Attribute;

/**
 * AfterThrowing advice - executed when the join point throws an exception
 * Equivalent to Spring's @AfterThrowing
 */
#[Attribute(Attribute::TARGET_METHOD)]
class AfterThrowing extends Advice {}
