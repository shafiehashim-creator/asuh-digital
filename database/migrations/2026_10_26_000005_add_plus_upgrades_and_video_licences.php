<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2: Langganan Plus (e-books and animated videos).
 * - orders.upgrades_pass_id: an upgrade order turns an existing yearly
 *   subscription into Plus for its remaining days instead of adding a new one.
 * - licenses.includes_videos: institution licences may include the videos.
 * - The Plus plan is switched on with no price; it goes on sale once the
 *   admin sets a price and opens the videos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('upgrades_pass_id')->nullable()->constrained('passes')->nullOnDelete();
        });

        Schema::table('licenses', function (Blueprint $table) {
            $table->boolean('includes_videos')->default(false);
        });

        DB::table('plans')->where('code', 'pas_plus')->update(['is_active' => true]);
    }

    public function down(): void
    {
        DB::table('plans')->where('code', 'pas_plus')->update(['is_active' => false]);

        Schema::table('licenses', function (Blueprint $table) {
            $table->dropColumn('includes_videos');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('upgrades_pass_id');
        });
    }
};
