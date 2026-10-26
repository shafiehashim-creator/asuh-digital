<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bilingual site (user decision 2026-10-26): the language a user last
     * chose (for their e-mails), and optional English book details.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 5)->nullable()->after('phone');
        });

        Schema::table('books', function (Blueprint $table) {
            $table->string('title_en')->nullable()->after('title');
            $table->text('synopsis_en')->nullable()->after('synopsis');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('locale');
        });

        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn(['title_en', 'synopsis_en']);
        });
    }
};
