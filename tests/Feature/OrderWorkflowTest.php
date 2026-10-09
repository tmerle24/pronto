<?php

namespace Tests\Feature;

use App\Enums\OrderEventType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\OrderQueued;
use App\Events\OrderStatusChanged;
use App\Models\Event;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\InvalidTransition;
use App\Services\Orders\OrderQueue;
use App\Services\Orders\OrderWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function queuedOrder(?Order $order = null): Order
    {
        return app(OrderQueue::class)->release($order ?? Order::factory()->paid()->create());
    }

    public function test_release_assigns_sequential_pickup_numbers_per_event(): void
    {
        EventFacade::fake([OrderQueued::class]);
        $event = Event::factory()->create();
        $other = Event::factory()->create();

        $a = $this->queuedOrder(Order::factory()->for($event)->create());
        $b = $this->queuedOrder(Order::factory()->for($event)->create());
        $c = $this->queuedOrder(Order::factory()->for($other)->create());

        $this->assertSame([1, 2, 1], [$a->pickup_number, $b->pickup_number, $c->pickup_number]);
        $this->assertNotNull($a->queued_at);
        EventFacade::assertDispatchedTimes(OrderQueued::class, 3);
    }

    public function test_release_is_idempotent(): void
    {
        $order = $this->queuedOrder();
        $again = app(OrderQueue::class)->release($order);

        $this->assertSame($order->pickup_number, $again->pickup_number);
        $this->assertSame(1, $order->event->refresh()->last_pickup_number);
    }

    public function test_advance_walks_through_all_steps_with_timestamps_and_audit(): void
    {
        EventFacade::fake([OrderStatusChanged::class]);
        $workflow = app(OrderWorkflow::class);
        $user = User::factory()->create();
        $order = $this->queuedOrder();

        $workflow->advance($order, $user);
        $workflow->advance($order, $user);
        $result = $workflow->advance($order, $user);

        $order = $result->order;
        $this->assertSame(OrderStatus::PickedUp, $order->status);
        $this->assertNotNull($order->started_at);
        $this->assertNotNull($order->ready_at);
        $this->assertNotNull($order->picked_up_at);
        $this->assertSame(
            ['new>in_progress', 'in_progress>ready', 'ready>picked_up'],
            $order->events()->where('type', OrderEventType::StatusChanged)->get()->map(fn ($e) => "{$e->from}>{$e->to}")->all(),
        );
        EventFacade::assertDispatchedTimes(OrderStatusChanged::class, 3);
        EventFacade::assertDispatched(OrderStatusChanged::class, fn ($e) => $e->to === OrderStatus::Ready);
    }

    public function test_unqueued_order_cannot_be_moved(): void
    {
        $order = Order::factory()->create();

        $this->expectException(InvalidTransition::class);
        app(OrderWorkflow::class)->advance($order);
    }

    public function test_invalid_jump_is_rejected(): void
    {
        $order = $this->queuedOrder();

        $this->expectException(InvalidTransition::class);
        app(OrderWorkflow::class)->transition($order, OrderStatus::PickedUp);
    }

    public function test_same_client_request_id_is_applied_once(): void
    {
        $workflow = app(OrderWorkflow::class);
        $order = $this->queuedOrder();
        $id = (string) Str::uuid();

        $first = $workflow->advance($order, null, $id);
        $second = $workflow->advance($order, null, $id);

        $this->assertFalse($first->duplicate);
        $this->assertTrue($second->duplicate);
        $this->assertSame(OrderStatus::InProgress, $order->refresh()->status);
    }

    public function test_pickup_of_open_cash_order_marks_it_paid(): void
    {
        $workflow = app(OrderWorkflow::class);
        $order = $this->queuedOrder(Order::factory()->cash()->create());
        $this->assertTrue($order->isCashOpen());

        $workflow->advance($order);
        $workflow->advance($order);
        $order = $workflow->advance($order)->order;

        $this->assertSame(OrderStatus::PickedUp, $order->status);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertNotNull($order->paid_at);
    }

    public function test_undo_restores_previous_status_and_clears_timestamp(): void
    {
        $workflow = app(OrderWorkflow::class);
        $order = $this->queuedOrder();
        $workflow->advance($order);
        $requestId = (string) Str::uuid();
        $workflow->advance($order, null, $requestId);

        $order = $workflow->undo($order, $requestId)->order;

        $this->assertSame(OrderStatus::InProgress, $order->status);
        $this->assertNull($order->ready_at);
        $this->assertNotNull($order->started_at);
        $this->assertTrue($order->events()->reorder('id', 'desc')->firstOrFail()->is_undo);
    }

    public function test_undo_of_cash_pickup_reopens_payment(): void
    {
        $workflow = app(OrderWorkflow::class);
        $order = $this->queuedOrder(Order::factory()->cash()->create());
        $workflow->advance($order);
        $workflow->advance($order);
        $workflow->advance($order);

        $order = $workflow->undo($order)->order;

        $this->assertSame(OrderStatus::Ready, $order->status);
        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
        $this->assertNull($order->paid_at);
    }

    public function test_undo_only_reverts_the_latest_change(): void
    {
        $workflow = app(OrderWorkflow::class);
        $order = $this->queuedOrder();
        $first = (string) Str::uuid();
        $workflow->advance($order, null, $first);
        $workflow->advance($order);

        $this->expectException(InvalidTransition::class);
        $workflow->undo($order, $first);
    }

    public function test_undo_twice_fails(): void
    {
        $workflow = app(OrderWorkflow::class);
        $order = $this->queuedOrder();
        $workflow->advance($order);
        $workflow->undo($order);

        $this->expectException(InvalidTransition::class);
        $workflow->undo($order);
    }
}
