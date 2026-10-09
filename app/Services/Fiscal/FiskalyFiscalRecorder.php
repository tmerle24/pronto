<?php

namespace App\Services\Fiscal;

use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Prepared adapter for fiskaly SIGN DE (cloud TSE, API v2). Not active until
 * FISCAL_DRIVER=fiskaly and credentials are set; verify against the fiskaly
 * sandbox before the first real use.
 */
class FiskalyFiscalRecorder implements FiscalRecorder
{
    private const BASE_URL = 'https://kassensichv-middleware.fiskaly.com/api/v2';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $apiSecret,
        private readonly string $tssId,
        private readonly string $clientId,
    ) {}

    public function isActive(): bool
    {
        return true;
    }

    public function record(Order $order, FiscalKind $kind): ?array
    {
        $txId = (string) Str::uuid();
        $path = "/tss/{$this->tssId}/tx/{$txId}";

        $this->request()->put($path.'?tx_revision=1', ['state' => 'ACTIVE', 'client_id' => $this->clientId])->throw();

        $sign = $kind === FiscalKind::Refund ? -1 : 1;
        $response = $this->request()->put($path.'?tx_revision=2', [
            'state' => 'FINISHED',
            'client_id' => $this->clientId,
            'schema' => ['standard_v1' => ['receipt' => [
                'receipt_type' => 'RECEIPT',
                'amounts_per_vat_rate' => $this->amountsPerVatRate($order, $sign),
                'amounts_per_payment_type' => [[
                    'payment_type' => $order->payment_method === PaymentMethod::Cash ? 'CASH' : 'NON_CASH',
                    'amount' => $this->euro($sign * $order->total_cents),
                ]],
            ]]],
        ])->throw();

        return [
            'provider' => 'fiskaly',
            'kind' => $kind->value,
            'tx_id' => $txId,
            'number' => $response->json('number'),
            'signature' => $response->json('signature.value'),
            'signature_counter' => $response->json('signature.counter'),
            'time_start' => $response->json('time_start'),
            'time_end' => $response->json('time_end'),
            'tss_serial' => $response->json('tss_serial_number'),
            'qr_code_data' => $response->json('qr_code_data'),
        ];
    }

    /**
     * @return list<array{vat_rate: string, amount: string}>
     */
    private function amountsPerVatRate(Order $order, int $sign): array
    {
        $byRate = [];
        foreach ($order->items as $item) {
            /** @var OrderItem $item */
            $byRate[$item->vat_rate_bp] = ($byRate[$item->vat_rate_bp] ?? 0) + $item->lineTotalCents();
        }

        $result = [];
        foreach ($byRate as $rate => $cents) {
            $result[] = ['vat_rate' => $rate === 1900 ? 'NORMAL' : 'REDUCED_1', 'amount' => $this->euro($sign * $cents)];
        }

        return $result;
    }

    private function euro(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)->withToken($this->token())->timeout(15)->acceptJson();
    }

    private function token(): string
    {
        return Cache::remember('fiskaly-token', 240, function (): string {
            $token = Http::baseUrl(self::BASE_URL)
                ->post('/auth', ['api_key' => $this->apiKey, 'api_secret' => $this->apiSecret])
                ->throw()
                ->json('access_token');

            return is_string($token) ? $token : throw new RuntimeException('fiskaly auth failed');
        });
    }
}
