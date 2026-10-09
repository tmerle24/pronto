<?php

namespace Tests\Feature;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Event;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\CancelOrder;
use App\Services\Orders\InvalidTransition;
use App\Services\Orders\OrderLine;
use App\Services\Orders\OrderPayments;
use App\Services\Orders\OrderQueue;
use App\Services\Orders\OrderWorkflow;
use App\Services\Orders\PlaceOrder;
use App\Services\Reports\DailyReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->event = Event::factory()->create();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_only_admins_reach_the_admin(): void
    {
        $this->get('/team/admin')->assertRedirect('/team/login');
        $this->actingAs(User::factory()->create())->get('/team/admin')->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('admin.events.qr', $this->event))->assertForbidden();
    }

    public function test_admin_pages_render(): void
    {
        $order = app(OrderQueue::class)->release(Order::factory()->for($this->event)->paid()->create());
        $this->actingAs($this->admin);

        foreach (['/team/admin', '/team/admin/events', '/team/admin/menu-items', '/team/admin/orders', '/team/admin/users', '/team/admin/daily-report', "/team/admin/orders/{$order->id}", "/team/admin/events/{$this->event->id}/edit"] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_only_one_event_is_active(): void
    {
        $second = Event::factory()->create(['is_active' => true]);

        $this->assertFalse($this->event->refresh()->is_active);
        $this->assertTrue($second->refresh()->is_active);
    }

    public function test_cancel_paid_online_order_refunds_via_stripe(): void
    {
        $order = app(OrderQueue::class)->release(Order::factory()->for($this->event)->paid()->create(['stripe_payment_intent_id' => 'pi_1']));

        $cancelled = app(CancelOrder::class)($order, $this->admin, 'Teig alle');

        $this->assertSame(OrderStatus::Cancelled, $cancelled->status);
        $this->assertSame(PaymentStatus::Refunded, $cancelled->payment_status);
        $this->assertSame('Teig alle', $cancelled->cancel_reason);
        $this->assertContains('refund:'.$order->id, $this->payments->calls);
        $this->assertSame(2, $cancelled->events()->whereIn('type', ['status_changed', 'payment_changed'])->count());
    }

    public function test_cancel_open_cash_order_marks_payment_failed_without_refund(): void
    {
        $order = app(OrderQueue::class)->release(Order::factory()->for($this->event)->cash()->create());

        $cancelled = app(CancelOrder::class)($order, $this->admin, 'nicht abgeholt');

        $this->assertSame(PaymentStatus::Failed, $cancelled->payment_status);
        $this->assertSame([], $this->payments->calls);
    }

    public function test_picked_up_orders_cannot_be_cancelled(): void
    {
        $order = app(OrderQueue::class)->release(Order::factory()->for($this->event)->paid()->create());
        $workflow = app(OrderWorkflow::class);
        $workflow->advance($order);
        $workflow->advance($order);
        $workflow->advance($order);

        $this->expectException(InvalidTransition::class);
        app(CancelOrder::class)($order->refresh(), $this->admin, 'x');
    }

    public function test_receipt_numbers_are_sequential_per_year(): void
    {
        $payments = app(OrderPayments::class);
        $a = $payments->markPaid(Order::factory()->for($this->event)->create());
        $b = $payments->markPaid(Order::factory()->for($this->event)->create());

        $this->assertSame(now()->year.'-000001', $a->receipt_number);
        $this->assertSame(now()->year.'-000002', $b->receipt_number);
    }

    public function test_cash_pickup_assigns_receipt_number(): void
    {
        $order = app(OrderQueue::class)->release(Order::factory()->for($this->event)->cash()->create());
        $workflow = app(OrderWorkflow::class);
        foreach (range(1, 3) as $_) {
            $workflow->advance($order);
        }

        $this->assertNotNull($order->refresh()->receipt_number);
    }

    public function test_receipt_shows_vat_per_rate_and_downloads_as_pdf(): void
    {
        $pizza = MenuItem::factory()->create(['price_cents' => 1070, 'vat_rate_bp' => 700]);
        $cola = MenuItem::factory()->drink()->create(['price_cents' => 300, 'deposit_cents' => 25]);
        $order = app(PlaceOrder::class)($this->event, [new OrderLine($pizza->id, 1), new OrderLine($cola->id, 1)], PaymentMethod::CounterCard, OrderSource::Counter, 'de', $this->admin);

        $this->get(route('guest.order', $order))->assertInertia(fn ($page) => $page
            ->where('receipt.total_cents', 1395)
            ->where('receipt.vat.0', ['rate' => 7, 'net_cents' => 1000, 'vat_cents' => 70, 'gross_cents' => 1070])
            ->where('receipt.vat.1.rate', 19)
            ->where('receipt.vat.1.gross_cents', 325));

        $response = $this->get(route('guest.order.receipt', $order));
        $response->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_no_receipt_before_payment(): void
    {
        $order = Order::factory()->for($this->event)->create();

        $this->get(route('guest.order.receipt', $order))->assertNotFound();
    }

    public function test_qr_sign_and_svg(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('admin.events.qr', $this->event))->assertOk()->assertSee('Scan &amp; bestell', false)->assertSee('<svg', false);
        $this->get(route('admin.events.qr.svg', $this->event))->assertOk()->assertHeader('content-type', 'image/svg+xml');
    }

    public function test_daily_report_and_csv(): void
    {
        $pizza = MenuItem::factory()->create(['price_cents' => 950]);
        $place = app(PlaceOrder::class);
        $place($this->event, [new OrderLine($pizza->id, 2)], PaymentMethod::Cash, OrderSource::Counter, 'de', $this->admin);
        $place($this->event, [new OrderLine($pizza->id, 1)], PaymentMethod::CounterCard, OrderSource::Counter, 'de', $this->admin);
        $place($this->event, [new OrderLine($pizza->id, 1)], PaymentMethod::Cash, OrderSource::Qr);

        $summary = (new DailyReport($this->event, today()->toImmutable()))->summary();

        $this->assertSame(3, $summary['orders']);
        $this->assertSame(2850, $summary['revenue_cents']);
        $this->assertSame(1, $summary['open_cash']);
        $this->assertSame(['name' => 'Margherita ganz', 'quantity' => 4, 'cents' => 3800], $summary['top_items'][0]);

        $csv = $this->actingAs($this->admin)->get(route('admin.report.csv', ['event' => $this->event->id]))->streamedContent();
        $this->assertStringContainsString('Nr;Zeit;Status', $csv);
        $this->assertSame(4, substr_count(trim($csv), "\n") + 1);
    }
}
