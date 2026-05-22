<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

try {
    // Test connection
    $result = DB::select('SELECT 1 as test');
    echo "Database connection successful. Result: ";
    var_dump($result);
    
    // Check if jobs table exists
    $hasTable = Schema::hasTable('jobs');
    echo "Jobs table exists: " . ($hasTable ? 'yes' : 'no') . "\n";
    
    if ($hasTable) {
        // Count jobs
        $count = DB::table('jobs')->count();
        echo "Number of jobs in table: " . $count . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString();
}
?>