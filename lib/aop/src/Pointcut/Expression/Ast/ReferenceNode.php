<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Pointcut\Expression\Ast;

final readonly class ReferenceNode implements NodeInterface
{
    public function __construct(public ?string $className, public string $methodName)
    {
    }
}
