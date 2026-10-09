<?php

namespace App\Services\Orders;

final readonly class OrderLine
{
    public function __construct(
        public int $menuItemId,
        public int $quantity,
    ) {}
}
