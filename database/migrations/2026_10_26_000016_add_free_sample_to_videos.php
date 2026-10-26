<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Videos anyone may watch in full on the public pages, to show what Langganan Plus offers. */
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->boolean('is_free_sample')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn('is_free_sample');
        });
    }
};
