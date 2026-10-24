<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('author');
            $table->text('synopsis');
            $table->string('age_group');
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('single_price_sen')->nullable();
            $table->boolean('is_enhanced_edition')->default(false);
            $table->string('cover_path')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('book_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->string('edition');
            // Private disk (storage/app/private), never under public/.
            $table->string('path');
            $table->unsignedSmallInteger('page_count')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamps();

            $table->unique(['book_id', 'edition']);
        });

        Schema::create('syariah_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending')->index();
            $table->text('notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        // Single e-books bought outside a pass (purchase flow comes later).
        Schema::create('entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'book_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('book_id')->references('id')->on('books')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['book_id']);
        });

        Schema::dropIfExists('entitlements');
        Schema::dropIfExists('syariah_reviews');
        Schema::dropIfExists('book_files');
        Schema::dropIfExists('books');
    }
};
