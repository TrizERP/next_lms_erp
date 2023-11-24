<?php

namespace App\Console\Commands;

use App\Models\ONetDataCategory;
use Illuminate\Console\Command;

class TestFunction extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:function {argumentName} {--optionName=default}';

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
        $argumentValue = $this->argument('argumentName');
        $optionValue = $this->option('optionName');

        // Your logic using the passed data...

        dd('Command executed with argument: ' . $argumentValue . ' and option: ' . $optionValue);
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
