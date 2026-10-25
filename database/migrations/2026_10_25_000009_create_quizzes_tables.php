<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase C: a short multiple-choice quiz per book, the child's attempts, and
 * the badges earned. The badge set itself is fixed in code (App\Enums\Badge).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->string('question', 300);
            $table->json('options');
            $table->unsignedTinyInteger('correct_index');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained('children')->cascadeOnDelete();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('score');
            $table->unsignedTinyInteger('total');
            $table->boolean('passed');
            $table->timestamps();
            $table->index(['child_id', 'passed']);
        });

        Schema::create('child_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained('children')->cascadeOnDelete();
            $table->string('badge', 40);
            $table->timestamp('awarded_at');
            $table->unique(['child_id', 'badge']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('child_badges');
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('quiz_questions');
        Schema::dropIfExists('quizzes');
    }
};
