<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Pointcut;

final readonly class BindingSpec
{
    public function __construct(
        public string $name,
        public BindingKind $kind,
        public ?string $typeRestriction,
    ) {
    }
}
