<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase D classes. Pupils are rows in `children` with a classroom instead of a
 * parent, so Mod Anak, reading records, quizzes and badges work the same for
 * them. PDPA as for children: a nickname, an age group and an avatar only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classrooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 60);
            $table->string('age_group', 10);
            $table->string('code', 6)->unique();
            $table->timestamps();
        });

        Schema::table('children', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->foreignId('classroom_id')->nullable()->after('user_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('children', function (Blueprint $table) {
            $table->dropConstrainedForeignId('classroom_id');
        });
        Schema::dropIfExists('classrooms');
    }
};
