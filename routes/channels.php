<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// queue tablets, counter: every team member
Broadcast::channel('team.{eventId}', fn (User $user) => true);
