<?php

namespace App\Support;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Models\Order;
use App\Models\OrderItem;

/**
 * What the guest sees of an order. No personal data.
 */
final class GuestOrderView
{
    private static function whatsAppLink(Order $order): ?string
    {
        $number = config('pronto.whatsapp.number');
        if (blank($number) || $order->pickup_number === null) {
            return null;
        }

        return 'https://wa.me/'.$number.'?text='.rawurlencode("Bestellung {$order->pickup_number} {$order->short_code}");
    }

    /**
     * @return array<string, mixed>
     */
    public static function make(Order $order): array
    {
        return [
            'token' => $order->public_token,
            'number' => $order->pickup_number,
            'short_code' => $order->short_code,
            'status' => $order->status->value,
            'payment_method' => $order->payment_method->value,
            'payment_status' => $order->payment_status->value,
            'queued' => $order->isQueued(),
            'total_cents' => $order->total_cents,
            'created_at' => $order->created_at->toIso8601String(),
            'ready_at' => $order->ready_at?->toIso8601String(),
            // which channels will ring, never the contact data
            'channels' => $order->notificationSubscriptions()
                ->whereIn('status', [NotificationStatus::Active, NotificationStatus::Sent])
                ->pluck('channel')
                ->map(fn (NotificationChannel $c) => $c->value)
                ->unique()
                ->values()
                ->all(),
            'whatsapp_link' => self::whatsAppLink($order),
            'items' => $order->items->map(fn (OrderItem $item) => [
                'name' => $item->name,
                'variant' => $item->variant?->value,
                'quantity' => $item->quantity,
                'line_total_cents' => $item->lineTotalCents(),
                'deposit_cents' => $item->unit_deposit_cents * $item->quantity,
            ])->all(),
        ];
    }
}
