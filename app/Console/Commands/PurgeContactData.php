<?php

namespace App\Console\Commands;

use App\Models\NotificationSubscription;
use Illuminate\Console\Command;

class PurgeContactData extends Command
{
    protected $signature = 'notifications:purge';

    protected $description = 'Delete guest contact data (push, WhatsApp, phone) after 24 hours';

    public function handle(): int
    {
        $count = NotificationSubscription::query()
            ->whereNull('purged_at')
            ->where('created_at', '<', now()->subHours((int) config('pronto.notifications.retention_hours')))
            ->update(['target' => null, 'purged_at' => now()]);

        $this->info("Purged: {$count}");

        return self::SUCCESS;
    }
}
