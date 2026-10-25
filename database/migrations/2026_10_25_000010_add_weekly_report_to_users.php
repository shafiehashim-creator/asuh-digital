<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Weekly reading report e-mail (Phase C): on by default, switched off from
 * "Akaun saya" or the link in the e-mail. sent_at stops a second copy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('weekly_report')->default(true);
            $table->timestamp('weekly_report_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['weekly_report', 'weekly_report_sent_at']);
        });
    }
};
