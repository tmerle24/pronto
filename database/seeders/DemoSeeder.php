<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Local demo logins (password: pronto-demo). Never runs in production.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->firstOrCreate(['email' => 'admin@pronto.test'], [
            'name' => 'Till',
            'password' => 'pronto-demo',
            'role' => UserRole::Admin,
        ]);

        User::query()->firstOrCreate(['email' => 'cucina@pronto.test'], [
            'name' => 'Timo',
            'password' => 'pronto-demo',
            'role' => UserRole::Staff,
        ]);
    }
}
