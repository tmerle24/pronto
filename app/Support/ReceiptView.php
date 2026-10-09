<?php

namespace App\Support;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;

/** Digital receipt: positions, VAT per rate, totals. Prices are gross. */
final class ReceiptView
{
    public static function available(Order $order): bool
    {
        return $order->receipt_number !== null && in_array($order->payment_status, [PaymentStatus::Paid, PaymentStatus::Refunded], true);
    }

    /**
     * @return array<string, mixed>
     */
    public static function make(Order $order): array
    {
        $lines = [];
        $vat = [];
        foreach ($order->items as $item) {
            /** @var OrderItem $item */
            $gross = $item->lineTotalCents();
            $lines[] = [
                'quantity' => $item->quantity,
                'name' => trim($item->name.' '.($item->variant?->label() ?? '')),
                'unit_cents' => $item->unit_price_cents,
                'deposit_cents' => $item->unit_deposit_cents * $item->quantity,
                'total_cents' => $gross,
                'vat_rate' => $item->vat_rate_bp / 100,
            ];
            $vat[$item->vat_rate_bp] = ($vat[$item->vat_rate_bp] ?? 0) + $gross;
        }
        ksort($vat);

        $groups = [];
        foreach ($vat as $rate => $gross) {
            $net = (int) round($gross * 10000 / (10000 + $rate));
            $groups[] = ['rate' => $rate / 100, 'net_cents' => $net, 'vat_cents' => $gross - $net, 'gross_cents' => $gross];
        }

        $fiscal = $order->fiscal_data['sale'] ?? null;

        return [
            'number' => $order->receipt_number,
            'pickup_number' => $order->pickup_number,
            'date' => $order->paid_at?->format('d.m.Y H:i'),
            'payment' => $order->payment_method->label(),
            'refunded' => $order->payment_status === PaymentStatus::Refunded,
            'lines' => $lines,
            'vat' => $groups,
            'total_cents' => $order->total_cents,
            'business' => config('pronto.business'),
            'fiscal' => is_array($fiscal) ? [
                'tss_serial' => $fiscal['tss_serial'] ?? null,
                'number' => $fiscal['number'] ?? null,
                'signature_counter' => $fiscal['signature_counter'] ?? null,
                'time_start' => $fiscal['time_start'] ?? null,
                'time_end' => $fiscal['time_end'] ?? null,
                'signature' => $fiscal['signature'] ?? null,
            ] : null,
        ];
    }
}
