<?php

declare(strict_types=1);

namespace App\AOP;

/**
 *
 */
class Pointcut
{
    public function __construct(
        private string $class,
        private string $method
    )
    {

    }

    public function matches(string $class, string $method): bool
    {
        return $this->class === $class && $this->method === $method;
    }

    public function getClass(): ?string
    {
        return $this->class;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }
}