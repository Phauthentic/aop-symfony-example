<?php

declare(strict_types=1);

namespace App\AOP;

/**
 * Filter that restricts matching of a pointcut to a given set of target classes.
 * 
 * This is equivalent to Spring's ClassFilter interface.
 */
interface ClassFilter
{
    /**
     * Should the pointcut apply to the given class?
     * 
     * @param string $className The class name to check
     * @return bool Whether the pointcut should apply
     */
    public function matches(string $className): bool;
}
