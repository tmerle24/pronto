<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

/** Order became visible in the kitchen queue (paid online, cash or counter). */
class OrderQueued
{
    use Dispatchable;

    public function __construct(public readonly Order $order) {}
}
