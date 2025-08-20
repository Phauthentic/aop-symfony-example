<?php

declare(strict_types=1);

namespace App\AOP\ClassFilter;

use App\AOP\ClassFilter;

/**
 * Class filter that matches all classes
 * Used when we don't want to filter by class
 */
class AllClassFilter implements ClassFilter
{
    public function matches(string $className): bool
    {
        return true;
    }
}
