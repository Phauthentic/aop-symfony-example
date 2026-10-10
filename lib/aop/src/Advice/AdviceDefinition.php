<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Advice;

final readonly class AdviceDefinition
{
    /**
     * @param class-string $aspectClass
     */
    public function __construct(
        public string $aspectClass,
        public string $methodName,
        public AdviceType $type,
        public string $expression,
        public ?string $returning,
        public ?string $throwing,
        public int $declarationIndex,
    ) {
    }
}
