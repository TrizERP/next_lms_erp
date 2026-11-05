<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$chapterIds = [8622, 8608, 25871, 25879, 25884, 25892, 25900];

echo "=== USER SPECIFIED CHAPTERS ===\n";
foreach ($chapterIds as $id) {
    $ch = DB::table('chapter_master')->where('id', $id)->first();
    $sub = DB::table('subject')->where('id', $ch->subject_id)->value('subject_name');
    echo "ID: $id | Std ID: {$ch->standard_id} | Sub: $sub ({$ch->subject_id}) | Name: {$ch->chapter_name}\n";
    
    // Check existing teacher resources for this chapter
    $res = DB::table('content_master')
        ->where('chapter_id', $id)
        ->where(function($q) {
            $q->where('content_category', 'LIKE', '%Teacher%')
              ->orWhere('title', 'LIKE', '%Teacher%')
              ->orWhere('title', 'LIKE', '%Rubric%')
              ->orWhere('title', 'LIKE', '%KSA%')
              ->orWhere('title', 'LIKE', '%Mindmap%')
              ->orWhere('title', 'LIKE', '%Learning Outcome%');
        })
        ->get(['id', 'title', 'content_category', 'filename']);
    foreach ($res as $r) {
        echo "   -> ID: {$r->id} | {$r->content_category} | {$r->title} | {$r->filename}\n";
    }
}

echo "\n=== FIND CHAPTERS WITH TEACHER TRAINING PRESENTATION IN DATABASE ===\n";
$ttp = DB::table('content_master')
    ->where('content_category', 'Teacher training presentation')
    ->orderByDesc('id')
    ->limit(15)
    ->get(['id', 'chapter_id', 'title', 'content_category', 'filename', 'created_at']);

foreach ($ttp as $t) {
    $ch = DB::table('chapter_master')->where('id', $t->chapter_id)->first();
    $sub = $ch ? DB::table('subject')->where('id', $ch->subject_id)->value('subject_name') : 'Unknown';
    echo "ID: {$t->id} | Ch: {$t->chapter_id} ({$ch?->chapter_name}, Sub: {$sub}, Std: {$ch?->standard_id}) | Title: {$t->title} | File: {$t->filename}\n";
}
