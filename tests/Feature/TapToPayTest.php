<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Event;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Services\Payments\CardTerminal;
use App\Services\Payments\StripeCardTerminal;
use App\Services\Payments\TerminalPayment;
use App\Services\Payments\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\ExplodingStripeClient;
use Tests\TestCase;

class TapToPayTest extends TestCase
{
    use RefreshDatabase;

    private MenuItem $pizza;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        Event::factory()->create();
        $this->pizza = MenuItem::factory()->create(['price_cents' => 950]);
        $this->staff = User::factory()->create();
        config(['services.stripe.terminal_location' => 'tml_test']);
    }

    private function start(): Order
    {
        $this->actingAs($this->staff)
            ->postJson('/team/counter/tap', ['lines' => [['id' => $this->pizza->id, 'quantity' => 2]], 'total_cents' => 1])
            ->assertOk()
            ->assertJsonPath('total_cents', 1900)
            ->assertJsonStructure(['order', 'client_secret']);

        return Order::query()->sole();
    }

    private function tap(Order $order): void
    {
        $this->terminal->statuses[$order->id] = TerminalPayment::SUCCEEDED;
    }

    public function test_endpoints_need_login(): void
    {
        $this->postJson('/team/terminal/connection-token')->assertUnauthorized();
        $this->postJson('/team/counter/tap', [])->assertUnauthorized();
    }

    public function test_connection_token(): void
    {
        $this->actingAs($this->staff)->postJson('/team/terminal/connection-token')->assertOk()->assertJsonPath('secret', 'pst_test_fake');
    }

    public function test_order_waits_for_the_tap_outside_the_queue(): void
    {
        $order = $this->start();

        $this->assertSame(PaymentMethod::TapToPay, $order->payment_method);
        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
        $this->assertFalse($order->isQueued());
        $this->assertSame('pi_fake_tap_'.$order->id, $order->stripe_payment_intent_id);
    }

    public function test_complete_after_tap_marks_paid_and_queues(): void
    {
        $order = $this->start();
        $this->tap($order);

        $this->postJson("/team/counter/tap/{$order->id}/complete")
            ->assertOk()
            ->assertJsonPath('placed', ['number' => 1, 'handed_over' => false, 'total_cents' => 1900, 'payment_method' => 'tap_to_pay']);

        $order->refresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertTrue($order->isQueued());
        $this->assertSame('tap_to_pay', $order->events()->where('type', 'payment_changed')->sole()->meta['via'] ?? null);

        // repeated report (e.g. retry after a network drop) keeps the number
        $this->postJson("/team/counter/tap/{$order->id}/complete")->assertJsonPath('placed.number', 1);
        $this->assertSame(1, $order->events()->where('type', 'payment_changed')->count());
    }

    public function test_handed_over_sale_skips_the_queue_after_the_tap(): void
    {
        $this->actingAs($this->staff)
            ->postJson('/team/counter/tap', ['lines' => [['id' => $this->pizza->id, 'quantity' => 1]], 'handed_over' => true])
            ->assertOk();
        $order = Order::query()->sole();
        $this->tap($order);

        $this->postJson("/team/counter/tap/{$order->id}/complete")
            ->assertOk()
            ->assertJsonPath('placed.handed_over', true)
            ->assertJsonPath('placed.number', null);

        $this->assertSame(OrderStatus::PickedUp, $order->refresh()->status);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
    }

    public function test_webhook_keeps_the_hand_over_choice(): void
    {
        $this->actingAs($this->staff)
            ->postJson('/team/counter/tap', ['lines' => [['id' => $this->pizza->id, 'quantity' => 1]], 'handed_over' => true]);
        $order = Order::query()->sole();

        $payload = (string) json_encode(['id' => 'evt_tap_ho', 'type' => WebhookEvent::PAYMENT_SUCCEEDED, 'data' => ['object' => ['id' => $order->stripe_payment_intent_id]]]);
        $this->call('POST', '/webhooks/stripe', [], [], [], ['HTTP_STRIPE_SIGNATURE' => 'fake-signature', 'CONTENT_TYPE' => 'application/json'], $payload)->assertOk();

        $order->refresh();
        $this->assertSame(OrderStatus::PickedUp, $order->status);
        $this->assertNull($order->pickup_number);
    }

    public function test_complete_without_successful_tap_is_rejected(): void
    {
        $order = $this->start();

        $this->postJson("/team/counter/tap/{$order->id}/complete")
            ->assertConflict()
            ->assertJsonPath('status', 'requires_payment_method');

        $this->assertSame(PaymentStatus::Pending, $order->refresh()->payment_status);
        $this->assertFalse($order->isQueued());
    }

    public function test_payment_waiting_for_capture_is_captured(): void
    {
        $order = $this->start();
        $this->terminal->statuses[$order->id] = TerminalPayment::REQUIRES_CAPTURE;

        $this->postJson("/team/counter/tap/{$order->id}/complete")->assertOk();

        $this->assertContains('capture:'.$order->id, $this->terminal->calls);
        $this->assertSame(PaymentStatus::Paid, $order->refresh()->payment_status);
    }

    public function test_amount_mismatch_never_marks_paid(): void
    {
        $order = $this->start();
        $this->tap($order);
        $this->terminal->amounts[$order->id] = 100;

        $this->postJson("/team/counter/tap/{$order->id}/complete")->assertConflict()->assertJsonPath('status', 'amount_mismatch');

        $this->assertSame(PaymentStatus::Pending, $order->refresh()->payment_status);
    }

    public function test_switch_to_cash_cancels_the_payment(): void
    {
        $order = $this->start();

        $this->postJson("/team/counter/tap/{$order->id}/cash")
            ->assertOk()
            ->assertJsonPath('placed.payment_method', 'cash');

        $order->refresh();
        $this->assertContains('cancel:'.$order->id, $this->terminal->calls);
        $this->assertSame(PaymentMethod::Cash, $order->payment_method);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertTrue($order->isQueued());
        $this->assertSame('cash', $order->events()->where('type', 'payment_method_changed')->sole()->to);

        $this->postJson("/team/counter/tap/{$order->id}/cash")->assertOk()->assertJsonPath('placed.number', 1);
    }

    public function test_switch_to_cash_after_a_tap_went_through_stays_card(): void
    {
        $order = $this->start();
        $this->tap($order);

        $this->postJson("/team/counter/tap/{$order->id}/cash")->assertOk()->assertJsonPath('placed.payment_method', 'tap_to_pay');

        $this->assertSame(PaymentMethod::TapToPay, $order->refresh()->payment_method);
    }

    public function test_cancel_before_tap_cancels_the_order(): void
    {
        $order = $this->start();

        $this->postJson("/team/counter/tap/{$order->id}/cancel")->assertOk()->assertJsonPath('cancelled', true);
        $this->postJson("/team/counter/tap/{$order->id}/cancel")->assertOk()->assertJsonPath('cancelled', true);

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertSame(PaymentStatus::Failed, $order->payment_status);
        $this->assertFalse($order->isQueued());
    }

    public function test_cancel_after_a_tap_went_through_completes_instead(): void
    {
        $order = $this->start();
        $this->tap($order);

        $this->postJson("/team/counter/tap/{$order->id}/cancel")->assertOk()->assertJsonPath('placed.number', 1);
    }

    public function test_webhook_completes_when_the_app_never_reports_back(): void
    {
        $order = $this->start();

        $payload = (string) json_encode(['id' => 'evt_tap', 'type' => WebhookEvent::PAYMENT_SUCCEEDED, 'data' => ['object' => ['id' => $order->stripe_payment_intent_id]]]);
        $this->call('POST', '/webhooks/stripe', [], [], [], ['HTTP_STRIPE_SIGNATURE' => 'fake-signature', 'CONTENT_TYPE' => 'application/json'], $payload)->assertOk();

        $order->refresh();
        $this->assertTrue($order->isQueued());
        $this->assertSame('tap_to_pay', $order->events()->where('type', 'payment_changed')->sole()->meta['via'] ?? null);
    }

    public function test_abandoned_tap_orders_expire(): void
    {
        $order = $this->start();
        $this->travel(31)->minutes();

        Artisan::call('orders:expire-unpaid');

        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
    }

    public function test_other_orders_are_not_reachable(): void
    {
        $online = Order::factory()->for(Event::factory())->create();

        $this->actingAs($this->staff)->postJson("/team/counter/tap/{$online->id}/complete")->assertNotFound();
    }

    public function test_disabled_without_terminal_location(): void
    {
        $this->app->instance(CardTerminal::class, new StripeCardTerminal(new ExplodingStripeClient));
        config(['services.stripe.terminal_location' => null]);

        $this->actingAs($this->staff)->postJson('/team/terminal/connection-token')->assertNotFound();
        $this->get('/team/counter')->assertInertia(fn ($page) => $page->where('tapToPay.enabled', false));
    }

    public function test_individual_price_is_charged(): void
    {
        $this->actingAs($this->staff)
            ->postJson('/team/counter/tap', ['lines' => [['id' => $this->pizza->id, 'quantity' => 2, 'price_cents' => 700]]])
            ->assertOk()
            ->assertJsonPath('total_cents', 1400);
    }

    public function test_tap_needs_at_least_fifty_cents(): void
    {
        $this->actingAs($this->staff)
            ->postJson('/team/counter/tap', ['lines' => [['id' => $this->pizza->id, 'quantity' => 1, 'price_cents' => 0]]])
            ->assertStatus(422);

        $this->assertSame(0, Order::query()->count());
    }

    public function test_guests_cannot_choose_tap_to_pay(): void
    {
        $event = Event::query()->sole();

        $this->post("/e/{$event->slug}/orders", ['lines' => [['id' => $this->pizza->id, 'quantity' => 1]], 'payment_method' => 'tap_to_pay'])
            ->assertSessionHasErrors('payment_method');
    }
}
