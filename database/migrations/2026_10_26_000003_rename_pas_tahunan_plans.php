<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Pas Tahunan" is now "Langganan Tahunan" (user decision 2026-10-25: "Pas"
 * sounded like a political party). Plan names show on orders and receipts.
 */
return new class extends Migration
{
    private const NAMES = [
        'pas_tahunan' => ['Pas Tahunan', 'Langganan Tahunan'],
        'pas_tahunan_early' => ['Pas Tahunan (Early Bird)', 'Langganan Tahunan (Early Bird)'],
        'pas_plus' => ['Pas Tahunan Plus', 'Langganan Plus'],
    ];

    public function up(): void
    {
        foreach (self::NAMES as $code => [$old, $new]) {
            DB::table('plans')->where('code', $code)->where('name', $old)->update(['name' => $new]);
        }
    }

    public function down(): void
    {
        foreach (self::NAMES as $code => [$old, $new]) {
            DB::table('plans')->where('code', $code)->where('name', $new)->update(['name' => $old]);
        }
    }
};
