<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 watching in Mod Anak: seconds watched per child, video and day (for
 * the daily limit and the weekly report), and the parent's two controls on
 * each child: a daily video limit and "Baca dulu, tonton kemudian".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained('children')->cascadeOnDelete();
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->date('watched_on');
            $table->unsignedInteger('seconds')->default(0);
            $table->unsignedInteger('position')->default(0);
            $table->boolean('completed')->default(false);
            $table->timestamp('last_tick_at')->nullable();
            $table->timestamps();
            $table->unique(['child_id', 'video_id', 'watched_on']);
            $table->index(['child_id', 'watched_on']);
        });

        Schema::table('children', function (Blueprint $table) {
            // Null: no limit.
            $table->unsignedSmallInteger('daily_video_minutes')->nullable();
            $table->boolean('read_before_watch')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('children', function (Blueprint $table) {
            $table->dropColumn(['daily_video_minutes', 'read_before_watch']);
        });
        Schema::dropIfExists('video_views');
    }
};
