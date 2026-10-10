<?php

declare(strict_types=1);

namespace App\Service;

interface OrderServiceInterface
{
    public function placeOrder(string $sku, int $qty): string;
}
