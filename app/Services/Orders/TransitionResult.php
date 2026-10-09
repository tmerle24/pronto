<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderEvent;

final readonly class TransitionResult
{
    public function __construct(
        public Order $order,
        public ?OrderEvent $event,
        // same client request id seen before, nothing changed
        public bool $duplicate = false,
    ) {}
}
