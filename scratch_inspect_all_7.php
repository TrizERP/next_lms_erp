<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "Standard columns: " . implode(', ', Schema::getColumnListing('standard')) . "\n";
echo "semantic_intelligence columns: " . implode(', ', Schema::getColumnListing('semantic_intelligence')) . "\n";

$chapterIds = [8622, 8608, 25871, 25879, 25884, 25892, 25900];

foreach ($chapterIds as $cid) {
    $ch = DB::table('chapter_master')->where('id', $cid)->first();
    $sub = DB::table('subject')->where('id', $ch->subject_id)->value('subject_name');
    $topics = DB::table('topic_master')->where('chapter_id', $cid)->count();
    $concepts = DB::table('lms_concept')->where('chapter_id', $cid)->count();
    $si = DB::table('semantic_intelligence')->where('chapter_id', $cid)->first();
    $lo = DB::table('lms_learning_outcomes')->where('chapter_id', $cid)->count();

    echo "Ch $cid: {$ch->chapter_name} (Std {$ch->standard_id}, Sub: $sub)\n";
    echo "  Topics: $topics | Concepts: $concepts | LO: $lo | SI ID: " . ($si->id ?? 'NONE') . "\n";
    if ($si) {
        $cols = (array)$si;
        foreach (['data', 'intelligence_data', 'json_data', 'content', 'payload'] as $f) {
            if (isset($cols[$f])) {
                $dec = json_decode($cols[$f], true);
                if (!empty($dec)) {
                    echo "  Found data in column '$f'. Keys: " . implode(', ', array_keys($dec)) . "\n";
                    echo "    LO: " . count($dec['learning_outcomes'] ?? []) . " | Knowledge: " . count($dec['knowledge'] ?? []) . " | Skills: " . count($dec['skill'] ?? []) . " | Abilities: " . count($dec['ability'] ?? []) . " | Rubrics: " . count($dec['assessment_rubrics'] ?? []) . "\n";
                    break;
                }
            }
        }
    }
}
