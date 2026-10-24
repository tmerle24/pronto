<?php

namespace App\Services\Staffing;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\EventCost;
use App\Models\MarketDay;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Profit of one market day and how it is shared: everyone gets the same share per hour worked.
 *
 * Revenue: paid orders queued that day, without deposit and VAT. Costs: card fees (estimated),
 * costs of the day and an even part of the event's shared costs (stand fee, registration, ...).
 */
final class DaySettlement
{
    private const CARD_METHODS = [PaymentMethod::Online, PaymentMethod::TapToPay, PaymentMethod::CounterCard];

    public function __construct(public readonly MarketDay $day) {}

    /**
     * @return array{
     *     orders: int, gross_cents: int, vat_cents: int, net_cents: int, card_payments: int, card_fee_cents: int,
     *     costs: list<array{label: string, cents: int, shared: bool}>, costs_cents: int, profit_cents: int,
     *     hours: float, people: list<array{user_id: int, name: string, hours: float, share: float, payout_cents: int}>
     * }
     */
    public function summary(): array
    {
        $orders = $this->orders();
        $gross = $vat = 0;
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $line = $item->unit_price_cents * $item->quantity;
                $gross += $line;
                $vat += (int) round($line * $item->vat_rate_bp / (10000 + $item->vat_rate_bp));
            }
        }

        $card = $orders->filter(fn (Order $o) => in_array($o->payment_method, self::CARD_METHODS, true));
        $cardFee = (int) round($card->sum(fn (Order $o) => $o->total_cents * config('pronto.staffing.card_fee_bp') / 10000
            + config('pronto.staffing.card_fee_fixed_cents')));

        $costs = $this->costs();
        $costsCents = array_sum(array_column($costs, 'cents'));
        $profit = $gross - $vat - $cardFee - $costsCents;
        [$hours, $people] = $this->share(max(0, $profit));

        return [
            'orders' => $orders->count(),
            'gross_cents' => $gross,
            'vat_cents' => $vat,
            'net_cents' => $gross - $vat,
            'card_payments' => $card->count(),
            'card_fee_cents' => $cardFee,
            'costs' => $costs,
            'costs_cents' => $costsCents,
            'profit_cents' => $profit,
            'hours' => $hours,
            'people' => $people,
        ];
    }

    /**
     * Payout of one person, null if they have no shift that day.
     *
     * @return ?array{user_id: int, name: string, hours: float, share: float, payout_cents: int}
     */
    public function payoutFor(User $user): ?array
    {
        foreach ($this->summary()['people'] as $person) {
            if ($person['user_id'] === $user->id) {
                return $person;
            }
        }

        return null;
    }

    /** @return Collection<int, Order> */
    private function orders(): Collection
    {
        $date = $this->day->date;

        return Order::query()
            ->where('event_id', $this->day->event_id)
            ->where('payment_status', PaymentStatus::Paid)
            ->whereBetween('queued_at', [$date->startOfDay(), $date->endOfDay()])
            ->with('items')
            ->get();
    }

    /**
     * @return list<array{label: string, cents: int, shared: bool}>
     */
    private function costs(): array
    {
        $days = max(1, $this->day->event->marketDays()->count());

        $costs = [];
        $rows = EventCost::query()
            ->where('event_id', $this->day->event_id)
            ->where(fn ($q) => $q->whereNull('market_day_id')->orWhere('market_day_id', $this->day->id))
            ->orderBy('id')
            ->get();
        foreach ($rows as $c) {
            $shared = $c->market_day_id === null;
            $costs[] = [
                'label' => $shared && $days > 1 ? "{$c->label} (1/{$days})" : $c->label,
                'cents' => $shared ? intdiv($c->amount_cents, $days) : $c->amount_cents,
                'shared' => $shared,
            ];
        }

        return $costs;
    }

    /**
     * Profit split by hours; rounding cents go to the people with the most hours, so the sum is exact.
     *
     * @return array{0: float, 1: list<array{user_id: int, name: string, hours: float, share: float, payout_cents: int}>}
     */
    private function share(int $profit): array
    {
        $byUser = [];
        foreach ($this->day->shifts()->with('user')->get() as $shift) {
            $byUser[$shift->user_id] ??= ['user_id' => $shift->user_id, 'name' => $shift->user->name, 'hours' => 0.0];
            $byUser[$shift->user_id]['hours'] += $shift->hours();
        }
        usort($byUser, fn (array $a, array $b) => $b['hours'] <=> $a['hours']);

        $total = (float) array_sum(array_column($byUser, 'hours'));
        $payouts = array_map(fn (array $p) => $total > 0 ? (int) floor($profit * $p['hours'] / $total) : 0, $byUser);
        $rest = $total > 0 ? $profit - array_sum($payouts) : 0;
        for ($i = 0; $rest > 0 && $payouts !== []; $i = ($i + 1) % count($payouts), $rest--) {
            $payouts[$i]++;
        }

        $people = [];
        foreach ($byUser as $i => $p) {
            $people[] = [
                'user_id' => $p['user_id'],
                'name' => $p['name'],
                'hours' => $p['hours'],
                'share' => $total > 0 ? $p['hours'] / $total : 0.0,
                'payout_cents' => $payouts[$i],
            ];
        }

        return [$total, $people];
    }
}
