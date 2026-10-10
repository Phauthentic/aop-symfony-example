<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Symfony\Tests\Fixtures\Service;

use Phauthentic\AOP\Symfony\Tests\Fixtures\Log;

final class FinalService implements ShoutInterface
{
    public function shout(string $text): string
    {
        Log::add('shout');

        return strtoupper($text);
    }
}
