<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database: php artisan db:seed --force. Safe to run on a live site.
     */
    public function run(): void
    {
        $this->call(StoreSeeder::class);
    }
}
