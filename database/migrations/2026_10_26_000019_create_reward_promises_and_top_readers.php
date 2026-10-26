<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rewards for reading (user decisions 2026-10-26): parents promise their
     * child a reward for earning a badge, and ASUH rewards the 5 best readers
     * of each age group every month.
     */
    public function up(): void
    {
        Schema::create('reward_promises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained('children')->cascadeOnDelete();
            $table->string('badge', 40);
            $table->string('reward', 120);
            $table->timestamp('achieved_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();
            $table->unique(['child_id', 'badge']);
        });

        Schema::create('monthly_top_readers', function (Blueprint $table) {
            $table->id();
            $table->date('month'); // first day of the month
            $table->string('age_group', 10);
            $table->unsignedTinyInteger('rank');
            $table->foreignId('child_id')->constrained('children')->cascadeOnDelete();
            $table->unsignedInteger('points');
            $table->unsignedInteger('minutes');
            $table->unsignedSmallInteger('days');
            $table->unsignedSmallInteger('quizzes');
            $table->timestamp('reward_sent_at')->nullable();
            $table->string('reward_note')->nullable();
            $table->timestamps();
            $table->unique(['month', 'child_id']);
            $table->index(['month', 'age_group']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_top_readers');
        Schema::dropIfExists('reward_promises');
    }
};
