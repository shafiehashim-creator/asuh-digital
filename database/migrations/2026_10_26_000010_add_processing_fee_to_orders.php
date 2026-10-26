<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CHIP's processing fee passed on to the buyer (user decision 2026-10-26):
     * kept apart from amount_sen, which stays the price of what was bought.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('processing_fee_sen')->default(0)->after('discount_sen');
            $table->string('chip_method', 20)->nullable()->after('processing_fee_sen');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['processing_fee_sen', 'chip_method']);
        });
    }
};
