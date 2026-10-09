<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Event;
use App\Models\MenuItem;
use App\Models\Order;
use App\Services\Payments\StripeGateway;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Stripe\StripeClient;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private MenuItem $pizza;

    private MenuItem $cola;

    protected function setUp(): void
    {
        parent::setUp();
        $this->event = Event::factory()->create();
        $this->pizza = MenuItem::factory()->create(['price_cents' => 950, 'oven_units' => '1.00']);
        $this->cola = MenuItem::factory()->drink()->create(['price_cents' => 300, 'deposit_cents' => 25]);
    }

    /**
     * @param  array<int, int>  $lines  menu item id => quantity
     * @return array<string, mixed>
     */
    private function payload(array $lines, string $method = 'online'): array
    {
        return [
            'lines' => collect($lines)->map(fn ($q, $id) => ['id' => $id, 'quantity' => $q])->values()->all(),
            'payment_method' => $method,
        ];
    }

    public function test_online_order_is_priced_on_the_server_and_waits_for_payment(): void
    {
        $response = $this->post("/e/{$this->event->slug}/orders", $this->payload([$this->pizza->id => 2, $this->cola->id => 1]) + [
            'total_cents' => 1,
        ]);

        $order = Order::query()->sole();
        $response->assertRedirect(route('guest.order.pay', $order));
        $this->assertSame(2 * 950 + 325, $order->total_cents);
        $this->assertSame('2.00', $order->oven_units);
        $this->assertSame(PaymentMethod::Online, $order->payment_method);
        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
        $this->assertSame('pi_fake_'.$order->id, $order->stripe_payment_intent_id);
        $this->assertFalse($order->isQueued());
        $this->assertNull($order->pickup_number);
        $this->assertCount(2, $order->items);
    }

    public function test_cash_order_goes_straight_into_the_queue(): void
    {
        $this->post("/e/{$this->event->slug}/orders", $this->payload([$this->pizza->id => 1], 'cash'))
            ->assertRedirect();

        $order = Order::query()->sole();
        $this->assertTrue($order->isQueued());
        $this->assertSame(1, $order->pickup_number);
        $this->assertTrue($order->isCashOpen());
        $this->assertSame([], $this->payments->calls);
    }

    public function test_cash_can_be_disabled_per_event(): void
    {
        $this->event->update(['cash_allowed' => false]);

        $this->post("/e/{$this->event->slug}/orders", $this->payload([$this->pizza->id => 1], 'cash'))
            ->assertSessionHasErrors('order');
        $this->assertSame(0, Order::query()->count());
    }

    public function test_sold_out_items_are_rejected(): void
    {
        $this->pizza->update(['is_sold_out' => true]);

        $this->withHeader('Accept-Language', 'de-DE')->post("/e/{$this->event->slug}/orders", $this->payload([$this->pizza->id => 1]))
            ->assertSessionHasErrors(['order' => 'Leider ausverkauft: Margherita']);
    }

    public function test_paused_event_rejects_orders(): void
    {
        $this->event->update(['paused_until' => now()->addMinutes(10)]);

        $this->post("/e/{$this->event->slug}/orders", $this->payload([$this->pizza->id => 1]))
            ->assertSessionHasErrors('order');
    }

    public function test_items_of_other_editions_are_rejected(): void
    {
        $flammkuchen = MenuItem::factory()->create(['edition' => 'flammkuchen']);

        $this->post("/e/{$this->event->slug}/orders", $this->payload([$flammkuchen->id => 1]))
            ->assertSessionHasErrors('order');
    }

    public function test_counter_card_is_not_allowed_for_guests(): void
    {
        $this->post("/e/{$this->event->slug}/orders", $this->payload([$this->pizza->id => 1], 'counter_card'))
            ->assertSessionHasErrors('payment_method');
    }

    public function test_quantity_limits(): void
    {
        $this->post("/e/{$this->event->slug}/orders", $this->payload([$this->pizza->id => 21]))
            ->assertSessionHasErrors('lines.0.quantity');
        $this->post("/e/{$this->event->slug}/orders", ['lines' => [], 'payment_method' => 'online'])
            ->assertSessionHasErrors('lines');
    }

    public function test_pay_page_and_demo_payment(): void
    {
        $this->post("/e/{$this->event->slug}/orders", $this->payload([$this->pizza->id => 1]));
        $order = Order::query()->sole();

        $this->get(route('guest.order.pay', $order))->assertOk()->assertInertia(fn ($page) => $page
            ->component('Guest/Pay')
            ->where('stripe', null));

        $this->post(route('guest.order.simulate', $order))->assertRedirect(route('guest.order', $order));

        $order->refresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(OrderStatus::New, $order->status);
        $this->assertSame(1, $order->pickup_number);
    }

    public function test_demo_payment_is_unavailable_in_production(): void
    {
        $order = Order::factory()->for($this->event)->create();
        $this->app->detectEnvironment(fn () => 'production');
        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->post(route('guest.order.simulate', $order))->assertNotFound();
    }

    public function test_real_stripe_client_cannot_be_called_in_tests(): void
    {
        $gateway = new StripeGateway($this->app->make(StripeClient::class), 'whsec_test');

        $this->expectException(LogicException::class);
        $gateway->createPayment(Order::factory()->for($this->event)->create());
    }
}
