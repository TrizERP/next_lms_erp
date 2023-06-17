<?php
require __DIR__.'/../../vendor/autoload.php';
$app = require_once __DIR__.'/../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

set_time_limit(0);
$host = env('DB_HOST');
$username = env('DB_USERNAME');
$password = env('DB_PASSWORD');
$database = env('DB_DATABASE');
$syear = env('SYEAR');

$cn = mysqli_connect($host, $username, $password) or die("Check DB Connection");

mysqli_select_db($cn, $database) or die("Connection OK, but Database not found");
?>