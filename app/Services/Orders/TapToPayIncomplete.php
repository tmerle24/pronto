<?php

namespace App\Services\Orders;

use RuntimeException;

/** Stripe does not (yet) report the tap as paid. */
class TapToPayIncomplete extends RuntimeException
{
    public function __construct(public readonly string $paymentStatus)
    {
        parent::__construct("Tap to Pay not completed: {$paymentStatus}");
    }
}
