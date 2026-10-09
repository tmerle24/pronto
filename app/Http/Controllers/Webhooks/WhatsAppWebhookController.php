<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Listeners\NotifyCustomerWhenReady;
use App\Models\Event;
use App\Models\Order;
use App\Models\ProcessedWebhook;
use App\Services\Notifications\NotificationFailed;
use App\Services\Notifications\WhatsAppNotifier;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * The guest sends "Bestellung 42 K7M2" via wa.me link; we match the short
 * code to the order and may then answer freely within 24 h.
 */
class WhatsAppWebhookController extends Controller
{
    /** Meta subscription handshake. */
    public function verify(Request $request): Response
    {
        $token = config('pronto.whatsapp.verify_token');
        if ($request->query('hub_mode') === 'subscribe' && filled($token) && hash_equals((string) $token, (string) $request->query('hub_verify_token'))) {
            return response((string) $request->query('hub_challenge'));
        }

        return response('forbidden', 403);
    }

    public function receive(Request $request, WhatsAppNotifier $notifier): Response
    {
        if (! $this->validSignature($request)) {
            return response('invalid signature', 403);
        }

        foreach ($this->messages($request) as [$messageId, $waId, $text]) {
            try {
                ProcessedWebhook::query()->create(['provider' => 'whatsapp', 'external_id' => $messageId, 'type' => 'message']);
            } catch (UniqueConstraintViolationException) {
                continue;
            }

            $order = $this->findOrder($text);
            if ($order === null) {
                Log::info('whatsapp message without matching order');

                continue;
            }

            $subscription = $order->notificationSubscriptions()->create([
                'channel' => NotificationChannel::WhatsApp,
                'target' => $waId,
                'status' => NotificationStatus::Active,
            ]);

            if ($order->status === OrderStatus::Ready) {
                NotifyCustomerWhenReady::dispatchFor([$subscription], immediately: true);

                continue;
            }

            try {
                $notifier->confirm($waId, $order);
            } catch (NotificationFailed $e) {
                report($e);
            }
        }

        // always 200, otherwise Meta retries and eventually disables the hook
        return response('ok');
    }

    private function validSignature(Request $request): bool
    {
        $secret = config('pronto.whatsapp.app_secret');
        if (blank($secret)) {
            // 360dialog and the log driver have no signature
            return config('pronto.whatsapp.driver') !== 'meta';
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), (string) $secret);

        return hash_equals($expected, (string) $request->header('X-Hub-Signature-256'));
    }

    /**
     * @return list<array{string, string, string}> message id, sender wa id, text
     */
    private function messages(Request $request): array
    {
        $result = [];
        foreach ((array) $request->input('entry', []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                foreach ((array) ($change['value']['messages'] ?? []) as $message) {
                    if (($message['type'] ?? null) === 'text' && isset($message['id'], $message['from'], $message['text']['body'])) {
                        $result[] = [(string) $message['id'], (string) $message['from'], (string) $message['text']['body']];
                    }
                }
            }
        }

        return $result;
    }

    private function findOrder(string $text): ?Order
    {
        $event = Event::current();
        if ($event === null || preg_match_all('/\b([A-Z0-9]{4})\b/', strtoupper($text), $matches) === 0) {
            return null;
        }

        return Order::query()
            ->where('event_id', $event->id)
            ->whereIn('short_code', $matches[1])
            ->whereNotNull('queued_at')
            ->whereIn('status', [OrderStatus::New, OrderStatus::InProgress, OrderStatus::Ready])
            ->first();
    }
}
