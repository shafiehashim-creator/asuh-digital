<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Promotion codes (user decision 2026-10-26): a percentage or an amount
     * off, for chosen products, with dates and use limits. One code per order,
     * typed in the same "Kod promosi" box as school and affiliate codes.
     */
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('description')->nullable();
            $table->string('type', 10); // percent | fixed
            $table->unsignedInteger('value'); // percent (1-100) or sen
            $table->string('applies_to', 10)->default('all'); // all | pass | plus | book
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->boolean('once_per_customer')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('coupon_id')->nullable()->after('referral_institution_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
        });
        Schema::dropIfExists('coupons');
    }
};
