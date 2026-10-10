<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Fixtures\Binding;

interface ServiceInterface
{
    public function inherited(): string;
}
