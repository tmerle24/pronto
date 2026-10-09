<?php

namespace App\Models;

use App\Enums\ItemVariant;
use App\Enums\MenuCategory;
use App\Enums\MenuEdition;
use Database\Factories\MenuItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property ?string $description
 * @property ?string $name_en
 * @property ?string $description_en
 * @property MenuCategory $category
 * @property ?MenuEdition $edition
 * @property ?ItemVariant $variant
 * @property int $price_cents
 * @property int $deposit_cents
 * @property int $vat_rate_bp
 * @property ?string $allergens
 * @property ?string $additives
 * @property string $oven_units
 * @property bool $is_sold_out
 * @property bool $is_available
 * @property int $sort
 */
#[Fillable([
    'name', 'description', 'name_en', 'description_en', 'category', 'edition', 'variant', 'price_cents', 'deposit_cents',
    'vat_rate_bp', 'allergens', 'additives', 'oven_units', 'is_sold_out', 'is_available', 'sort',
])]
class MenuItem extends Model
{
    /** @use HasFactory<MenuItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => MenuCategory::class,
            'edition' => MenuEdition::class,
            'variant' => ItemVariant::class,
            'price_cents' => 'integer',
            'deposit_cents' => 'integer',
            'vat_rate_bp' => 'integer',
            'oven_units' => 'decimal:2',
            'is_sold_out' => 'boolean',
            'is_available' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /**
     * Items for the given editions; drinks (edition null) always.
     *
     * @param  Builder<MenuItem>  $query
     * @param  iterable<MenuEdition>  $editions
     */
    public function scopeForEditions(Builder $query, iterable $editions): void
    {
        $values = collect($editions)->map(fn (MenuEdition $e) => $e->value)->all();

        $query->where('is_available', true)
            ->where(fn (Builder $q) => $q->whereNull('edition')->orWhereIn('edition', $values))
            ->orderBy('sort')
            ->orderBy('id');
    }

    public function isOrderable(): bool
    {
        return $this->is_available && ! $this->is_sold_out;
    }

    public function localizedName(string $locale): string
    {
        return $locale === 'en' && $this->name_en ? $this->name_en : $this->name;
    }

    public function localizedDescription(string $locale): ?string
    {
        return $locale === 'en' && $this->description_en ? $this->description_en : $this->description;
    }
}
