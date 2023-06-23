<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class testFunction extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:function';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command  description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $filePath = 'user/D5448828-3ADB-4FDC-8B3B-04ED20C6AB6F.jpeg';
//        Storage::disk('digitalocean')->delete('uploads/test.png');
//        dd('done');
        $disk = Storage::disk('digitalocean');
        if ($disk->exists($filePath)) {
            dd('Connected to DigitalOcean Spaces. File exists.');
        } else {
            dd('Unable to connect to DigitalOcean Spaces or file does not exist.');
        }
    }
}
