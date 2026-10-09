<?php

namespace Database\Factories;

use App\Enums\ItemVariant;
use App\Enums\MenuCategory;
use App\Enums\MenuEdition;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Margherita',
            'description' => 'Tomate, Mozzarella',
            'category' => MenuCategory::Pizza,
            'edition' => MenuEdition::Pizza,
            'variant' => ItemVariant::Whole,
            'price_cents' => 1000,
            'deposit_cents' => 0,
            'vat_rate_bp' => 700,
            'oven_units' => '1.00',
        ];
    }

    public function slice(): static
    {
        return $this->state(['variant' => ItemVariant::Slice, 'price_cents' => 300, 'oven_units' => '0.25']);
    }

    public function drink(): static
    {
        return $this->state([
            'name' => 'Cola',
            'description' => 'Dose 0,33 l',
            'category' => MenuCategory::Drink,
            'edition' => null,
            'variant' => null,
            'price_cents' => 300,
            'deposit_cents' => 25,
            'vat_rate_bp' => 1900,
            'oven_units' => '0.00',
        ]);
    }
}
