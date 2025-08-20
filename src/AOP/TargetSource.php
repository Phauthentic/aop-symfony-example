<?php

declare(strict_types=1);

namespace App\AOP;

/**
 * A TargetSource is used to obtain the current "target" of an AOP invocation,
 * which will be invoked via reflection if no around advice chooses to end the
 * interceptor chain itself.
 * 
 * This is equivalent to Spring's TargetSource interface.
 */
interface TargetSource
{
    /**
     * Return the type of the target object.
     */
    public function getTargetClass(): string;

    /**
     * Return whether the target is static.
     */
    public function isStatic(): bool;

    /**
     * Return a target instance.
     */
    public function getTarget(): object;

    /**
     * Release the given target object.
     */
    public function releaseTarget(object $target): void;
}
