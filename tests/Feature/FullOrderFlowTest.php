<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Event;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Services\Payments\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Order -> Stripe webhook (fake) -> queue -> ready -> guest notified -> picked up.
 */
class FullOrderFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_flow(): void
    {
        $event = Event::factory()->create();
        $pizza = MenuItem::factory()->create();
        $staff = User::factory()->create();

        // guest orders and pays online
        $this->post("/e/{$event->slug}/orders", [
            'lines' => [['id' => $pizza->id, 'quantity' => 2]],
            'payment_method' => 'online',
        ])->assertRedirect();
        $order = Order::query()->sole();
        $this->assertFalse($order->isQueued());

        // not in the queue before payment
        $this->actingAs($staff)->get('/team/queue')->assertInertia(fn ($page) => $page->has('orders', 0));

        // stripe confirms
        $payload = (string) json_encode(['id' => 'evt_1', 'type' => WebhookEvent::PAYMENT_SUCCEEDED, 'data' => ['object' => ['id' => $order->stripe_payment_intent_id]]]);
        $this->call('POST', '/webhooks/stripe', [], [], [], ['HTTP_STRIPE_SIGNATURE' => 'fake-signature', 'CONTENT_TYPE' => 'application/json'], $payload)->assertOk();
        $order->refresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(1, $order->pickup_number);

        // guest asks for a push message
        $this->postJson(route('guest.order.push', $order), ['endpoint' => 'https://push.example/x', 'keys' => ['p256dh' => 'k', 'auth' => 'a']])->assertOk();

        // kitchen: in the queue, two taps to ready
        $this->get('/team/queue')->assertInertia(fn ($page) => $page->has('orders', 1));
        foreach (['in_progress', 'ready'] as $expected) {
            $this->postJson("/team/orders/{$order->id}/advance", ['request_id' => (string) Str::uuid()])
                ->assertJsonPath('order.status', $expected);
        }

        // guest notified (sync queue in tests)
        $this->assertCount(1, $this->push->sent);
        $this->assertNotNull($order->refresh()->customer_notified_at);
        $this->get(route('guest.order', $order))->assertInertia(fn ($page) => $page->where('order.status', 'ready'));

        // picked up
        $this->postJson("/team/orders/{$order->id}/advance", ['request_id' => (string) Str::uuid()])
            ->assertJsonPath('order.status', 'picked_up');
        $this->assertSame(OrderStatus::PickedUp, $order->refresh()->status);
        $this->assertSame(
            ['created', 'payment_changed', 'queued', 'status_changed', 'status_changed', 'customer_notified', 'status_changed'],
            $order->events()->pluck('type')->map->value->all(),
        );
    }
}
