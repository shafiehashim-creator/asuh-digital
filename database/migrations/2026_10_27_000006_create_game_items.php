<?php

use Database\Seeders\GameItemSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Game content the admin can edit (user decision 2026-10-27), started
     * with ASUH's own set. Hijaiyah letters stay in code.
     */
    public function up(): void
    {
        Schema::create('game_items', function (Blueprint $table) {
            $table->id();
            $table->string('game', 40);
            $table->string('age_group', 10)->default('both');
            // Emoji, scenario, set title, word, clue or question, by game.
            $table->text('prompt');
            // The memory card's word, the prophet's name or the right answer.
            $table->string('answer')->nullable();
            $table->boolean('is_good')->nullable();
            $table->text('explanation')->nullable();
            // The set in its right order, or the wrong answers.
            $table->json('options')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['game', 'is_active']);
        });

        (new GameItemSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('game_items');
    }
};
