<?php

namespace App\Services\Payments;

use App\Models\Order;
use Stripe\Exception\InvalidRequestException;
use Stripe\PaymentIntent;
use Stripe\StripeClient;

class StripeCardTerminal implements CardTerminal
{
    public function __construct(private readonly StripeClient $stripe) {}

    public function connectionToken(): string
    {
        // location is passed by the app on connect, not here
        return (string) $this->stripe->terminal->connectionTokens->create()->secret;
    }

    public function createPayment(Order $order): string
    {
        $intent = $this->stripe->paymentIntents->create([
            'amount' => $order->total_cents,
            'currency' => 'eur',
            // API 2026-09-30: replaces payment_method_types
            'allowed_payment_method_types' => ['card_present'],
            'capture_method' => 'automatic',
            'description' => sprintf('%s Theke %d', config('brand.name'), $order->id),
            'metadata' => ['order_id' => (string) $order->id, 'event_id' => (string) $order->event_id, 'channel' => 'tap_to_pay'],
        ], ['idempotency_key' => 'pronto-tap-'.$order->id]);

        return $intent->id;
    }

    public function clientSecret(Order $order): string
    {
        return (string) $this->intent($order)->client_secret;
    }

    public function status(Order $order): TerminalPayment
    {
        return $this->view($this->intent($order));
    }

    public function capture(Order $order): TerminalPayment
    {
        $intent = $this->stripe->paymentIntents->capture(
            (string) $order->stripe_payment_intent_id,
            [],
            ['idempotency_key' => 'pronto-tap-capture-'.$order->id],
        );

        return $this->view($intent);
    }

    public function cancel(Order $order): bool
    {
        $intent = $this->intent($order);
        if ($intent->status === PaymentIntent::STATUS_SUCCEEDED) {
            return false;
        }
        if ($intent->status === PaymentIntent::STATUS_CANCELED) {
            return true;
        }

        try {
            $intent->cancel();
        } catch (InvalidRequestException) {
            // raced with a tap that just went through
            return $this->intent($order)->status !== PaymentIntent::STATUS_SUCCEEDED;
        }

        return true;
    }

    public function isTestMode(): bool
    {
        return str_contains((string) config('services.stripe.secret'), '_test_');
    }

    public function isFake(): bool
    {
        return false;
    }

    private function intent(Order $order): PaymentIntent
    {
        return $this->stripe->paymentIntents->retrieve((string) $order->stripe_payment_intent_id);
    }

    private function view(PaymentIntent $intent): TerminalPayment
    {
        return new TerminalPayment($intent->id, $intent->status, $intent->amount, $intent->currency);
    }
}
