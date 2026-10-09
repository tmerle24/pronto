<?php

namespace Tests\Feature;

use App\Broadcasting\BoardUpdated;
use App\Broadcasting\GuestOrderUpdated;
use App\Broadcasting\TeamOrderUpdated;
use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\OrderQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Str;
use Tests\TestCase;

class TeamQueueTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->event = Event::factory()->create();
    }

    private function queued(): Order
    {
        return app(OrderQueue::class)->release(Order::factory()->for($this->event)->paid()->create());
    }

    public function test_queue_requires_login(): void
    {
        $this->get('/team/queue')->assertRedirect('/team/login');
        $this->postJson('/team/orders/1/advance', ['request_id' => (string) Str::uuid()])->assertUnauthorized();
    }

    public function test_login_remembers_the_device(): void
    {
        $user = User::factory()->create(['password' => 'geheim-123']);

        $response = $this->post('/team/login', ['email' => $user->email, 'password' => 'geheim-123']);

        $response->assertRedirect('/team/queue');
        $this->assertAuthenticatedAs($user);
        $response->assertCookie(Auth::guard('web')->getRecallerName());
    }

    public function test_wrong_password(): void
    {
        $user = User::factory()->create();

        $this->post('/team/login', ['email' => $user->email, 'password' => 'falsch'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_queue_lists_only_queued_open_orders(): void
    {
        $queued = $this->queued();
        Order::factory()->for($this->event)->create();

        $this->actingAs(User::factory()->create())
            ->get('/team/queue')
            ->assertInertia(fn ($page) => $page->component('Team/Queue')->has('orders', 1)->where('orders.0.id', $queued->id));
    }

    public function test_advance_and_undo_via_api(): void
    {
        $order = $this->queued();
        $user = User::factory()->create();
        $first = (string) Str::uuid();

        $this->actingAs($user)
            ->postJson("/team/orders/{$order->id}/advance", ['request_id' => $first])
            ->assertOk()
            ->assertJsonPath('order.status', 'in_progress');

        $this->postJson("/team/orders/{$order->id}/advance", ['request_id' => $first])
            ->assertOk()
            ->assertJsonPath('duplicate', true);

        $this->postJson("/team/orders/{$order->id}/undo", ['request_id' => (string) Str::uuid(), 'undo_of' => $first])
            ->assertOk()
            ->assertJsonPath('order.status', 'new');

        $this->assertSame($user->id, $order->events()->reorder('id', 'desc')->firstOrFail()->user_id);
    }

    public function test_conflict_returns_current_card(): void
    {
        $order = $this->queued();
        $this->actingAs(User::factory()->create());
        $this->postJson("/team/orders/{$order->id}/undo", ['request_id' => (string) Str::uuid(), 'undo_of' => (string) Str::uuid()])
            ->assertStatus(409)
            ->assertJsonPath('order.status', 'new');
    }

    public function test_status_change_is_broadcast_to_team_guest_and_board(): void
    {
        EventFacade::fake([TeamOrderUpdated::class, GuestOrderUpdated::class, BoardUpdated::class]);
        $order = $this->queued();

        $this->actingAs(User::factory()->create())
            ->postJson("/team/orders/{$order->id}/advance", ['request_id' => (string) Str::uuid()]);

        EventFacade::assertDispatched(TeamOrderUpdated::class, fn ($e) => $e->order->status === OrderStatus::InProgress);
        EventFacade::assertDispatched(GuestOrderUpdated::class);
        EventFacade::assertDispatched(BoardUpdated::class);
    }

    public function test_display_needs_the_secret_token_and_shows_numbers_only(): void
    {
        $order = $this->queued();

        $this->get('/display/wrong-token')->assertNotFound();
        $this->get("/display/{$this->event->display_token}/state")
            ->assertOk()
            ->assertExactJson(['board' => ['in_oven' => [$order->pickup_number], 'ready' => []]]);
    }

    public function test_team_channel_requires_login(): void
    {
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'k', 'broadcasting.connections.reverb.secret' => 's', 'broadcasting.connections.reverb.app_id' => '1']);

        $this->post('/broadcasting/auth', ['channel_name' => 'private-team.'.$this->event->id, 'socket_id' => '1.1'])
            ->assertForbidden();
    }
}
