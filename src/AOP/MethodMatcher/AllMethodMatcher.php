<?php

declare(strict_types=1);

namespace App\AOP\MethodMatcher;

use App\AOP\MethodMatcher;

/**
 * Method matcher that matches all methods
 * Used when we don't want to filter by method name
 */
class AllMethodMatcher implements MethodMatcher
{
    public function matches(string $methodName): bool
    {
        return true;
    }
}
