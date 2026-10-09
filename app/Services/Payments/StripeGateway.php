<?php

namespace App\Services\Payments;

use App\Models\Order;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentIntent;
use Stripe\StripeClient;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeGateway implements PaymentGateway
{
    public function __construct(
        private readonly StripeClient $stripe,
        private readonly string $webhookSecret,
    ) {}

    public function createPayment(Order $order): string
    {
        $intent = $this->stripe->paymentIntents->create([
            'amount' => $order->total_cents,
            'currency' => 'eur',
            'automatic_payment_methods' => ['enabled' => true],
            'description' => sprintf('%s Bestellung %d', config('brand.name'), $order->id),
            'metadata' => ['order_id' => (string) $order->id, 'event_id' => (string) $order->event_id],
        ], ['idempotency_key' => 'pronto-order-'.$order->id]);

        return $intent->id;
    }

    public function clientSecret(Order $order): string
    {
        return (string) $this->intent($order)->client_secret;
    }

    public function cancel(Order $order): void
    {
        $intent = $this->intent($order);
        if (! in_array($intent->status, [PaymentIntent::STATUS_SUCCEEDED, PaymentIntent::STATUS_CANCELED], true)) {
            $intent->cancel();
        }
    }

    public function refund(Order $order): void
    {
        $this->stripe->refunds->create(
            ['payment_intent' => (string) $order->stripe_payment_intent_id],
            ['idempotency_key' => 'pronto-refund-'.$order->id],
        );
    }

    public function parseWebhook(string $payload, ?string $signature): WebhookEvent
    {
        try {
            $event = Webhook::constructEvent($payload, (string) $signature, $this->webhookSecret);
        } catch (SignatureVerificationException|UnexpectedValueException $e) {
            throw new InvalidWebhook($e->getMessage(), previous: $e);
        }

        $object = $event->data->object ?? null;
        $paymentId = $object instanceof PaymentIntent ? $object->id : null;

        return new WebhookEvent($event->id, $event->type, $paymentId);
    }

    public function isFake(): bool
    {
        return false;
    }

    private function intent(Order $order): PaymentIntent
    {
        return $this->stripe->paymentIntents->retrieve((string) $order->stripe_payment_intent_id);
    }
}
