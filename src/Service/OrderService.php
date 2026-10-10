<?php

declare(strict_types=1);

namespace App\Service;

final class OrderService implements OrderServiceInterface
{
    public function placeOrder(string $sku, int $qty): string
    {
        return sprintf('Order %s x%d placed', $sku, $qty);
    }
}
