<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('name_en')->nullable();
            $table->string('description_en')->nullable();
            $table->string('category');
            // null = shown in every edition (drinks)
            $table->string('edition')->nullable();
            $table->string('variant')->nullable();
            $table->unsignedInteger('price_cents');
            $table->unsignedInteger('deposit_cents')->default(0);
            // basis points: 700 = 7 %
            $table->unsignedSmallInteger('vat_rate_bp')->default(700);
            $table->string('allergens')->nullable();
            $table->string('additives')->nullable();
            $table->decimal('oven_units', 5, 2)->default(0);
            $table->boolean('is_sold_out')->default(false);
            $table->boolean('is_available')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['edition', 'category', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
