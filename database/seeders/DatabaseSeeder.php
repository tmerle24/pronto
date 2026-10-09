<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Safe to run on every deploy: only creates what is missing.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([MenuSeeder::class, EventSeeder::class]);

        if (app()->environment('local', 'testing')) {
            $this->call(DemoSeeder::class);
        }
    }
}
