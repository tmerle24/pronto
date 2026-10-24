<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Flammkuchen card of 24.10.2026 (Altstadtmarkt Oberursel): new names, prices, gratinated variants,
 * Bella Banana, drink prices. Runs before the seeder on deploy, so the seeder finds the renamed items.
 * Data only, no down(): prices are changed in the admin afterwards.
 */
return new class extends Migration
{
    private const RENAMES = [
        // old name, old variant => new name, new variant
        ['Flammkuchen Klassiker', null, 'Nonno Classico', null],
        ['Flammkuchen Vegetarisch', null, 'Viva Vegetaria', null],
        ['Flammkuchen Apfel-Zimt', 'whole', 'Primo Amore', null],
    ];

    public function up(): void
    {
        $now = now();
        $items = DB::table('menu_items');

        foreach (self::RENAMES as [$old, $oldVariant, $new, $newVariant]) {
            $row = $this->find($old, 'flammkuchen', $oldVariant);
            if ($row === null) {
                continue;
            }
            $exists = $this->find($new, 'flammkuchen', $newVariant) !== null;
            $items->clone()->where('id', $row->id)->update($exists
                ? ['is_available' => false, 'updated_at' => $now]
                : ['name' => $new, 'name_en' => null, 'variant' => $newVariant, 'updated_at' => $now]);
        }

        // not on the card any more
        $items->clone()->where('name', 'Flammkuchen Apfel-Zimt')->where('edition', 'flammkuchen')->where('variant', 'slice')
            ->update(['is_available' => false, 'updated_at' => $now]);

        foreach (self::flammkuchen() as $sort => [$name, $variant, $desc, $descEn, $price, $allergens, $additives, $icon]) {
            $values = [
                'description' => $desc,
                'description_en' => $descEn,
                'price_cents' => $price,
                'allergens' => $allergens,
                'additives' => $additives,
                'icon' => $icon,
                'is_available' => true,
                'sort' => 50 + $sort * 10,
                'updated_at' => $now,
            ];
            $row = $this->find($name, 'flammkuchen', $variant);
            if ($row !== null) {
                $items->clone()->where('id', $row->id)->update($values);
            } else {
                $items->clone()->insert($values + [
                    'name' => $name,
                    'category' => 'flammkuchen',
                    'edition' => 'flammkuchen',
                    'variant' => $variant,
                    'deposit_cents' => 0,
                    'vat_rate_bp' => 700,
                    'oven_units' => '1.00',
                    'created_at' => $now,
                ]);
            }
        }

        // price on the card without deposit, deposit 0,25 € stays
        $items->clone()->where('name', 'Cola')->whereNull('edition')->update(['price_cents' => 225, 'additives' => '1, 2', 'sort' => 110, 'updated_at' => $now]);
        $items->clone()->where('name', 'Wasser')->whereNull('edition')->update(['price_cents' => 175, 'additives' => null, 'sort' => 120, 'updated_at' => $now]);
    }

    /**
     * Same data as in MenuSeeder. Allergens and additives with the codes of the printed card.
     *
     * @return list<array{string, ?string, string, string, int, string, ?string, string}>
     */
    private static function flammkuchen(): array
    {
        $classico = ['Crème fraîche, Speck, Zwiebeln, Schnittlauch', 'Crème fraîche, bacon, onions, chives'];
        $vegetaria = ['Crème fraîche, Feta, Zwiebeln, Oliven, milde Chilis', 'Crème fraîche, feta, onions, olives, mild chillies'];

        return [
            ['Nonno Classico', null, ...$classico, 650, 'a, b', '3', 'flammkuchen-klassiker'],
            ['Nonno Classico', 'gratinated', $classico[0].', gratiniert', $classico[1].', gratinated', 750, 'a, b', '3', 'flammkuchen-klassiker'],
            ['Viva Vegetaria', null, ...$vegetaria, 650, 'a, b', null, 'flammkuchen-vegetarisch'],
            ['Viva Vegetaria', 'gratinated', $vegetaria[0].', gratiniert', $vegetaria[1].', gratinated', 750, 'a, b', null, 'flammkuchen-vegetarisch'],
            ['Primo Amore', null, 'Apfel & Zimt: Crème fraîche mit Rohrzucker & Zimt, Apfel, Mandelsplitter', 'Apple & cinnamon: crème fraîche with cane sugar & cinnamon, apple, flaked almonds', 600, 'a, b, c', null, 'flammkuchen-dessert'],
            ['Bella Banana', null, 'Nutella & Banane: Nutella, Banane, Crème fraîche mit Ahornsirup', 'Nutella & banana: Nutella, banana, crème fraîche with maple syrup', 600, 'a, b, c, d', null, 'flammkuchen-banane'],
        ];
    }

    private function find(string $name, string $edition, ?string $variant): ?object
    {
        return DB::table('menu_items')->where('name', $name)->where('edition', $edition)
            ->when($variant === null, fn ($q) => $q->whereNull('variant'), fn ($q) => $q->where('variant', $variant))
            ->orderBy('id')->first();
    }
};
