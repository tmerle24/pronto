<?php

namespace App\Services\Notifications;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** seven.io HTTP API. */
class SevenSmsProvider implements SmsProvider
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $sender,
    ) {}

    public function name(): string
    {
        return 'seven';
    }

    public function send(string $phone, string $text): ?string
    {
        try {
            $response = Http::withHeaders(['X-Api-Key' => $this->apiKey])
                ->acceptJson()
                ->timeout(10)
                ->asForm()
                ->post('https://gateway.seven.io/api/sms', [
                    'to' => $phone,
                    'text' => $text,
                    'from' => $this->sender,
                    'json' => 1,
                ]);
        } catch (ConnectionException $e) {
            throw new NotificationFailed($e->getMessage(), previous: $e);
        }

        // seven answers 200 with its own success code ("100" = sent)
        $code = (string) $response->json('success');
        if ($response->failed() || $code !== '100') {
            throw NotificationFailed::permanent('seven.io '.$response->status().' code '.$code);
        }

        $id = $response->json('messages.0.id');

        return is_scalar($id) ? (string) $id : null;
    }
}
