<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Symfony\Tests\Fixtures\Service;

use Phauthentic\AOP\Symfony\Tests\Fixtures\Log;

class FactoryCreatedService implements ShoutInterface
{
    public static function create(): self
    {
        return new self();
    }

    public function shout(string $text): string
    {
        Log::add('factory shout');

        return strtoupper($text).'!';
    }
}
