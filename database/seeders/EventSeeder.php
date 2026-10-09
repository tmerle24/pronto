<?php

namespace Database\Seeders;

use App\Enums\MenuEdition;
use App\Models\Event;
use Illuminate\Database\Seeder;

class EventSeeder extends Seeder
{
    public function run(): void
    {
        Event::query()->firstOrCreate(['slug' => 'weihnachtsmarkt-stierstadt-2026'], [
            'name' => 'Weihnachtsmarkt Stierstadt',
            'location' => 'Oberursel-Stierstadt',
            'starts_on' => '2026-12-05',
            'ends_on' => '2026-12-06',
            'is_active' => true,
            'editions' => [MenuEdition::Pizza],
            'accepting_orders' => true,
            'cash_allowed' => true,
            'oven_slots' => 2,
            'minutes_per_bake' => 4,
            'buffer_minutes' => 3,
        ]);
    }
}
