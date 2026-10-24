<?php

namespace Database\Seeders;

use App\Enums\ItemVariant;
use App\Enums\MenuCategory;
use App\Enums\MenuEdition;
use App\Models\MenuItem;
use Illuminate\Database\Seeder;

/**
 * Start menu. Existing items are left untouched.
 */
class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $pizza = MenuEdition::Pizza;
        $flamm = MenuEdition::Flammkuchen;
        $whole = ItemVariant::Whole;
        $slice = ItemVariant::Slice;
        $gratinated = ItemVariant::Gratinated;

        // name, name_en, description, description_en, category, edition, variant, price, deposit, vat, allergens, additives, oven units
        $items = [
            ['Margherita', null, 'Tomate, Mozzarella, Basilikum, Olivenöl', 'Tomato, mozzarella, basil, olive oil', MenuCategory::Pizza, $pizza, $whole, 900, 0, 700, 'A, G', null, '1.00'],
            ['Margherita', null, 'Tomate, Mozzarella, Basilikum, Olivenöl', 'Tomato, mozzarella, basil, olive oil', MenuCategory::Pizza, $pizza, $slice, 300, 0, 700, 'A, G', null, '0.25'],
            ['Salami', null, 'Tomate, Mozzarella, italienische Salami', 'Tomato, mozzarella, Italian salami', MenuCategory::Pizza, $pizza, $whole, 950, 0, 700, 'A, G', '2, 3', '1.00'],
            ['Salami', null, 'Tomate, Mozzarella, italienische Salami', 'Tomato, mozzarella, Italian salami', MenuCategory::Pizza, $pizza, $slice, 350, 0, 700, 'A, G', '2, 3', '0.25'],
            ['Nonno Classico', null, 'Crème fraîche, Speck, Zwiebeln, Schnittlauch', 'Crème fraîche, bacon, onions, chives', MenuCategory::Flammkuchen, $flamm, null, 650, 0, 700, 'a, b', '3', '1.00'],
            ['Nonno Classico', null, 'Crème fraîche, Speck, Zwiebeln, Schnittlauch, gratiniert', 'Crème fraîche, bacon, onions, chives, gratinated', MenuCategory::Flammkuchen, $flamm, $gratinated, 750, 0, 700, 'a, b', '3', '1.00'],
            ['Viva Vegetaria', null, 'Crème fraîche, Feta, Zwiebeln, Oliven, milde Chilis', 'Crème fraîche, feta, onions, olives, mild chillies', MenuCategory::Flammkuchen, $flamm, null, 650, 0, 700, 'a, b', null, '1.00'],
            ['Viva Vegetaria', null, 'Crème fraîche, Feta, Zwiebeln, Oliven, milde Chilis, gratiniert', 'Crème fraîche, feta, onions, olives, mild chillies, gratinated', MenuCategory::Flammkuchen, $flamm, $gratinated, 750, 0, 700, 'a, b', null, '1.00'],
            ['Primo Amore', null, 'Apfel & Zimt: Crème fraîche mit Rohrzucker & Zimt, Apfel, Mandelsplitter', 'Apple & cinnamon: crème fraîche with cane sugar & cinnamon, apple, flaked almonds', MenuCategory::Flammkuchen, $flamm, null, 600, 0, 700, 'a, b, c', null, '1.00'],
            ['Bella Banana', null, 'Nutella & Banane: Nutella, Banane, Crème fraîche mit Ahornsirup', 'Nutella & banana: Nutella, banana, crème fraîche with maple syrup', MenuCategory::Flammkuchen, $flamm, null, 600, 0, 700, 'a, b, c, d', null, '1.00'],
            ['Cola', null, 'Dose 0,33 l', 'Can 0.33 l', MenuCategory::Drink, null, null, 225, 25, 1900, null, '1, 2', '0.00'],
            ['Wasser', 'Water', 'Flasche 0,33 l', 'Bottle 0.33 l', MenuCategory::Drink, null, null, 175, 25, 1900, null, null, '0.00'],
        ];

        $icons = ['Margherita' => 'margherita', 'Salami' => 'salami', 'Nonno Classico' => 'flammkuchen-klassiker',
            'Viva Vegetaria' => 'flammkuchen-vegetarisch', 'Primo Amore' => 'flammkuchen-dessert', 'Bella Banana' => 'flammkuchen-banane',
            'Cola' => 'cola', 'Wasser' => 'wasser'];

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
                    'icon' => $icons[$name],
                ],
            );
        }
    }
}
