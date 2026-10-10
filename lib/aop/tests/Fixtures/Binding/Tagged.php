<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Fixtures\Binding;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final class Tagged
{
    public function __construct(public string $label = '')
    {
    }
}
