<?php

namespace Database\Seeders;

use App\Enums\ItemVariant;
use App\Enums\MenuCategory;
use App\Enums\MenuEdition;
use App\Models\MenuItem;
use Illuminate\Database\Seeder;

/**
 * Start menu, prices as on tiamo-pizza.de. Existing items are left untouched.
 */
class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $pizza = MenuEdition::Pizza;
        $flamm = MenuEdition::Flammkuchen;
        $whole = ItemVariant::Whole;
        $slice = ItemVariant::Slice;

        // name, name_en, description, description_en, category, edition, variant, price, deposit, vat, allergens, additives, oven units
        $items = [
            ['Margherita', null, 'Tomate, Mozzarella, Basilikum, Olivenöl', 'Tomato, mozzarella, basil, olive oil', MenuCategory::Pizza, $pizza, $whole, 950, 0, 700, 'A, G', null, '1.00'],
            ['Margherita', null, 'Tomate, Mozzarella, Basilikum, Olivenöl', 'Tomato, mozzarella, basil, olive oil', MenuCategory::Pizza, $pizza, $slice, 350, 0, 700, 'A, G', null, '0.25'],
            ['Salami', null, 'Tomate, Mozzarella, italienische Salami', 'Tomato, mozzarella, Italian salami', MenuCategory::Pizza, $pizza, $whole, 1050, 0, 700, 'A, G', '2, 3', '1.00'],
            ['Salami', null, 'Tomate, Mozzarella, italienische Salami', 'Tomato, mozzarella, Italian salami', MenuCategory::Pizza, $pizza, $slice, 400, 0, 700, 'A, G', '2, 3', '0.25'],
            ['Flammkuchen Klassiker', 'Flammkuchen classic', 'Crème fraîche, Speck, Lauch, Zwiebeln', 'Crème fraîche, bacon, leek, onions', MenuCategory::Flammkuchen, $flamm, null, 950, 0, 700, 'A, G', '2, 3', '1.00'],
            ['Flammkuchen Vegetarisch', 'Flammkuchen vegetarian', 'Crème fraîche, Lauch, Champignons, Bergkäse', 'Crème fraîche, leek, mushrooms, mountain cheese', MenuCategory::Flammkuchen, $flamm, null, 900, 0, 700, 'A, G', null, '1.00'],
            ['Cola', null, 'Dose 0,33 l', 'Can 0.33 l', MenuCategory::Drink, null, null, 300, 25, 1900, null, '1, 9', '0.00'],
            ['Wasser', 'Water', 'Flasche 0,33 l', 'Bottle 0.33 l', MenuCategory::Drink, null, null, 250, 25, 1900, null, null, '0.00'],
        ];

        $icons = ['Margherita' => 'margherita', 'Salami' => 'salami', 'Flammkuchen Klassiker' => 'flammkuchen-klassiker',
            'Flammkuchen Vegetarisch' => 'flammkuchen-vegetarisch', 'Cola' => 'cola', 'Wasser' => 'wasser'];

        foreach ($items as $sort => [$name, $nameEn, $desc, $descEn, $category, $edition, $variant, $price, $deposit, $vat, $allergens, $additives, $units]) {
            MenuItem::query()->firstOrCreate(
                ['name' => $name, 'edition' => $edition, 'variant' => $variant],
                [
                    'name_en' => $nameEn,
                    'description' => $desc,
                    'description_en' => $descEn,
                    'category' => $category,
                    'price_cents' => $price,
                    'deposit_cents' => $deposit,
                    'vat_rate_bp' => $vat,
                    'allergens' => $allergens,
                    'additives' => $additives,
                    'oven_units' => $units,
                    'sort' => ($sort + 1) * 10,
                    'icon' => $icons[$name] ?? null,
                ],
            );
        }
    }
}
