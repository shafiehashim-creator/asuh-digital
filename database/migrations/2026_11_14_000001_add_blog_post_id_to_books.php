<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The book blog post a book was pulled from, so the same post is never imported twice. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('blog_post_id', 32)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropUnique(['blog_post_id']);
            $table->dropColumn('blog_post_id');
        });
    }
};
