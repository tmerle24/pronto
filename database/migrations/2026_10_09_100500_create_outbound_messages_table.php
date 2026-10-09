<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // every message to a paid provider, for daily limits and cost tracking (no contact data)
        Schema::create('outbound_messages', function (Blueprint $table) {
            $table->id();
            $table->string('channel');
            $table->string('driver');
            $table->string('kind');
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider_message_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['channel', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_messages');
    }
};
