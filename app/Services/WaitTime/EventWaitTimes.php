<?php

namespace App\Services\WaitTime;

use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use Illuminate\Support\Collection;

/**
 * Loads an event's open orders and today's measurements once and computes
 * wait times for every open order plus the general one for new guests.
 */
final class EventWaitTimes
{
    /** @var Collection<int, Order> */
    private Collection $open;

    /** @var list<float> */
    private array $samples;

    public function __construct(
        private readonly Event $event,
        private readonly WaitTimeEstimator $estimator = new WaitTimeEstimator,
    ) {
        $this->open = Order::query()
            ->where('event_id', $event->id)
            ->whereNotNull('queued_at')
            ->whereIn('status', [OrderStatus::New, OrderStatus::InProgress])
            ->orderBy('queued_at')
            ->orderBy('id')
            ->get(['id', 'pickup_number', 'status', 'oven_units', 'queued_at', 'started_at']);

        $this->samples = $this->todaysSamples();
    }

    /** For someone ordering now: all open orders ahead, one unit own. */
    public function general(): WaitTimeRange
    {
        return $this->estimator->estimate($this->input($this->unitsOf($this->open), 1.0));
    }

    /**
     * @return array{low: int, high: int, ahead: int}|null null once ready or not queued
     */
    public function forOrder(Order $order): ?array
    {
        $index = $this->open->search(fn (Order $o) => $o->id === $order->id);
        if ($index === false) {
            return null;
        }

        /** @var Order $own */
        $own = $this->open[$index];
        $ahead = $this->open->take($index);
        $elapsed = $own->status === OrderStatus::InProgress && $own->started_at !== null
            ? $own->started_at->diffInSeconds(now()) / 60
            : 0.0;

        $range = $this->estimator->estimate($this->input($this->unitsOf($ahead), (float) $own->oven_units, $elapsed));

        return $range->toArray() + ['ahead' => $ahead->count()];
    }

    /**
     * Keyed by pickup number, numbers are public anyway (display).
     *
     * @return array<int, array{low: int, high: int, ahead: int}>
     */
    public function allOpen(): array
    {
        $result = [];
        foreach ($this->open as $order) {
            $wait = $this->forOrder($order);
            if ($wait !== null && $order->pickup_number !== null) {
                $result[$order->pickup_number] = $wait;
            }
        }

        return $result;
    }

    public function minutesPerBake(): float
    {
        return $this->estimator->minutesPerBake($this->event->minutes_per_bake, $this->samples);
    }

    private function input(float $unitsAhead, float $ownUnits, float $elapsed = 0.0): WaitTimeInput
    {
        return new WaitTimeInput(
            ovenSlots: $this->event->oven_slots,
            startMinutesPerBake: $this->event->minutes_per_bake,
            bufferMinutes: $this->event->buffer_minutes,
            unitsAhead: $unitsAhead,
            ownUnits: $ownUnits,
            samples: $this->samples,
            pauseMinutes: $this->event->pauseMinutesLeft(),
            elapsedMinutes: $elapsed,
        );
    }

    /**
     * @param  Collection<int, Order>  $orders
     */
    private function unitsOf(Collection $orders): float
    {
        return (float) $orders->sum(fn (Order $o) => (float) $o->oven_units);
    }

    /**
     * @return list<float>
     */
    private function todaysSamples(): array
    {
        return array_values(Order::query()
            ->where('event_id', $this->event->id)
            ->whereNotNull('started_at')
            ->whereNotNull('ready_at')
            ->where('ready_at', '>=', today())
            ->orderBy('ready_at')
            ->get(['oven_units', 'started_at', 'ready_at'])
            ->map(fn (Order $o) => $o->started_at === null || $o->ready_at === null ? null : WaitTimeEstimator::sample(
                $o->started_at->diffInSeconds($o->ready_at) / 60,
                (float) $o->oven_units,
                $this->event->oven_slots,
            ))
            ->filter(fn (?float $s) => $s !== null)
            ->all());
    }
}
