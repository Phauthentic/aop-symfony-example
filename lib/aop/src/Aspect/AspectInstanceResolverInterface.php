<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Aspect;

interface AspectInstanceResolverInterface
{
    /**
     * @param class-string $aspectClass
     */
    public function get(string $aspectClass): object;
}
