<?php

namespace App\Services\Payments;

use App\Models\Order;

interface PaymentGateway
{
    /** Creates the provider payment for an online order, returns its id. */
    public function createPayment(Order $order): string;

    /** Data the browser needs to mount the payment form. */
    public function clientSecret(Order $order): string;

    public function cancel(Order $order): void;

    public function refund(Order $order): void;

    /**
     * Verifies the signature and returns the event.
     *
     * @throws InvalidWebhook
     */
    public function parseWebhook(string $payload, ?string $signature): WebhookEvent;

    /** Fake gateway only: lets local demos complete a payment without Stripe. */
    public function isFake(): bool;
}
