<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Games in Mod Anak (user decision 2026-10-27): time played per child,
     * game and day (for the parent's daily limit) and the best score.
     */
    public function up(): void
    {
        Schema::create('game_plays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained('children')->cascadeOnDelete();
            $table->string('game', 40);
            $table->date('played_on');
            $table->unsignedInteger('seconds')->default(0);
            $table->unsignedSmallInteger('rounds')->default(0);
            $table->unsignedSmallInteger('best_score')->default(0);
            $table->timestamp('last_tick_at')->nullable();
            $table->timestamps();
            $table->unique(['child_id', 'game', 'played_on']);
            $table->index(['child_id', 'played_on']);
        });

        Schema::table('children', function (Blueprint $table) {
            // Null: no limit.
            $table->unsignedSmallInteger('daily_game_minutes')->nullable()->default(20);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_plays');
        Schema::table('children', function (Blueprint $table) {
            $table->dropColumn('daily_game_minutes');
        });
    }
};
