<?php

namespace App\Console\Commands;

use App\Models\ONetDataCategory;
use Illuminate\Console\Command;

class CreateONetDataCategory extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:o-net-category';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        ONetDataCategory::insert([
            ['category' => 'abilities'],
            ['category' => 'interests'],
            ['category' => 'knowledge'],
            ['category' => 'skills(Basic)'],
            ['category' => 'skills(Cross-Functional)'],
            ['category' => 'work-activities'],
            ['category' => 'work-context'],
            ['category' => 'work-styles'],
            ['category' => 'work-values'],
        ]);
    }
}
