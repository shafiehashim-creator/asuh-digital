<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The landing texts migration first shipped without clearing the cached
     * settings, so the server kept showing the old headline. Run it again
     * (it only changes texts still at their old defaults) and clear the cache.
     */
    public function up(): void
    {
        (require __DIR__.'/2026_10_27_000003_landing_texts_mention_videos.php')->up();
    }

    public function down(): void {}
};
