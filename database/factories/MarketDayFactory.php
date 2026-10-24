<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\MarketDay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MarketDay>
 */
class MarketDayFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'date' => today(),
            'opens_at' => '10:00',
            'closes_at' => '18:00',
            'setup_from' => '09:00',
            'teardown_until' => '19:00',
        ];
    }
}
