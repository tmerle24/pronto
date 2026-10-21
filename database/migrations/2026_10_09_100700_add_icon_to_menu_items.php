<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            // file name in public/illustrations, without .svg
            $table->string('icon', 40)->nullable();
        });

        // existing menus (production) get their pictures right away
        foreach ([
            'Margherita' => 'margherita',
            'Salami' => 'salami',
            'Flammkuchen Klassiker' => 'flammkuchen-klassiker',
            'Flammkuchen Vegetarisch' => 'flammkuchen-vegetarisch',
            'Cola' => 'cola',
            'Wasser' => 'wasser',
        ] as $name => $icon) {
            DB::table('menu_items')->where('name', $name)->whereNull('icon')->update(['icon' => $icon]);
        }
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn('icon');
        });
    }
};
