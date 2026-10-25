<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reading in Mod Anak: one row per child, book and day. Minutes grow from the
 * reader's heartbeat while the child is actually reading; last_page lets the
 * child carry on where they stopped, on any device.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reading_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained('children')->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->date('read_on');
            $table->unsignedSmallInteger('minutes')->default(0);
            $table->unsignedInteger('last_page')->default(1);
            $table->timestamp('last_tick_at')->nullable();
            $table->timestamps();
            $table->unique(['child_id', 'book_id', 'read_on']);
            $table->index(['child_id', 'read_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reading_sessions');
    }
};
