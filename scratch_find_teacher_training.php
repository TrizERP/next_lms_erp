<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== Columns in content_master ===\n";
var_dump(Schema::getColumnListing('content_master'));

echo "\n=== Contents for Chapter 8594 ===\n";
$rows = DB::table('content_master')->where('chapter_id', 8594)->get();
foreach ($rows as $r) {
    echo "ID: {$r->id} | Category: {$r->content_category} | ResType: " . ($r->resource_type ?? 'N/A') . " | Title: {$r->title} | File: {$r->filename}\n";
}

echo "\n=== Search for Teacher Training or KSA or Rubrics or Mindmap across all content_master ===\n";
$matches = DB::table('content_master')
    ->where(function($q) {
        $q->where('title', 'LIKE', '%Teacher Training%')
          ->orWhere('title', 'LIKE', '%Learning Outcome%')
          ->orWhere('title', 'LIKE', '%KSA%')
          ->orWhere('title', 'LIKE', '%Rubric%')
          ->orWhere('title', 'LIKE', '%Mindmap%')
          ->orWhere('title', 'LIKE', '%Mind Map%')
          ->orWhere('content_category', 'LIKE', '%Teacher%')
          ->orWhere('content_category', 'LIKE', '%Rubric%')
          ->orWhere('content_category', 'LIKE', '%Mind%');
    })
    ->get(['id', 'chapter_id', 'title', 'content_category', 'filename']);

foreach ($matches as $m) {
    echo "ID: {$m->id} | Ch: {$m->chapter_id} | Cat: {$m->content_category} | Title: {$m->title} | File: {$m->filename}\n";
}

