<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Pointcut\Expression\Ast;

final readonly class ParamListNode implements NodeInterface
{
    /**
     * @param list<TypePatternNode|WildcardParamNode|BindingCandidateNode> $params
     * @param bool                                                         $trailingEllipsis `..` after the listed params (or alone)
     */
    public function __construct(
        public array $params,
        public bool $trailingEllipsis = false,
    ) {
    }
}
