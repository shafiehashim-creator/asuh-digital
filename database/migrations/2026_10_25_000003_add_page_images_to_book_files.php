<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Versi pantas": light page images made from the PDF in the admin's browser,
 * so phones do not have to download and decode print-resolution pages.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('book_files', function (Blueprint $table) {
            $table->unsignedSmallInteger('page_images')->nullable()->after('size_bytes');
            $table->string('page_format', 8)->nullable()->after('page_images');
            $table->unsignedSmallInteger('page_width')->nullable()->after('page_format');
            $table->unsignedSmallInteger('page_height')->nullable()->after('page_width');
            $table->timestamp('pages_ready_at')->nullable()->after('page_height');
        });
    }

    public function down(): void
    {
        Schema::table('book_files', function (Blueprint $table) {
            $table->dropColumn(['page_images', 'page_format', 'page_width', 'page_height', 'pages_ready_at']);
        });
    }
};
