<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** A book's own royalty on single sales, when its contract differs (null: the default in settings). */
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->decimal('royalty_single_percent', 5, 2)->nullable()->after('single_price_sen');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn('royalty_single_percent');
        });
    }
};
