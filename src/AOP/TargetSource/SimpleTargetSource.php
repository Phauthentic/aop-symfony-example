<?php

declare(strict_types=1);

namespace App\AOP\TargetSource;

use App\AOP\TargetSource;

/**
 * Simple target source that holds a single target instance.
 * 
 * This is equivalent to Spring's SingletonTargetSource.
 */
class SimpleTargetSource implements TargetSource
{
    private object $target;
    private string $targetClass;

    public function __construct(object $target)
    {
        $this->target = $target;
        $this->targetClass = get_class($target);
    }

    public function getTargetClass(): string
    {
        return $this->targetClass;
    }

    public function isStatic(): bool
    {
        return true;
    }

    public function getTarget(): object
    {
        return $this->target;
    }

    public function releaseTarget(object $target): void
    {
        // No cleanup needed for simple target source
    }
}
