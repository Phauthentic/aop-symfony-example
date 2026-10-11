<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Symfony\Tests\Fixtures\Service;

interface SelfCallingInterface
{
    public function outer(): string;

    public function inner(): string;
}
