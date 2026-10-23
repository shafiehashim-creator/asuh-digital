<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->restrictOnDelete();
            // The books table arrives in Phase B; its foreign key is added then.
            $table->unsignedBigInteger('book_id')->nullable()->index();
            $table->unsignedInteger('amount_sen');
            $table->char('currency', 3)->default('MYR');
            $table->string('payment_method');
            $table->string('status')->default('pending')->index();
            $table->string('chip_purchase_id')->nullable()->unique();
            $table->string('bank_receipt_path')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
