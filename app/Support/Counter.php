<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/** Gapless counters (receipt numbers). Call inside a transaction. */
final class Counter
{
    public static function next(string $name): int
    {
        DB::table('counters')->insertOrIgnore(['name' => $name, 'value' => 0]);
        $value = (int) DB::table('counters')->where('name', $name)->lockForUpdate()->value('value') + 1;
        DB::table('counters')->where('name', $name)->update(['value' => $value]);

        return $value;
    }
}
