<?php

namespace Tests\Feature;

use App\Broadcasting\WaitTimesUpdated;
use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use App\Services\Orders\OrderQueue;
use App\Services\Orders\OrderWorkflow;
use App\Services\WaitTime\EventWaitTimes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event as EventFacade;
use Tests\TestCase;

class EventWaitTimesTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        // fixed noon: tests travel up to 80 min and only today's samples count
        $this->travelTo(today()->setTime(12, 0));
        // 2 slots, 4 min per bake, 3 min buffer
        $this->event = Event::factory()->create();
    }

    private function queued(string $units): Order
    {
        $this->travel(1)->seconds();

        return app(OrderQueue::class)->release(Order::factory()->for($this->event)->paid()->create(['oven_units' => $units]));
    }

    public function test_orders_ahead_count_and_range(): void
    {
        $first = $this->queued('2.00');
        $second = $this->queued('1.00');
        $third = $this->queued('0.25');

        $times = new EventWaitTimes($this->event);

        // first: 1 pass x 4 + 3 = 7
        $this->assertSame(['low' => 5, 'high' => 10, 'ahead' => 0], $times->forOrder($first));
        // third: ceil(3.25 / 2) = 2 passes x 4 + 3 = 11
        $this->assertSame(['low' => 10, 'high' => 15, 'ahead' => 2], $times->forOrder($third));
        $this->assertSame([1, 2, 3], array_keys($times->allOpen()));
        $this->assertNotNull($times->forOrder($second));
    }

    public function test_ready_orders_have_no_wait_and_do_not_count(): void
    {
        $workflow = app(OrderWorkflow::class);
        $first = $this->queued('2.00');
        $second = $this->queued('1.00');
        $workflow->advance($first);
        $workflow->advance($first);

        $times = new EventWaitTimes($this->event);

        $this->assertNull($times->forOrder($first->refresh()));
        $this->assertSame(0, $times->forOrder($second)['ahead'] ?? null);
    }

    public function test_time_already_in_the_oven_is_subtracted(): void
    {
        $order = $this->queued('2.00');
        app(OrderWorkflow::class)->advance($order);
        $this->travel(3)->minutes();

        // 4 - 3 + 3 buffer = 4
        $this->assertSame(['low' => 0, 'high' => 5, 'ahead' => 0], (new EventWaitTimes($this->event))->forOrder($order));
    }

    public function test_pause_is_added_to_general_wait(): void
    {
        $this->event->update(['paused_until' => now()->addMinutes(10)]);

        // 1 unit: 4 + 3 + 10 = 17
        $this->assertSame(['low' => 15, 'high' => 20], (new EventWaitTimes($this->event->refresh()))->general()->toArray());
    }

    public function test_learns_from_todays_measurements(): void
    {
        $workflow = app(OrderWorkflow::class);
        for ($i = 0; $i < 10; $i++) {
            $order = $this->queued('2.00');
            $workflow->advance($order);
            $this->travel(8)->minutes();
            $workflow->advance($order);
        }

        $times = new EventWaitTimes($this->event);

        $this->assertEqualsWithDelta(8.0, $times->minutesPerBake(), 0.01);
        // 1 pass x 8 + 3 = 11
        $this->assertSame(['low' => 10, 'high' => 15], $times->general()->toArray());
    }

    public function test_yesterdays_measurements_are_ignored(): void
    {
        $workflow = app(OrderWorkflow::class);
        for ($i = 0; $i < 6; $i++) {
            $order = $this->queued('2.00');
            $workflow->advance($order);
            $this->travel(8)->minutes();
            $workflow->advance($order);
            $workflow->advance($order);
        }
        $this->travel(1)->days();

        $this->assertSame(4.0, (new EventWaitTimes($this->event))->minutesPerBake());
    }

    public function test_every_status_change_broadcasts_wait_times(): void
    {
        EventFacade::fake([WaitTimesUpdated::class]);
        $order = $this->queued('1.00');

        app(OrderWorkflow::class)->transition($order, OrderStatus::InProgress);

        EventFacade::assertDispatchedTimes(WaitTimesUpdated::class, 2);
        $payload = (new WaitTimesUpdated($this->event))->broadcastWith();
        $this->assertArrayHasKey(1, $payload['orders']);
    }

    public function test_status_page_shows_wait(): void
    {
        $order = $this->queued('1.00');

        $this->get(route('guest.order', $order))
            ->assertInertia(fn ($page) => $page->where('wait', ['low' => 5, 'high' => 10, 'ahead' => 0]));
    }
}
