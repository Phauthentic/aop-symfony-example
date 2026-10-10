<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Proxy;

final class ProxyClassName
{
    public const NAMESPACE_PREFIX = 'Phauthentic\AOP\Generated\\';

    /**
     * `Phauthentic\AOP\Generated\<FQCN>Proxy_<first 12 hex of xxh128(FQCN . strategy . sorted method names)>`.
     *
     * @param list<string> $methods
     */
    public static function for(string $fqcn, ProxyStrategy $strategy, array $methods): string
    {
        $fqcn = ltrim($fqcn, '\\');
        sort($methods);

        return self::NAMESPACE_PREFIX.$fqcn.'Proxy_'.substr(hash('xxh128', $fqcn.$strategy->value.implode(',', $methods)), 0, 12);
    }
}
