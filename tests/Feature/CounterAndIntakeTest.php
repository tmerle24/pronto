<?php

namespace Tests\Feature;

use App\Broadcasting\WaitTimesUpdated;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\OrderQueued;
use App\Models\Event;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Services\Reports\DailyReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event as EventFacade;
use Tests\TestCase;

class CounterAndIntakeTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private MenuItem $pizza;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->event = Event::factory()->create();
        $this->pizza = MenuItem::factory()->create(['price_cents' => 950]);
        $this->staff = User::factory()->create();
    }

    /** @return array<string, mixed> */
    private function counterOrder(string $method): array
    {
        return ['lines' => [['id' => $this->pizza->id, 'quantity' => 2]], 'payment_method' => $method];
    }

    public function test_counter_needs_login(): void
    {
        $this->get('/team/counter')->assertRedirect('/team/login');
        $this->post('/team/counter', $this->counterOrder('cash'))->assertRedirect('/team/login');
    }

    public function test_counter_cash_is_paid_on_the_spot_and_queued(): void
    {
        $this->actingAs($this->staff)
            ->post('/team/counter', $this->counterOrder('cash'))
            ->assertSessionHas('placed', ['number' => 1, 'handed_over' => false, 'total_cents' => 1900, 'payment_method' => 'cash']);

        $order = Order::query()->sole();
        $this->assertSame(OrderSource::Counter, $order->source);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertFalse($order->isCashOpen());
        $this->assertTrue($order->isQueued());
        $this->assertSame($this->staff->id, $order->created_by_user_id);
    }

    public function test_counter_card_is_marked_paid(): void
    {
        $this->actingAs($this->staff)->post('/team/counter', $this->counterOrder('counter_card'));

        $order = Order::query()->sole();
        $this->assertSame(PaymentMethod::CounterCard, $order->payment_method);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame('counter_card', $order->events()->where('type', 'payment_changed')->sole()->meta['via'] ?? null);
    }

    public function test_counter_works_during_pause_but_guests_wait(): void
    {
        $this->event->update(['paused_until' => now()->addMinutes(10)]);

        $this->post("/e/{$this->event->slug}/orders", $this->counterOrder('cash'))->assertSessionHasErrors('order');
        $this->actingAs($this->staff)->post('/team/counter', $this->counterOrder('cash'))->assertSessionHasNoErrors();
    }

    public function test_counter_takes_an_individual_price(): void
    {
        $this->actingAs($this->staff)
            ->post('/team/counter', ['lines' => [['id' => $this->pizza->id, 'quantity' => 2, 'price_cents' => 500]], 'payment_method' => 'cash'])
            ->assertSessionHas('placed', fn (array $placed) => $placed['total_cents'] === 1000);

        $order = Order::query()->sole();
        $this->assertSame(500, $order->items()->sole()->unit_price_cents);
        $this->assertSame(
            [['menu_item_id' => $this->pizza->id, 'menu_cents' => 950, 'charged_cents' => 500]],
            $order->events()->where('type', 'created')->sole()->meta['custom_prices'],
        );
    }

    public function test_counter_without_individual_price_uses_the_menu(): void
    {
        $this->actingAs($this->staff)
            ->post('/team/counter', ['lines' => [['id' => $this->pizza->id, 'quantity' => 1, 'price_cents' => null]], 'payment_method' => 'cash'])
            ->assertSessionHas('placed', fn (array $placed) => $placed['total_cents'] === 950);

        $this->assertArrayNotHasKey('custom_prices', Order::query()->sole()->events()->where('type', 'created')->sole()->meta);
    }

    public function test_individual_price_is_limited(): void
    {
        $this->actingAs($this->staff)
            ->post('/team/counter', ['lines' => [['id' => $this->pizza->id, 'quantity' => 1, 'price_cents' => 100001]], 'payment_method' => 'cash'])
            ->assertSessionHasErrors('lines.0.price_cents');
        $this->assertSame(0, Order::query()->count());
    }

    public function test_guests_cannot_set_a_price(): void
    {
        $this->post("/e/{$this->event->slug}/orders", ['lines' => [['id' => $this->pizza->id, 'quantity' => 1, 'price_cents' => 1]], 'payment_method' => 'cash']);

        $this->assertSame(950, Order::query()->sole()->total_cents);
    }

    public function test_counter_rejects_online_payment(): void
    {
        $this->actingAs($this->staff)->post('/team/counter', $this->counterOrder('online'))->assertSessionHasErrors('payment_method');
    }

    public function test_pause_and_resume(): void
    {
        EventFacade::fake([WaitTimesUpdated::class]);

        $this->actingAs($this->staff)
            ->postJson('/team/intake/pause', ['minutes' => 10])
            ->assertOk()
            ->assertJsonPath('status.open', false)
            ->assertJsonPath('status.paused_minutes', 10);
        $this->assertTrue($this->event->refresh()->isPaused());
        EventFacade::assertDispatched(WaitTimesUpdated::class);

        $this->postJson('/team/intake/pause', ['minutes' => 0])->assertJsonPath('status.open', true);
        $this->postJson('/team/intake/pause', ['minutes' => 7])->assertUnprocessable();
    }

    public function test_order_intake_can_be_switched_off(): void
    {
        $this->actingAs($this->staff)->postJson('/team/intake/accepting', ['accepting' => false])->assertJsonPath('status.open', false);

        $this->post("/e/{$this->event->slug}/orders", $this->counterOrder('cash'))->assertSessionHasErrors('order');
    }

    public function test_sold_out_toggle_blocks_guest_orders(): void
    {
        $this->actingAs($this->staff)
            ->postJson("/team/menu-items/{$this->pizza->id}/sold-out", ['sold_out' => true])
            ->assertJsonPath('status.sold_out', [$this->pizza->id]);

        $this->post("/e/{$this->event->slug}/orders", $this->counterOrder('online'))->assertSessionHasErrors('order');

        $this->postJson("/team/menu-items/{$this->pizza->id}/sold-out", ['sold_out' => false])->assertJsonPath('status.sold_out', []);
    }

    public function test_wait_broadcast_carries_intake_status(): void
    {
        $this->pizza->update(['is_sold_out' => true]);

        $payload = (new WaitTimesUpdated($this->event))->broadcastWith();

        $this->assertSame([$this->pizza->id], $payload['status']['sold_out']);
        $this->assertTrue($payload['status']['open']);
    }

    public function test_counter_sale_handed_over_skips_queue_but_counts(): void
    {
        EventFacade::fake([OrderQueued::class]);
        $slice = MenuItem::factory()->slice()->create();
        $cola = MenuItem::factory()->drink()->create();

        $this->actingAs($this->staff)->post('/team/counter', [
            'lines' => [['id' => $slice->id, 'quantity' => 1], ['id' => $cola->id, 'quantity' => 1]],
            'payment_method' => 'cash',
            'handed_over' => true,
        ])->assertSessionHas('placed', fn ($p) => $p['handed_over'] === true && $p['number'] === null);

        $order = Order::query()->sole();
        $this->assertSame(OrderStatus::PickedUp, $order->status);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertNotNull($order->receipt_number);
        $this->assertNull($order->pickup_number);
        $this->assertSame(0, $this->event->refresh()->last_pickup_number);
        EventFacade::assertNotDispatched(OrderQueued::class);

        $this->get('/team/queue/state')->assertJsonCount(0, 'orders');
        $this->get("/display/{$this->event->display_token}/state")->assertJsonPath('board', ['in_oven' => [], 'ready' => []]);
        $this->assertSame(1, (new DailyReport($this->event, today()->toImmutable()))->summary()['orders']);
    }

    public function test_counter_sale_to_kitchen_gets_a_number(): void
    {
        $this->actingAs($this->staff)->post('/team/counter', $this->counterOrder('cash') + ['handed_over' => false]);

        $order = Order::query()->sole();
        $this->assertSame(1, $order->pickup_number);
        $this->assertSame(OrderStatus::New, $order->status);
    }

    public function test_guests_cannot_skip_the_queue(): void
    {
        $this->post("/e/{$this->event->slug}/orders", $this->counterOrder('cash') + ['handed_over' => true]);

        $this->assertSame(OrderStatus::New, Order::query()->sole()->status);
    }
}
