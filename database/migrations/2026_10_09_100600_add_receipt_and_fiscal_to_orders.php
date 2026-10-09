<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // e.g. 2026-000042, assigned when paid, gapless per year
            $table->string('receipt_number', 20)->nullable()->unique();
            // TSE result (signature, transaction number) when a recorder is active
            $table->json('fiscal_data')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamp('refunded_at')->nullable();
        });

        Schema::create('counters', function (Blueprint $table) {
            $table->string('name')->primary();
            $table->unsignedBigInteger('value')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('counters');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['receipt_number', 'fiscal_data', 'cancel_reason', 'refunded_at']);
        });
    }
};
