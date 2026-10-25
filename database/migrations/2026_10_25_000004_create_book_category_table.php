<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A book may sit in several categories (e.g. "Kisah nabi" and "Komik").
 * Each book's existing category moves into the pivot before the old column goes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_category', function (Blueprint $table) {
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->primary(['book_id', 'category_id']);
        });

        DB::table('book_category')->insertUsing(
            ['book_id', 'category_id'],
            DB::table('books')->select('id', 'category_id')->whereNotNull('category_id'),
        );

        Schema::table('books', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('age_group')->constrained()->restrictOnDelete();
        });

        foreach (DB::table('book_category')->orderBy('category_id')->get() as $row) {
            DB::table('books')->where('id', $row->book_id)->whereNull('category_id')->update(['category_id' => $row->category_id]);
        }

        Schema::dropIfExists('book_category');
    }
};
