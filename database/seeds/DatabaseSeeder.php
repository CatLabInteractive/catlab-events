<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Test data for a local copy (see docs/local-development.md):
     *
     *   php artisan migrate:fresh --seed
     *
     * @return void
     */
    public function run()
    {
        $this->call(PreviewSeeder::class);
    }
}
