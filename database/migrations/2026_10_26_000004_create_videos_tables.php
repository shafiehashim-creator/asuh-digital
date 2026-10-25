<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2: animated videos, with or without a book. Files live with an outside
 * provider (YouTube unlisted first, Bunny Stream later); only the provider
 * and its video id are kept here, so a video can move provider without any
 * other change. Videos go through the same syariah review as books.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('synopsis')->nullable();
            $table->string('age_group', 10);
            $table->foreignId('book_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 20);
            $table->string('provider_video_id', 100);
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('category_video', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->primary(['category_id', 'video_id']);
        });

        Schema::table('syariah_reviews', function (Blueprint $table) {
            $table->foreignId('book_id')->nullable()->change();
            $table->foreignId('video_id')->nullable()->after('book_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('syariah_reviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('video_id');
        });
        Schema::dropIfExists('category_video');
        Schema::dropIfExists('videos');
    }
};
