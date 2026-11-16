<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Books that belong together (a story told over several books), shown in reading order. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_series', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->text('description_en')->nullable();
            $table->timestamps();
        });

        Schema::table('books', function (Blueprint $table) {
            $table->foreignId('book_series_id')->nullable()->constrained('book_series')->nullOnDelete();
            $table->unsignedSmallInteger('series_position')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropConstrainedForeignId('book_series_id');
            $table->dropColumn('series_position');
        });

        Schema::dropIfExists('book_series');
    }
};
