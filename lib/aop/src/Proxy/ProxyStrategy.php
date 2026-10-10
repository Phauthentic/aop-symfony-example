<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Proxy;

enum ProxyStrategy: string
{
    case SUBCLASS = 'subclass';
    case INTERFACE = 'interface';
}
