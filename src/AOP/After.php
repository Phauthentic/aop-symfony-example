<?php

declare(strict_types=1);

namespace App\AOP;

use Attribute;

/**
 * After advice - executed after the join point (finally)
 * Equivalent to Spring's @After
 */
#[Attribute(Attribute::TARGET_METHOD)]
class After extends Advice {}
