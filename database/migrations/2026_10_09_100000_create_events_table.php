<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('location')->nullable();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_active')->default(false);
            // list of MenuEdition values
            $table->json('editions');
            $table->boolean('accepting_orders')->default(true);
            $table->timestamp('paused_until')->nullable();
            $table->string('pause_message')->nullable();
            $table->boolean('cash_allowed')->default(true);
            // wait time start values
            $table->unsignedSmallInteger('oven_slots')->default(2);
            $table->unsignedSmallInteger('minutes_per_bake')->default(4);
            $table->unsignedSmallInteger('buffer_minutes')->default(3);
            // secret link for the pickup display
            $table->string('display_token', 64)->unique();
            // pickup number counter, row locked on allocation
            $table->unsignedInteger('last_pickup_number')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
