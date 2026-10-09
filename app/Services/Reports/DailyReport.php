<?php

namespace App\Services\Reports;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Day summary for one event: orders, revenue by payment, times, best sellers. */
final class DailyReport
{
    /** @var Collection<int, Order> */
    private Collection $orders;

    public function __construct(
        public readonly Event $event,
        public readonly ?CarbonImmutable $day = null,
    ) {
        $this->orders = $this->query()->with('items')->orderBy('id')->get();
    }

    /** @return Builder<Order> */
    public function query(): Builder
    {
        return Order::query()
            ->where('event_id', $this->event->id)
            ->whereNotNull('queued_at')
            ->when($this->day, fn (Builder $q, CarbonImmutable $day) => $q->whereBetween('queued_at', [$day->startOfDay(), $day->endOfDay()]));
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $paid = $this->orders->filter(fn (Order $o) => $o->payment_status === PaymentStatus::Paid);

        $byMethod = [];
        foreach (PaymentMethod::cases() as $method) {
            $orders = $paid->filter(fn (Order $o) => $o->payment_method === $method);
            $byMethod[] = ['method' => $method->label(), 'count' => $orders->count(), 'cents' => (int) $orders->sum('total_cents')];
        }

        return [
            'orders' => $this->orders->count(),
            'picked_up' => $this->orders->filter(fn (Order $o) => $o->status === OrderStatus::PickedUp)->count(),
            'cancelled' => $this->orders->filter(fn (Order $o) => $o->status === OrderStatus::Cancelled)->count(),
            'open_cash' => $this->orders->filter(fn (Order $o) => $o->isCashOpen() && $o->status !== OrderStatus::Cancelled)->count(),
            'revenue_cents' => (int) $paid->sum('total_cents'),
            'deposit_cents' => (int) $paid->sum(fn (Order $o) => $o->items->sum(fn (OrderItem $i) => $i->unit_deposit_cents * $i->quantity)),
            'refunded_cents' => (int) $this->orders->filter(fn (Order $o) => $o->payment_status === PaymentStatus::Refunded)->sum('total_cents'),
            'by_method' => $byMethod,
            'avg_wait' => $this->avgMinutes('queued_at', 'ready_at'),
            'avg_oven' => $this->avgMinutes('started_at', 'ready_at'),
            'avg_pickup' => $this->avgMinutes('ready_at', 'picked_up_at'),
            'top_items' => $this->topItems(),
            'per_hour' => $this->perHour(),
        ];
    }

    /**
     * @return list<array{name: string, quantity: int, cents: int}>
     */
    public function topItems(): array
    {
        $items = [];
        foreach ($this->orders as $order) {
            if ($order->status === OrderStatus::Cancelled) {
                continue;
            }
            foreach ($order->items as $item) {
                $name = trim($item->name.' '.($item->variant?->label() ?? ''));
                $items[$name] ??= ['name' => $name, 'quantity' => 0, 'cents' => 0];
                $items[$name]['quantity'] += $item->quantity;
                $items[$name]['cents'] += $item->lineTotalCents();
            }
        }
        $items = array_values($items);
        usort($items, fn ($a, $b) => $b['quantity'] <=> $a['quantity']);

        return $items;
    }

    /**
     * @return array<string, int> "18:00" => orders
     */
    public function perHour(): array
    {
        $hours = [];
        foreach ($this->orders as $order) {
            $hour = $order->queued_at?->format('H:00');
            if ($hour !== null) {
                $hours[$hour] = ($hours[$hour] ?? 0) + 1;
            }
        }
        ksort($hours);

        return $hours;
    }

    /**
     * One row per order, for the CSV export.
     *
     * @return list<list<string|int>>
     */
    public function rows(): array
    {
        $rows = [['Nr', 'Zeit', 'Status', 'Zahlungsart', 'Zahlstatus', 'Summe EUR', 'Beleg', 'Quelle', 'Positionen', 'Wartezeit Min']];
        foreach ($this->orders as $order) {
            $rows[] = [
                (int) $order->pickup_number,
                (string) $order->queued_at?->format('d.m.Y H:i'),
                $order->status->label(),
                $order->payment_method->label(),
                $order->payment_status->label(),
                number_format($order->total_cents / 100, 2, ',', ''),
                (string) $order->receipt_number,
                $order->source->value,
                $order->items->map(fn (OrderItem $i) => $i->quantity.'x '.trim($i->name.' '.($i->variant?->label() ?? '')))->implode(', '),
                $order->ready_at && $order->queued_at ? (int) round($order->queued_at->diffInSeconds($order->ready_at) / 60) : '',
            ];
        }

        return $rows;
    }

    private function avgMinutes(string $from, string $to): ?float
    {
        $values = $this->orders
            ->filter(fn (Order $o) => $o->{$from} !== null && $o->{$to} !== null)
            ->map(fn (Order $o) => $o->{$from}->diffInSeconds($o->{$to}) / 60);

        return $values->isEmpty() ? null : round((float) $values->avg(), 1);
    }
}
