<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which families have opened which book: "Paling popular" on the landing page
 * and "X keluarga membaca buku ini" on the book page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_readers', function (Blueprint $table) {
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('first_read_at');
            $table->timestamp('last_read_at');
            $table->primary(['book_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_readers');
    }
};
