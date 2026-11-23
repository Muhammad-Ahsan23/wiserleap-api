<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Starting categories and authors (same as the website).
     * Admin accounts are not seeded: use the sign-up page for the first
     * account, or `php artisan admin:create`.
     */
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            AuthorSeeder::class,
        ]);
    }
}
