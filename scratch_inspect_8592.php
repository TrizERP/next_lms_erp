<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$rows = DB::table('content_master')->where('chapter_id', 8592)->get();
foreach ($rows as $r) {
    echo "ID: {$r->id} | Cat: {$r->content_category} | Title: {$r->title} | File: {$r->filename} | URL: {$r->url}\n";
}

