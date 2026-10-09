<?php

namespace App\Services\Payments;

class OnlinePayments
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    /** Fake gateway is for local demos; production without Stripe keys means cash only. */
    public function enabled(): bool
    {
        return ! ($this->gateway->isFake() && app()->isProduction());
    }

    public function simulated(): bool
    {
        return $this->gateway->isFake() && ! app()->isProduction();
    }
}
