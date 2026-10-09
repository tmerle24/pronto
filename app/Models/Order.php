<?php

namespace App\Models;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Carbon\CarbonImmutable;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Never deleted, only cancelled (audit trail in order_events).
 *
 * @property int $id
 * @property int $event_id
 * @property ?int $pickup_number
 * @property string $public_token
 * @property string $short_code
 * @property OrderStatus $status
 * @property PaymentMethod $payment_method
 * @property PaymentStatus $payment_status
 * @property OrderSource $source
 * @property int $total_cents
 * @property string $oven_units
 * @property string $locale
 * @property ?string $stripe_payment_intent_id
 * @property ?int $created_by_user_id
 * @property ?CarbonImmutable $queued_at
 * @property ?CarbonImmutable $started_at
 * @property ?CarbonImmutable $ready_at
 * @property ?CarbonImmutable $picked_up_at
 * @property ?CarbonImmutable $cancelled_at
 * @property ?CarbonImmutable $paid_at
 * @property ?CarbonImmutable $customer_notified_at
 * @property ?string $receipt_number
 * @property ?array<string, mixed> $fiscal_data
 * @property ?string $cancel_reason
 * @property ?CarbonImmutable $refunded_at
 * @property CarbonImmutable $created_at
 * @property-read Event $event
 */
#[Fillable([
    'event_id', 'public_token', 'short_code', 'status', 'payment_method', 'payment_status',
    'source', 'total_cents', 'oven_units', 'locale', 'stripe_payment_intent_id', 'created_by_user_id',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pickup_number' => 'integer',
            'status' => OrderStatus::class,
            'payment_method' => PaymentMethod::class,
            'payment_status' => PaymentStatus::class,
            'source' => OrderSource::class,
            'total_cents' => 'integer',
            'oven_units' => 'decimal:2',
            'queued_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'ready_at' => 'immutable_datetime',
            'picked_up_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
            'customer_notified_at' => 'immutable_datetime',
            'fiscal_data' => 'array',
            'refunded_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasMany<OrderEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class)->orderBy('id');
    }

    /** @return HasMany<NotificationSubscription, $this> */
    public function notificationSubscriptions(): HasMany
    {
        return $this->hasMany(NotificationSubscription::class);
    }

    /**
     * Visible in kitchen queue and display.
     *
     * @param  Builder<Order>  $query
     */
    public function scopeInQueue(Builder $query): void
    {
        $query->whereNotNull('queued_at')
            ->whereIn('status', [OrderStatus::New, OrderStatus::InProgress, OrderStatus::Ready])
            ->orderBy('queued_at')
            ->orderBy('id');
    }

    public function isQueued(): bool
    {
        return $this->queued_at !== null;
    }

    public function isCashOpen(): bool
    {
        return $this->payment_method === PaymentMethod::Cash && $this->payment_status === PaymentStatus::Pending;
    }

    public function getRouteKeyName(): string
    {
        return 'public_token';
    }
}
