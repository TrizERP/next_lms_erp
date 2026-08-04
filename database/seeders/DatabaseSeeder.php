<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // \App\Models\User::factory(10)->create();

        // Onboarding journey template. Note that App\Console\Kernel::bootstrap()
        // blocks `db:seed` outright, so on an existing installation this is run
        // via `php artisan onboarding:install` instead.
        $this->call(OnboardingJourneySeeder::class);
    }
}
