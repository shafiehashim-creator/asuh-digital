<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * User decision 2026-10-27: the yearly subscription now includes the
     * animated videos and costs RM149. Only the old default RM99 changes, so
     * a price the admin set by hand is kept.
     */
    public function up(): void
    {
        DB::table('plans')->where('code', 'pas_tahunan')->where('price_sen', 9900)
            ->update(['price_sen' => 14900, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('plans')->where('code', 'pas_tahunan')->where('price_sen', 14900)
            ->update(['price_sen' => 9900, 'updated_at' => now()]);
    }
};
