<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preorders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->unique()->constrained()->nullOnDelete();
            // Snapshot of contact details at the time of purchase.
            $table->string('name');
            $table->string('email');
            $table->string('phone', 30);
            $table->string('state');
            $table->unsignedTinyInteger('children_count');
            $table->string('children_age_group');
            $table->timestamp('pdpa_consented_at');
            $table->string('status')->default('reserved')->index();
            $table->timestamp('reserved_until')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preorders');
    }
};
