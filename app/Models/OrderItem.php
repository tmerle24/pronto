<?php

namespace App\Models;

use App\Enums\ItemVariant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property ?int $menu_item_id
 * @property string $name
 * @property ?ItemVariant $variant
 * @property int $unit_price_cents
 * @property int $unit_deposit_cents
 * @property int $vat_rate_bp
 * @property string $unit_oven_units
 * @property int $quantity
 * @property-read ?MenuItem $menuItem
 */
#[Fillable([
    'menu_item_id', 'name', 'variant', 'unit_price_cents', 'unit_deposit_cents',
    'vat_rate_bp', 'unit_oven_units', 'quantity',
])]
class OrderItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'variant' => ItemVariant::class,
            'unit_price_cents' => 'integer',
            'unit_deposit_cents' => 'integer',
            'vat_rate_bp' => 'integer',
            'unit_oven_units' => 'decimal:2',
            'quantity' => 'integer',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<MenuItem, $this> */
    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function lineTotalCents(): int
    {
        return ($this->unit_price_cents + $this->unit_deposit_cents) * $this->quantity;
    }

    public function displayName(): string
    {
        return $this->variant ? $this->name.' '.$this->variant->label() : $this->name;
    }
}
