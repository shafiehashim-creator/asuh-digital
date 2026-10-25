<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * School licences vs family subscriptions (user decision 2026-10-26):
 * - licences may be limited to school days and hours (null: any time), so
 *   reading at home stays a family subscription;
 * - each institution has a code giving its pupils' parents a discount on
 *   Langganan Tahunan, and orders remember which school sent them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->json('access_days')->nullable();
            $table->time('access_starts')->nullable();
            $table->time('access_ends')->nullable();
        });

        Schema::table('institutions', function (Blueprint $table) {
            $table->string('parent_code', 20)->nullable()->unique();
            $table->unsignedTinyInteger('parent_discount_percent')->default(20);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('referral_institution_id')->nullable()->constrained('institutions')->nullOnDelete();
            $table->unsignedInteger('discount_sen')->default(0);
        });

        foreach (DB::table('institutions')->whereNull('parent_code')->pluck('id') as $id) {
            DB::table('institutions')->where('id', $id)->update(['parent_code' => 'ASUH-'.Str::upper(Str::random(5))]);
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referral_institution_id');
            $table->dropColumn('discount_sen');
        });
        Schema::table('institutions', function (Blueprint $table) {
            $table->dropUnique(['parent_code']);
            $table->dropColumn(['parent_code', 'parent_discount_percent']);
        });
        Schema::table('licenses', function (Blueprint $table) {
            $table->dropColumn(['access_days', 'access_starts', 'access_ends']);
        });
    }
};
