<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Services\Payments\StripeCardTerminal;
use Tests\Support\RecordingStripeClient;
use Tests\TestCase;

class StripeCardTerminalTest extends TestCase
{
    public function test_payment_is_card_present_for_the_order_total_and_idempotent(): void
    {
        $stripe = new RecordingStripeClient(['post /v1/payment_intents' => ['object' => 'payment_intent', 'id' => 'pi_tap_1']]);
        $order = new Order(['total_cents' => 1900, 'event_id' => 3]);
        $order->id = 42;

        $id = (new StripeCardTerminal($stripe))->createPayment($order);

        $this->assertSame('pi_tap_1', $id);
        $request = $stripe->requests[0];
        $this->assertSame(1900, $request['params']['amount']);
        $this->assertSame('eur', $request['params']['currency']);
        $this->assertSame(['card_present'], $request['params']['allowed_payment_method_types']);
        $this->assertSame('42', $request['params']['metadata']['order_id']);
        $this->assertSame('pronto-tap-42', $request['opts']['idempotency_key']);
    }

    public function test_cancel_reports_money_already_taken(): void
    {
        $stripe = new RecordingStripeClient(['get /v1/payment_intents/pi_tap_1' => ['object' => 'payment_intent', 'id' => 'pi_tap_1', 'status' => 'succeeded']]);
        $order = new Order(['stripe_payment_intent_id' => 'pi_tap_1']);

        $this->assertFalse((new StripeCardTerminal($stripe))->cancel($order));
        $this->assertCount(1, $stripe->requests);
    }

    public function test_connection_token(): void
    {
        $stripe = new RecordingStripeClient(['post /v1/terminal/connection_tokens' => ['object' => 'terminal.connection_token', 'secret' => 'pst_test_1']]);

        $this->assertSame('pst_test_1', (new StripeCardTerminal($stripe))->connectionToken());
    }
}
