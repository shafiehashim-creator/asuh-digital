<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Monthly subscriptions get one renewal reminder before they end. */
    public function up(): void
    {
        Schema::table('passes', function (Blueprint $table) {
            $table->timestamp('renewal_reminded_at')->nullable()->after('ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('passes', function (Blueprint $table) {
            $table->dropColumn('renewal_reminded_at');
        });
    }
};
