<?php

namespace App\Services\Payments;

/** Whether the counter app may offer Tap to Pay, and how it connects. */
class TapToPay
{
    public function __construct(private readonly CardTerminal $terminal) {}

    public function enabled(): bool
    {
        if ($this->terminal->isFake()) {
            return ! app()->isProduction();
        }

        return filled(config('services.stripe.terminal_location'));
    }

    /**
     * @return array{enabled: bool, location: ?string, simulated: bool}
     */
    public function clientConfig(): array
    {
        return [
            'enabled' => $this->enabled(),
            'location' => config('services.stripe.terminal_location'),
            // test keys: no real card can be charged, Stripe simulates the tap
            'simulated' => $this->terminal->isTestMode(),
        ];
    }
}
