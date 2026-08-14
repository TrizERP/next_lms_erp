<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "admission_enquiry columns:\n";
print_r(array_map(fn($c) => $c->Field, DB::select('DESC admission_enquiry')));

echo "\nadmission_form columns + row count:\n";
print_r(array_map(fn($c) => $c->Field, DB::select('DESC admission_form')));
echo "admission_form rows: " . DB::table('admission_form')->count() . "\n";

echo "\nfollow_up.status distinct values (fu.status used in enquiry_status alias elsewhere):\n";
print_r(DB::table('follow_up')->select('status')->distinct()->get());
