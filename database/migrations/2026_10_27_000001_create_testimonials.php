<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Parents' words for the sales page: a quote, a WhatsApp screenshot, or both.
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('author');
            $table->string('detail')->nullable();
            $table->text('quote')->nullable();
            $table->text('quote_en')->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }
};
