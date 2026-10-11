<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Pointcut;

use Phauthentic\AOP\Proxy\ProxyStrategy;

final class StaticMatchContext
{
    /**
     * @param \ReflectionClass<object> $targetClass
     * @param list<class-string>       $introducedInterfaces interfaces the proxy implements through introductions
     */
    public function __construct(
        public readonly \ReflectionClass $targetClass,
        public readonly ?string $beanName = null,
        public readonly ProxyStrategy $strategy = ProxyStrategy::SUBCLASS,
        public readonly array $introducedInterfaces = [],
    ) {
    }
}
