<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Pointcut\Expression\Ast;

final readonly class ThisNode implements NodeInterface
{
    public function __construct(public TypePatternNode|BindingCandidateNode $subject)
    {
    }
}
