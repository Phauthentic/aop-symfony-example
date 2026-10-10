<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Fixtures\Targets;

use Phauthentic\AOP\Attribute\Proxyable;
use Phauthentic\AOP\Proxy\ProxyStrategy;
use Phauthentic\AOP\Tests\Fixtures\Log;

#[Proxyable(ProxyStrategy::INTERFACE)]
class InterfaceTypedTarget implements ShoutInterface
{
    public function shout(string $text): string
    {
        Log::add('shout');

        return strtoupper($text).'!';
    }
}
