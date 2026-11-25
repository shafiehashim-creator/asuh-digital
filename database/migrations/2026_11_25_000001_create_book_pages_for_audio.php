<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audiobook "Baca bersama": one row per book page with its text (from the
 * PDF, editable), where each word sits on the page, the page's recorded or
 * AI voice and when each word is spoken, for the word highlight.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('page');
            $table->text('text')->nullable();
            // [{t, x, y, w, h}] from the PDF, x/y/w/h as fractions of the page.
            $table->json('word_boxes')->nullable();
            // Private disk, like the PDFs.
            $table->string('audio_path')->nullable();
            $table->string('audio_source')->nullable();
            $table->string('audio_status')->default('none');
            $table->string('audio_error')->nullable();
            $table->decimal('audio_seconds', 7, 2)->nullable();
            // [{t, s, e}] spoken words with start and end in seconds.
            $table->json('timings')->nullable();
            $table->timestamps();

            $table->unique(['book_id', 'page']);
        });

        Schema::table('books', function (Blueprint $table) {
            $table->timestamp('audio_published_at')->nullable();
        });

        Schema::table('children', function (Blueprint $table) {
            $table->boolean('allow_audio')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('children', fn (Blueprint $table) => $table->dropColumn('allow_audio'));
        Schema::table('books', fn (Blueprint $table) => $table->dropColumn('audio_published_at'));
        Schema::dropIfExists('book_pages');
    }
};
