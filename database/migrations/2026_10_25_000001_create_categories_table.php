<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // The seven categories from CLAUDE.md, so every environment has them.
        $now = now();
        $names = ['Komik', 'Kisah nabi', 'Akhlak', 'Doa harian', 'Cerita bergambar', 'Novel kanak-kanak', 'Sirah'];

        DB::table('categories')->insert(array_map(fn (string $name, int $i) => [
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'sort_order' => $i + 1,
            'created_at' => $now,
            'updated_at' => $now,
        ], $names, array_keys($names)));
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
