<?php

namespace Tests\Feature;

use App\Enums\ItemVariant;
use App\Enums\MenuCategory;
use App\Enums\MenuEdition;
use App\Models\MenuItem;
use Database\Seeders\MenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlammkuchenMenuMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // the migration already ran on the empty test database and created the card
        MenuItem::query()->delete();
    }

    private function migrate(): void
    {
        (require database_path('migrations/2026_10_24_100000_flammkuchen_menu_altstadtmarkt.php'))->up();
    }

    private function old(string $name, ?ItemVariant $variant, int $price): MenuItem
    {
        return MenuItem::factory()->create([
            'name' => $name, 'category' => MenuCategory::Flammkuchen, 'edition' => MenuEdition::Flammkuchen,
            'variant' => $variant, 'price_cents' => $price, 'oven_units' => '1.00',
        ]);
    }

    public function test_production_menu_gets_the_card_of_today(): void
    {
        $classic = $this->old('Flammkuchen Klassiker', null, 650);
        $this->old('Flammkuchen Vegetarisch', null, 650);
        $apple = $this->old('Flammkuchen Apfel-Zimt', ItemVariant::Whole, 600);
        $appleSlice = $this->old('Flammkuchen Apfel-Zimt', ItemVariant::Slice, 350);
        $cola = MenuItem::factory()->create(['name' => 'Cola', 'category' => MenuCategory::Drink, 'edition' => null, 'price_cents' => 300, 'deposit_cents' => 25]);

        $this->migrate();

        $this->assertSame('Nonno Classico', $classic->fresh()->name);
        $this->assertSame('Primo Amore', $apple->fresh()->name);
        $this->assertNull($apple->fresh()->variant);
        $this->assertFalse($appleSlice->fresh()->is_available);
        $this->assertSame(225, $cola->fresh()->price_cents);
        $this->assertSame(25, $cola->fresh()->deposit_cents);

        $card = MenuItem::query()->where('edition', 'flammkuchen')->where('is_available', true)->orderBy('sort')->get()
            ->map(fn (MenuItem $i) => [$i->name, $i->variant?->value, $i->price_cents])->all();
        $this->assertSame([
            ['Nonno Classico', null, 650],
            ['Nonno Classico', 'gratinated', 750],
            ['Viva Vegetaria', null, 650],
            ['Viva Vegetaria', 'gratinated', 750],
            ['Primo Amore', null, 600],
            ['Bella Banana', null, 600],
        ], $card);

        // the seeder runs after the migration on every deploy and must not add anything
        $count = MenuItem::query()->where('edition', 'flammkuchen')->count();
        $this->seed(MenuSeeder::class);
        $this->assertSame($count, MenuItem::query()->where('edition', 'flammkuchen')->count());
    }

    public function test_items_renamed_in_the_admin_before_are_not_duplicated(): void
    {
        $old = $this->old('Flammkuchen Klassiker', null, 650);
        $this->old('Nonno Classico', null, 700);

        $this->migrate();

        $this->assertFalse($old->fresh()->is_available);
        $this->assertSame(1, MenuItem::query()->where('name', 'Nonno Classico')->whereNull('variant')->count());
        $this->assertSame(650, MenuItem::query()->where('name', 'Nonno Classico')->whereNull('variant')->sole()->price_cents);
    }

    public function test_fresh_install_and_repeated_run_change_nothing(): void
    {
        $this->seed(MenuSeeder::class);
        $before = MenuItem::query()->orderBy('id')->get(['id', 'name', 'variant', 'price_cents', 'is_available'])->toArray();

        $this->migrate();
        $this->migrate();

        $this->assertSame($before, MenuItem::query()->orderBy('id')->get(['id', 'name', 'variant', 'price_cents', 'is_available'])->toArray());
    }
}
