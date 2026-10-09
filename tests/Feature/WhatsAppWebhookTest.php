<?php

namespace Tests\Feature;

use App\Enums\NotificationChannel;
use App\Models\Event;
use App\Models\Order;
use App\Models\OutboundMessage;
use App\Services\Notifications\WhatsAppProvider;
use App\Services\Orders\OrderQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class WhatsAppWebhookTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'pronto.whatsapp.driver' => 'meta',
            'pronto.whatsapp.token' => 'token',
            'pronto.whatsapp.phone_number_id' => '123',
            'pronto.whatsapp.app_secret' => 'secret',
            'pronto.whatsapp.verify_token' => 'verify-me',
            'pronto.whatsapp.number' => '4961719999',
        ]);
        $this->app->forgetInstance(WhatsAppProvider::class);
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]])]);

        $this->order = app(OrderQueue::class)->release(
            Order::factory()->for(Event::factory())->paid()->create(['short_code' => 'K7M4']),
        );
    }

    /** @return TestResponse<Response> */
    private function incoming(string $text, string $messageId = 'wamid.in1', ?string $signature = null): TestResponse
    {
        $payload = (string) json_encode(['entry' => [['changes' => [['value' => ['messages' => [[
            'id' => $messageId, 'from' => '491711234567', 'type' => 'text', 'text' => ['body' => $text],
        ]]]]]]]]);
        $signature ??= 'sha256='.hash_hmac('sha256', $payload, 'secret');

        return $this->call('POST', '/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
        ], $payload);
    }

    public function test_verification_handshake(): void
    {
        $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=verify-me&hub.challenge=42')->assertOk()->assertSee('42');
        $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=42')->assertForbidden();
    }

    public function test_message_with_short_code_subscribes_and_confirms(): void
    {
        $this->incoming('Bestellung 1 K7M4')->assertOk();

        $subscription = $this->order->notificationSubscriptions()->sole();
        $this->assertSame(NotificationChannel::WhatsApp, $subscription->channel);
        $this->assertSame('491711234567', $subscription->target);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/123/messages') && str_contains((string) $r['text']['body'], 'Nummer 1'));
        $this->assertSame('registered', OutboundMessage::query()->sole()->kind);
    }

    public function test_same_message_twice_is_handled_once(): void
    {
        $this->incoming('Bestellung 1 K7M4');
        $this->incoming('Bestellung 1 K7M4');

        $this->assertSame(1, $this->order->notificationSubscriptions()->count());
        Http::assertSentCount(1);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $this->incoming('Bestellung 1 K7M4', signature: 'sha256=forged')->assertForbidden();

        $this->assertSame(0, $this->order->notificationSubscriptions()->count());
    }

    public function test_unknown_code_is_ignored(): void
    {
        $this->incoming('Hallo, habt ihr auch Calzone?')->assertOk();

        $this->assertSame(0, $this->order->notificationSubscriptions()->count());
        Http::assertNothingSent();
    }

    public function test_status_page_offers_the_wa_me_link(): void
    {
        $this->get(route('guest.order', $this->order))
            ->assertInertia(fn ($page) => $page->where('order.whatsapp_link', 'https://wa.me/4961719999?text=Bestellung%201%20K7M4'));
    }
}
