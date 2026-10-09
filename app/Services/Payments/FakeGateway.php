<?php

namespace App\Services\Payments;

use App\Models\Order;

/**
 * Without Stripe keys (local demo, tests). Payments are completed via the
 * dev-only simulate route, never in production.
 */
class FakeGateway implements PaymentGateway
{
    /** @var list<string> */
    public array $calls = [];

    public function createPayment(Order $order): string
    {
        $this->calls[] = 'create:'.$order->id;

        return 'pi_fake_'.$order->id;
    }

    public function clientSecret(Order $order): string
    {
        return 'fake_secret_'.$order->id;
    }

    public function cancel(Order $order): void
    {
        $this->calls[] = 'cancel:'.$order->id;
    }

    public function refund(Order $order): void
    {
        $this->calls[] = 'refund:'.$order->id;
    }

    public function parseWebhook(string $payload, ?string $signature): WebhookEvent
    {
        if ($signature !== 'fake-signature') {
            throw new InvalidWebhook('Invalid signature.');
        }

        /** @var array{id?: string, type?: string, data?: array{object?: array{id?: string}}} $data */
        $data = json_decode($payload, true) ?: [];

        return new WebhookEvent($data['id'] ?? '', $data['type'] ?? '', $data['data']['object']['id'] ?? null);
    }

    public function isFake(): bool
    {
        return true;
    }
}
