<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Child profiles under a parent's account (Phase C). PDPA: only a nickname,
 * an age group and a picked avatar; no photo, birth date or school.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('children', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('nickname', 30);
            $table->string('age_group', 10);
            $table->string('avatar', 20);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('children');
    }
};
