<?php

namespace App\Services\Payments;

use App\Models\Order;

/** Stripe Terminal for Tap to Pay on the counter phone. */
interface CardTerminal
{
    /** Short-lived token the native SDK needs to talk to Stripe. */
    public function connectionToken(): string;

    /** card_present payment for the order total, returns its id. */
    public function createPayment(Order $order): string;

    public function clientSecret(Order $order): string;

    public function status(Order $order): TerminalPayment;

    /** Only for payments waiting for capture. */
    public function capture(Order $order): TerminalPayment;

    /** False when the money was already taken, then the order must be completed instead. */
    public function cancel(Order $order): bool;

    /** Test keys: the app uses Stripe's simulated reader. */
    public function isTestMode(): bool;

    public function isFake(): bool;
}
