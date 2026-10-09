<?php

namespace Database\Factories;

use App\Enums\MenuEdition;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Markt '.fake()->unique()->city();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'location' => fake()->city(),
            'starts_on' => today(),
            'ends_on' => today(),
            'is_active' => true,
            'editions' => [MenuEdition::Pizza],
            'accepting_orders' => true,
            'cash_allowed' => true,
            'oven_slots' => 2,
            'minutes_per_bake' => 4,
            'buffer_minutes' => 3,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
