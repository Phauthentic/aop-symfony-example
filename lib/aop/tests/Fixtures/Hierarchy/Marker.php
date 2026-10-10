<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Fixtures\Hierarchy;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Marker
{
    public function __construct(public string $label = '')
    {
    }
}
