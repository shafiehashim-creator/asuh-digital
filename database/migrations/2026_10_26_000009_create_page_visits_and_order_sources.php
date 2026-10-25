<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin reports (2026-10-26): a light record of public page views for the
 * traffic report, with no IP address and no third-party cookie (PDPA): a
 * random visitor id kept in the session, the page, the device type and where
 * the visit came from (UTM tags or the referring site). Orders keep the
 * first source of the buyer's visit, for the sales channel report.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_visits', function (Blueprint $table) {
            $table->id();
            $table->timestamp('visited_at')->index();
            $table->char('visitor', 32)->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('route', 80)->nullable();
            $table->string('path', 191);
            $table->string('source', 100);
            $table->string('medium', 50)->nullable();
            $table->string('campaign', 100)->nullable();
            $table->string('device', 10);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('source', 100)->nullable();
            $table->string('medium', 50)->nullable();
            $table->string('campaign', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['source', 'medium', 'campaign']);
        });
        Schema::dropIfExists('page_visits');
    }
};
