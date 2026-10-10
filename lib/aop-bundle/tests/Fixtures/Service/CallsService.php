<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Symfony\Tests\Fixtures\Service;

use Phauthentic\AOP\Symfony\Tests\Fixtures\Log;

class CallsService
{
    private string $prefix = '';

    public function setPrefix(string $prefix): void
    {
        $this->prefix = $prefix;
    }

    public function decorate(string $text): string
    {
        Log::add('decorate');

        return $this->prefix.$text;
    }
}
