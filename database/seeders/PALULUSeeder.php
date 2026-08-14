<?php

namespace Database\Seeders;

use App\Services\PAL\ULU\ULUService;
use Illuminate\Database\Seeder;

class PALULUSeeder extends Seeder
{
    public function run(): void
    {
        app(ULUService::class)->seedDefaults();
    }
}
