<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Repeatable: an existing address is left alone. Last line is machine
 * readable for install.sh: "EXISTS" or "CREATED <password>".
 */
class CreateTeamUser extends Command
{
    protected $signature = 'pronto:user {email} {--name=Team} {--admin}';

    protected $description = 'Create a team login (prints a generated password once)';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        if (User::query()->where('email', $email)->exists()) {
            $this->line('EXISTS');

            return self::SUCCESS;
        }

        $password = Str::password(16, symbols: false);
        User::query()->create([
            'name' => (string) $this->option('name'),
            'email' => $email,
            'password' => $password,
            'role' => $this->option('admin') ? UserRole::Admin : UserRole::Staff,
        ]);

        $this->line('CREATED '.$password);

        return self::SUCCESS;
    }
}
