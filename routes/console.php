<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('orders:expire-unpaid')->everyMinute()->withoutOverlapping();
Schedule::command('notifications:purge')->hourly();
