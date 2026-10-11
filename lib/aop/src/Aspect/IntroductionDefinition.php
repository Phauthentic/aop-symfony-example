<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Aspect;

final readonly class IntroductionDefinition
{
    /**
     * @param class-string $interface
     * @param class-string $defaultImpl
     */
    public function __construct(
        public string $typePattern,
        public string $interface,
        public string $defaultImpl,
    ) {
    }
}
