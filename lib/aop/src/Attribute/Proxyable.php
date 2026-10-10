<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Attribute;

use Phauthentic\AOP\Proxy\ProxyStrategy;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class Proxyable
{
    public function __construct(public readonly ProxyStrategy $strategy)
    {
    }
}
