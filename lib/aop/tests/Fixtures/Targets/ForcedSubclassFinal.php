<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Fixtures\Targets;

use Phauthentic\AOP\Attribute\Proxyable;
use Phauthentic\AOP\Proxy\ProxyStrategy;

#[Proxyable(ProxyStrategy::SUBCLASS)]
final class ForcedSubclassFinal implements ShoutInterface
{
    public function shout(string $text): string
    {
        return $text;
    }
}
