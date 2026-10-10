<?php

declare(strict_types=1);

namespace Phauthentic\AOP\JoinPoint;

interface ProceedingJoinPointInterface extends JoinPointInterface
{
    /**
     * @param array<int|string, mixed>|null $args replaces the arguments for the remaining chain when given
     */
    public function proceed(?array $args = null): mixed;
}
