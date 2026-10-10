<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Pointcut;

use Phauthentic\AOP\Proxy\ProxyStrategy;

final class StaticMatchContext
{
    /**
     * @param \ReflectionClass<object> $targetClass
     */
    public function __construct(
        public readonly \ReflectionClass $targetClass,
        public readonly ?string $beanName = null,
        public readonly ProxyStrategy $strategy = ProxyStrategy::SUBCLASS,
    ) {
    }
}
