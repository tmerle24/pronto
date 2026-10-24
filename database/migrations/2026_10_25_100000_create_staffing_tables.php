<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->time('opens_at');
            $table->time('closes_at');
            // team arrives / leaves, null = same as opening hours
            $table->time('setup_from')->nullable();
            $table->time('teardown_until')->nullable();
            // payouts fixed by the admin, before that they are preliminary
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'date']);
        });

        Schema::create('event_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            // null = shared evenly by all market days of the event (stand fee, registration)
            $table->foreignId('market_day_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('label', 120);
            $table->unsignedInteger('amount_cents');
            $table->timestamps();
        });

        Schema::create('availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_day_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->time('available_from');
            $table->time('available_until');
            $table->string('note', 200)->nullable();
            $table->timestamps();

            $table->unique(['market_day_id', 'user_id']);
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_day_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // planned, corrected by the admin to the real times after the day
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('task', 120)->nullable();
            $table->timestamps();
        });

        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            // only the hash is stored, the token itself is in the link
            $table->string('token_hash', 64)->unique();
            $table->string('name', 120);
            $table->string('role', 20);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('availabilities');
        Schema::dropIfExists('event_costs');
        Schema::dropIfExists('market_days');
    }
};
