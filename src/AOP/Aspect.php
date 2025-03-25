<?php

declare(strict_types=1);

namespace App\AOP;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Aspect {}
