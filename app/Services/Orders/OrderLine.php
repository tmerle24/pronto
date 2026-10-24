<?php

namespace App\Services\Orders;

final readonly class OrderLine
{
    public function __construct(
        public int $menuItemId,
        public int $quantity,
        // counter only: price set by the team as an exception, null = menu price
        public ?int $unitPriceCents = null,
    ) {}
}
