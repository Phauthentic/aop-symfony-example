<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Proxy;

final class ProxyClassName
{
    public const NAMESPACE_PREFIX = 'Phauthentic\AOP\Generated\\';

    /**
     * `Phauthentic\AOP\Generated\<FQCN>Proxy_<first 12 hex of xxh128(FQCN . strategy . sorted method names [. "@" . salt])>`.
     *
     * @param list<string> $methods
     * @param string       $salt    distinguishes proxies of the same class, e.g. per bean name
     */
    public static function for(string $fqcn, ProxyStrategy $strategy, array $methods, string $salt = ''): string
    {
        $fqcn = ltrim($fqcn, '\\');
        sort($methods);
        $seed = $fqcn.$strategy->value.implode(',', $methods).('' === $salt ? '' : '@'.$salt);

        return self::NAMESPACE_PREFIX.$fqcn.'Proxy_'.substr(hash('xxh128', $seed), 0, 12);
    }
}
