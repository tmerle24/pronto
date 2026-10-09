<?php

namespace App\Services\Notifications;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * WhatsApp Business Cloud API, directly at Meta or via 360dialog (same payload,
 * different base URL and auth header).
 */
class CloudApiWhatsAppProvider implements WhatsAppProvider
{
    public function __construct(
        private readonly string $driver,
        private readonly string $token,
        private readonly ?string $phoneNumberId,
    ) {}

    public function name(): string
    {
        return $this->driver;
    }

    public function sendText(string $waId, string $text): ?string
    {
        $request = $this->driver === '360dialog'
            ? Http::withHeaders(['D360-API-KEY' => $this->token])->baseUrl('https://waba-v2.360dialog.io')
            : Http::withToken($this->token)->baseUrl('https://graph.facebook.com/v21.0/'.$this->phoneNumberId);

        try {
            $response = $request->timeout(10)->post('/messages', [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $waId,
                'type' => 'text',
                'text' => ['body' => $text],
            ]);
        } catch (ConnectionException $e) {
            throw new NotificationFailed($e->getMessage(), previous: $e);
        }

        if ($response->clientError()) {
            throw NotificationFailed::permanent('WhatsApp '.$response->status().': '.$response->body());
        }
        if ($response->failed()) {
            throw new NotificationFailed('WhatsApp '.$response->status());
        }

        $id = $response->json('messages.0.id');

        return is_string($id) ? $id : null;
    }
}
