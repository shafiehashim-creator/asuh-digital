<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kuiz Interaktif (user decision 2026-11-26, like Wayground): quizzes made
 * by ASUH admins, played by children (Mod Anak), parents, and pupils whose
 * teacher assigns one to their class.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_games', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('age_group')->index();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('seconds_per_question')->default(20);
            $table->timestamp('published_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('quiz_game_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_game_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->string('type');
            $table->text('prompt');
            $table->string('image_path')->nullable();
            // Choices [{t, ok}] or the items in their right order [{t}].
            $table->json('options')->nullable();
            // True/false: a boolean. Fill in: the accepted answers.
            $table->json('answer')->nullable();
            $table->unsignedSmallInteger('seconds')->nullable();
            $table->text('explanation')->nullable();
            $table->timestamps();
        });

        Schema::create('quiz_game_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('due_at')->nullable();
            $table->timestamps();

            $table->unique(['quiz_game_id', 'classroom_id']);
        });

        Schema::create('quiz_game_plays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_game_id')->constrained()->cascadeOnDelete();
            // A child (family or pupil) plays; a parent, teacher or staff can also try one.
            $table->foreignId('child_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('assignment_id')->nullable()->constrained('quiz_game_assignments')->nullOnDelete();
            $table->unsignedInteger('score')->default(0);
            $table->unsignedSmallInteger('correct')->default(0);
            $table->unsignedSmallInteger('total')->default(0);
            // {question id: {ok, pts, ms}}
            $table->json('answers')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['quiz_game_id', 'child_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_game_plays');
        Schema::dropIfExists('quiz_game_assignments');
        Schema::dropIfExists('quiz_game_questions');
        Schema::dropIfExists('quiz_games');
    }
};
