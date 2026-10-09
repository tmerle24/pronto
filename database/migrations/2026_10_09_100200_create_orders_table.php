<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            // assigned when the order enters the queue, so aborted payments leave no gaps
            $table->unsignedInteger('pickup_number')->nullable();
            $table->string('public_token', 64)->unique();
            // whatsapp matching, e.g. "K7M2"
            $table->string('short_code', 8);
            $table->string('status')->default('new');
            $table->string('payment_method');
            $table->string('payment_status')->default('pending');
            $table->string('source')->default('qr');
            $table->unsignedInteger('total_cents');
            $table->decimal('oven_units', 6, 2)->default(0);
            $table->string('locale', 5)->default('de');
            $table->string('stripe_payment_intent_id')->nullable()->unique();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('customer_notified_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'pickup_number']);
            $table->unique(['event_id', 'short_code']);
            $table->index(['event_id', 'status', 'queued_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_item_id')->nullable()->constrained()->nullOnDelete();
            // snapshot, the menu may change later
            $table->string('name');
            $table->string('variant')->nullable();
            $table->unsignedInteger('unit_price_cents');
            $table->unsignedInteger('unit_deposit_cents')->default(0);
            $table->unsignedSmallInteger('vat_rate_bp');
            $table->decimal('unit_oven_units', 5, 2)->default(0);
            $table->unsignedSmallInteger('quantity');
            $table->timestamps();
        });

        // append-only audit log of status and payment changes
        Schema::create('order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('type');
            $table->string('from')->nullable();
            $table->string('to')->nullable();
            $table->boolean('is_undo')->default(false);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // idempotency key from the queue tablet (offline replay)
            $table->uuid('client_request_id')->nullable()->unique();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['order_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_events');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
