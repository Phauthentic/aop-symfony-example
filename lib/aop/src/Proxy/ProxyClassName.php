<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Proxy;

final class ProxyClassName
{
    public const NAMESPACE_PREFIX = 'Phauthentic\AOP\Generated\\';

    /**
     * `Phauthentic\AOP\Generated\<FQCN>Proxy_<first 12 hex of xxh128(FQCN . strategy . sorted method names [. "@" . salt] [. "+" . introductions])>`.
     *
     * @param list<string>          $methods
     * @param string                $salt          distinguishes proxies of the same class, e.g. per bean name
     * @param array<string, string> $introductions introduced interface => default implementation
     */
    public static function for(string $fqcn, ProxyStrategy $strategy, array $methods, string $salt = '', array $introductions = []): string
    {
        $fqcn = ltrim($fqcn, '\\');
        sort($methods);
        ksort($introductions);
        $seed = $fqcn.$strategy->value.implode(',', $methods).('' === $salt ? '' : '@'.$salt)
            .([] === $introductions ? '' : '+'.http_build_query($introductions));

        return self::NAMESPACE_PREFIX.$fqcn.'Proxy_'.substr(hash('xxh128', $seed), 0, 12);
    }
}
