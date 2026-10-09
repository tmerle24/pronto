<?php

namespace Tests\Feature;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Enums\OrderEventType;
use App\Jobs\SendReadyNotification;
use App\Models\Event;
use App\Models\NotificationSubscription;
use App\Models\Order;
use App\Models\OutboundMessage;
use App\Services\Notifications\NotificationFailed;
use App\Services\Notifications\SmsProvider;
use App\Services\Orders\OrderQueue;
use App\Services\Orders\OrderWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->order = app(OrderQueue::class)->release(Order::factory()->for(Event::factory())->paid()->create());
    }

    private function subscribe(NotificationChannel $channel, string $target): NotificationSubscription
    {
        return $this->order->notificationSubscriptions()->create([
            'channel' => $channel,
            'target' => $target,
            'status' => NotificationStatus::Active,
        ]);
    }

    private function makeReady(): void
    {
        $workflow = app(OrderWorkflow::class);
        $workflow->advance($this->order);
        $workflow->advance($this->order);
    }

    private function pushTarget(): string
    {
        return (string) json_encode(['endpoint' => 'https://push.example/abc', 'keys' => ['p256dh' => 'k', 'auth' => 'a']]);
    }

    public function test_ready_dispatches_one_delayed_job_per_active_channel(): void
    {
        Queue::fake();
        $this->subscribe(NotificationChannel::WebPush, $this->pushTarget());
        $this->subscribe(NotificationChannel::Sms, '+491711234567');

        $this->makeReady();

        Queue::assertPushed(SendReadyNotification::class, 2);
        Queue::assertPushed(SendReadyNotification::class, fn ($job) => $job->delay === 6);
    }

    public function test_job_sends_push_and_marks_order_notified(): void
    {
        $subscription = $this->subscribe(NotificationChannel::WebPush, $this->pushTarget());
        $this->makeReady();

        $this->assertCount(1, $this->push->sent);
        $this->assertStringContainsString('Pronto! Nr. 1', $this->push->sent[0]['payload']);
        $this->assertSame(NotificationStatus::Sent, $subscription->refresh()->status);
        $this->assertNotNull($this->order->refresh()->customer_notified_at);
        $this->assertTrue($this->order->events()->where('type', OrderEventType::CustomerNotified)->exists());
    }

    public function test_undo_before_the_job_runs_sends_nothing(): void
    {
        $subscription = $this->subscribe(NotificationChannel::WebPush, $this->pushTarget());
        Queue::fake();
        $this->makeReady();
        app(OrderWorkflow::class)->undo($this->order);

        app()->call([new SendReadyNotification($subscription->id), 'handle']);

        $this->assertSame([], $this->push->sent);
        $this->assertSame(NotificationStatus::Active, $subscription->refresh()->status);
    }

    public function test_expired_push_subscription_fails_permanently(): void
    {
        $subscription = $this->subscribe(NotificationChannel::WebPush, $this->pushTarget());
        $this->push->failWith = NotificationFailed::permanent('expired');

        $this->makeReady();

        $this->assertSame(NotificationStatus::Failed, $subscription->refresh()->status);
        $this->assertNull($this->order->refresh()->customer_notified_at);
    }

    public function test_sms_log_driver_records_usage_and_daily_limit_stops_sending(): void
    {
        config(['pronto.sms.daily_limit' => 1]);
        $first = $this->subscribe(NotificationChannel::Sms, '+491711234567');
        $second = $this->subscribe(NotificationChannel::Sms, '+491711234568');

        $this->makeReady();

        $this->assertSame(1, OutboundMessage::query()->count());
        $statuses = [$first->refresh()->status, $second->refresh()->status];
        $this->assertContains(NotificationStatus::Sent, $statuses);
        $this->assertContains(NotificationStatus::Failed, $statuses);
    }

    public function test_seven_provider_sends_via_http(): void
    {
        config(['pronto.sms.driver' => 'seven', 'pronto.sms.api_key' => 'test-key']);
        $this->app->forgetInstance(SmsProvider::class);
        Http::fake(['gateway.seven.io/*' => Http::response(['success' => '100', 'messages' => [['id' => 77]]])]);
        $this->subscribe(NotificationChannel::Sms, '+491711234567');

        $this->makeReady();

        Http::assertSent(fn ($request) => $request->hasHeader('X-Api-Key', 'test-key') && $request['to'] === '+491711234567');
        $this->assertSame('77', OutboundMessage::query()->sole()->provider_message_id);
    }

    public function test_sms_signup_validates_and_normalizes(): void
    {
        $url = route('guest.order.sms', $this->order);

        $this->post($url, ['phone' => '0171 1234567'])->assertSessionHasErrors('consent');
        $this->post($url, ['phone' => '12', 'consent' => '1'])->assertSessionHasErrors('phone');
        $this->post($url, ['phone' => '0171 1234567', 'consent' => '1'])->assertSessionHasNoErrors();

        $this->assertSame('+491711234567', $this->order->notificationSubscriptions()->sole()->target);
    }

    public function test_signup_after_ready_sends_immediately(): void
    {
        $this->makeReady();

        $this->postJson(route('guest.order.push', $this->order), json_decode($this->pushTarget(), true))->assertOk();

        $this->assertCount(1, $this->push->sent);
    }

    public function test_contact_data_is_purged_after_24_hours(): void
    {
        $subscription = $this->subscribe(NotificationChannel::Sms, '+491711234567');
        $this->travel(25)->hours();

        Artisan::call('notifications:purge');

        $subscription->refresh();
        $this->assertNull($subscription->target);
        $this->assertNotNull($subscription->purged_at);
    }

    public function test_status_page_lists_channels_but_never_contact_data(): void
    {
        $this->subscribe(NotificationChannel::Sms, '+491711234567');

        $this->get(route('guest.order', $this->order))
            ->assertInertia(fn ($page) => $page->where('order.channels', ['sms']))
            ->assertDontSee('1711234567');
    }
}
