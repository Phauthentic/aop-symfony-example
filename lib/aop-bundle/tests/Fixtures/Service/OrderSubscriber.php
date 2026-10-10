<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Symfony\Tests\Fixtures\Service;

use Phauthentic\AOP\Symfony\Tests\Fixtures\Log;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Final, so only an interface proxy is possible, which would not have the listener method the dispatcher calls.
 */
final class OrderSubscriber implements ShoutInterface, EventSubscriberInterface
{
    public const EVENT = 'fixture.order_placed';

    public static function getSubscribedEvents(): array
    {
        return [self::EVENT => 'onOrderPlaced'];
    }

    public function onOrderPlaced(): void
    {
        Log::add('order placed');
    }

    public function shout(string $text): string
    {
        return strtoupper($text);
    }
}
