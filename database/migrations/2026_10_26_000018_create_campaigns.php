<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Promotion campaigns with a countdown (user decision 2026-10-26): a
     * pre-order, a launch or any offer with an end date, shown as a bar on
     * every public page and/or a banner on the sales pages until it ends.
     * A campaign can also set the price in its window: the Langganan Tahunan
     * early bird, or a promotional Langganan Plus price. Before it starts it
     * can show as "coming soon" while buyers still pay the normal price.
     */
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('title_en')->nullable();
            $table->string('message', 500)->nullable();
            $table->string('message_en', 500)->nullable();
            $table->string('cta_label', 60)->nullable();
            $table->string('cta_label_en', 60)->nullable();
            $table->string('cta_url')->nullable();
            $table->string('promo_code', 30)->nullable();
            $table->string('offer', 20)->default('none'); // none | early_bird | plus
            $table->unsignedInteger('promo_price_sen')->nullable(); // plus only
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at');
            $table->boolean('show_bar')->default(true);
            $table->boolean('show_banner')->default(true);
            $table->boolean('show_coming_soon')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
