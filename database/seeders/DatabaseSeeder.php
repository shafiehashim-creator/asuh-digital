<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Admin accounts are never seeded; create them with `php artisan asuh:create-admin`.
     */
    public function run(): void
    {
        $this->call(PlanSeeder::class);
    }
}
