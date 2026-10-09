<?php

namespace App\Services\Payments;

use App\Models\Order;

/** Without Stripe keys (local demo, tests). Tests decide the outcome of a tap. */
class FakeCardTerminal implements CardTerminal
{
    /** @var list<string> */
    public array $calls = [];

    /** @var array<int, string> order id => payment status */
    public array $statuses = [];

    /** @var array<int, int> order id => charged amount, defaults to the order total */
    public array $amounts = [];

    public function connectionToken(): string
    {
        $this->calls[] = 'token';

        return 'pst_test_fake';
    }

    public function createPayment(Order $order): string
    {
        $this->calls[] = 'create:'.$order->id;

        return 'pi_fake_tap_'.$order->id;
    }

    public function clientSecret(Order $order): string
    {
        return 'pi_fake_tap_'.$order->id.'_secret_fake';
    }

    public function status(Order $order): TerminalPayment
    {
        return new TerminalPayment(
            (string) $order->stripe_payment_intent_id,
            $this->statuses[$order->id] ?? 'requires_payment_method',
            $this->amounts[$order->id] ?? $order->total_cents,
            'eur',
        );
    }

    public function capture(Order $order): TerminalPayment
    {
        $this->calls[] = 'capture:'.$order->id;
        $this->statuses[$order->id] = TerminalPayment::SUCCEEDED;

        return $this->status($order);
    }

    public function cancel(Order $order): bool
    {
        $this->calls[] = 'cancel:'.$order->id;
        if (($this->statuses[$order->id] ?? null) === TerminalPayment::SUCCEEDED) {
            return false;
        }
        $this->statuses[$order->id] = TerminalPayment::CANCELED;

        return true;
    }

    public function isTestMode(): bool
    {
        return true;
    }

    public function isFake(): bool
    {
        return true;
    }
}
