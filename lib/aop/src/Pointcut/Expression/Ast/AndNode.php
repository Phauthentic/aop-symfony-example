<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Pointcut\Expression\Ast;

final readonly class AndNode implements NodeInterface
{
    public function __construct(public NodeInterface $left, public NodeInterface $right)
    {
    }
}
