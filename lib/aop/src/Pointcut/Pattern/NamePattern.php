<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Pointcut\Pattern;

/**
 * Glob with `*` only (method names, bean ids).
 */
final class NamePattern
{
    private readonly string $regex;

    public function __construct(string $pattern)
    {
        $this->regex = '/^'.str_replace('\*', '.*', preg_quote($pattern, '/')).'$/D';
    }

    public function matches(string $name): bool
    {
        return 1 === preg_match($this->regex, $name);
    }
}
