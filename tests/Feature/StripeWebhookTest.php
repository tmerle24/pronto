<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Event;
use App\Models\Order;
use App\Services\Payments\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    /** @return TestResponse<Response> */
    private function webhook(string $eventId, string $type, ?string $paymentId, string $signature = 'fake-signature'): TestResponse
    {
        $payload = json_encode(['id' => $eventId, 'type' => $type, 'data' => ['object' => ['id' => $paymentId]]]);

        return $this->call('POST', '/webhooks/stripe', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], (string) $payload);
    }

    private function pendingOrder(): Order
    {
        return Order::factory()->for(Event::factory())->create(['stripe_payment_intent_id' => 'pi_123']);
    }

    public function test_payment_succeeded_marks_paid_and_releases_into_queue(): void
    {
        $order = $this->pendingOrder();

        $this->webhook('evt_1', WebhookEvent::PAYMENT_SUCCEEDED, 'pi_123')->assertOk();

        $order->refresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertNotNull($order->paid_at);
        $this->assertTrue($order->isQueued());
        $this->assertSame(1, $order->pickup_number);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $order = $this->pendingOrder();

        $this->webhook('evt_1', WebhookEvent::PAYMENT_SUCCEEDED, 'pi_123', 'forged')->assertStatus(400);

        $this->assertSame(PaymentStatus::Pending, $order->refresh()->payment_status);
    }

    public function test_same_event_twice_is_processed_once(): void
    {
        $order = $this->pendingOrder();

        $this->webhook('evt_1', WebhookEvent::PAYMENT_SUCCEEDED, 'pi_123')->assertOk();
        $this->webhook('evt_1', WebhookEvent::PAYMENT_SUCCEEDED, 'pi_123')->assertOk()->assertSee('duplicate');

        $this->assertSame(1, $order->event->refresh()->last_pickup_number);
    }

    public function test_second_success_event_for_same_payment_keeps_number(): void
    {
        $order = $this->pendingOrder();

        $this->webhook('evt_1', WebhookEvent::PAYMENT_SUCCEEDED, 'pi_123');
        $this->webhook('evt_2', WebhookEvent::PAYMENT_SUCCEEDED, 'pi_123');

        $this->assertSame(1, $order->refresh()->pickup_number);
        $this->assertSame(1, $order->event->refresh()->last_pickup_number);
    }

    public function test_payment_failed_keeps_order_pending(): void
    {
        $order = $this->pendingOrder();

        $this->webhook('evt_1', WebhookEvent::PAYMENT_FAILED, 'pi_123')->assertOk();

        $this->assertSame(PaymentStatus::Pending, $order->refresh()->payment_status);
        $this->assertFalse($order->isQueued());
    }

    public function test_other_event_types_are_ignored(): void
    {
        $this->webhook('evt_1', 'charge.updated', 'ch_1')->assertOk()->assertSee('ignored');
    }

    public function test_late_payment_after_expiry_revives_the_order(): void
    {
        $order = $this->pendingOrder();
        $this->travel(31)->minutes();
        $this->assertSame(0, Artisan::call('orders:expire-unpaid'));
        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);

        $this->webhook('evt_1', WebhookEvent::PAYMENT_SUCCEEDED, 'pi_123')->assertOk();

        $order->refresh();
        $this->assertSame(OrderStatus::New, $order->status);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertTrue($order->isQueued());
    }
}
