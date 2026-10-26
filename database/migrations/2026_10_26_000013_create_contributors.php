<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Royalty payees (writers, illustrators) and their share of each book's
     * royalty, for the monthly royalty report (2026-10-26).
     */
    public function up(): void
    {
        Schema::create('contributors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_name')->nullable();
            $table->string('bank_account_number', 40)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('book_contributor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contributor_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('penulis'); // penulis | ilustrator | lain
            $table->decimal('share_percent', 5, 2)->default(100);
            $table->timestamps();
            $table->unique(['book_id', 'contributor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_contributor');
        Schema::dropIfExists('contributors');
    }
};
