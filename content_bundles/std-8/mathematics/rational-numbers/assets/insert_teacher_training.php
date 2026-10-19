<?php
// Run from the project root with:
//   php artisan tinker --execute="require 'C:/xampp/htdocs/next_lms_erp/content_bundles/std-8/mathematics/rational-numbers/assets/insert_teacher_training.php';"
//
// Inserts a NEW content_master row for chapter 25850 (Std 8 Maths, Rational
// Numbers), content_category = "Teacher training presentation" - no row of
// this type currently exists for this chapter (verified before writing
// this script), so this is a plain insert, not an update.
//
//  1. Inserts 4 diagrams (number systems, closure grid, associativity,
//     distributivity) into study_deck_images.
//  2. Inserts the content_master row with the validated 10-slide web HTML
//     as `description`, with its 4 image placeholders resolved to the new
//     real study-deck-image ids.
//  3. Links the 4 images to the new row.
//  4. Renders a separate, fuller 11-page PDF from teacher-training-pdf.html
//     (real-world applications, extra misconceptions, a 9-question
//     assessment bank, all 4 diagrams) and uploads it as the row's file.

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;

$dir = __DIR__ . '/';
$chapterId = 25850;
$gradeId = 11;
$standardId = 41;
$subjectId = 3976;
$tenant = 1;
$syear = 2026;
$userId = 1;

$existing = DB::table('content_master')
    ->where('chapter_id', $chapterId)
    ->where('content_category', 'Teacher training presentation')
    ->count();
if ($existing > 0) {
    throw new RuntimeException("Chapter $chapterId already has $existing Teacher training presentation row(s) - stopping to avoid duplicates.");
}

// --- Step 1: the 4 diagrams ---
$store = new App\Services\StudyDeck\StudyDeckImages();
$imageFiles = [
    '__NUMBER_SYSTEMS__' => 'number-systems.png',
    '__CLOSURE_GRID__' => 'closure-grid.png',
    '__ASSOCIATIVITY__' => 'associativity.png',
    '__DISTRIBUTIVITY__' => 'distributivity.png',
];
$imageIds = [];
foreach ($imageFiles as $placeholder => $f) {
    $bytes = file_get_contents($dir . $f);
    $r = $store->put($bytes, $tenant, $chapterId, null);
    $imageIds[$placeholder] = $r['id'];
}

// --- Step 2: resolve the validated web view's placeholders ---
$webHtmlPath = $dir . '../out/teacher-training.html';
$webHtml = file_get_contents($webHtmlPath);
foreach ($imageIds as $placeholder => $id) {
    $webHtml = str_replace('study-deck-image:' . $placeholder, 'study-deck-image:' . $id, $webHtml);
}
file_put_contents($webHtmlPath, $webHtml);

// --- Step 3: render the fuller PDF from the separate, richer source ---
$pdfSource = file_get_contents($dir . 'teacher-training-pdf.html');
foreach ($imageFiles as $placeholder => $f) {
    $bytes = file_get_contents($dir . $f);
    $dataUri = 'data:image/png;base64,' . base64_encode($bytes);
    $pdfSource = str_replace('src="' . $placeholder . '"', 'src="' . $dataUri . '"', $pdfSource);
}

$css = <<<'CSS'
@page { margin: 28px 32px; }
body { font-family: DejaVu Sans, sans-serif; color: #1e293b; font-size: 12.5px; line-height: 1.6; margin: 0; }
p { margin: 0 0 9px; }
strong { color: inherit; }

.cover { background: #141b2e; color: #ffffff; border-radius: 12px; padding: 22px 26px; margin-bottom: 0; }
.cover .eyebrow { display: block; font-size: 9.5px; letter-spacing: 0.14em; text-transform: uppercase; color: #9fb0d9; font-weight: 700; margin-bottom: 8px; }
.cover .lede { color: #c7d3e6; font-size: 11.5px; margin-top: 10px; line-height: 1.6; }
.palette-rule { width: 100%; border-collapse: collapse; margin: 14px 0 20px; }
.palette-rule td { height: 5px; padding: 0; }

.slide { border-radius: 10px; padding: 16px 18px; margin: 0 0 14px; }
.slide-head { margin: 0 0 9px; page-break-after: avoid; }
.slide-num { display: block; font-size: 8.5px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; opacity: 0.75; }
.slide-head h3 { font-size: 15px; margin: 2px 0 0; }
.explain, .cover { page-break-inside: avoid; }

.callout { border-radius: 8px; padding: 10px 12px; margin: 9px 0; border-left: 4px solid; background: rgba(255,255,255,0.65); page-break-inside: avoid; }
.callout-label { display: block; font-size: 8.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px; }
.callout-warn { border-color: #b45309; } .callout-warn .callout-label { color: #92400e; }
.callout-try { border-color: #0369a1; } .callout-try .callout-label { color: #075985; }
.callout-info { border-color: #0f7a72; } .callout-info .callout-label { color: #0a4a45; }
.callout p { font-size: 11px; margin: 0; }

.visual figure, .fig-lg { margin: 10px 0; text-align: center; page-break-inside: avoid; }
.visual img, figure img { max-width: 320px; width: 100%; border-radius: 8px; border: 1px solid rgba(0,0,0,0.08); }
.visual figcaption, figcaption { font-size: 9.5px; color: #475569; margin-top: 5px; font-style: italic; }

table { width: 100%; border-collapse: collapse; margin: 8px 0; font-size: 10px; }
th, td { border: 1px solid rgba(0,0,0,0.12); padding: 6px 7px; text-align: left; vertical-align: top; background: rgba(255,255,255,0.55); }
th { font-weight: 700; text-transform: uppercase; font-size: 9px; }
tr { page-break-inside: avoid; }

table.tiles td { background: transparent; border: none; padding: 4px 0; }
table.tiles .tile-num { font-weight: 700; color: inherit; width: 22px; }

.t1 { background: #fdf3ed; } .t1 .slide-num, .t1 h3 { color: #9a4015; }
.t2 { background: #f8f0fb; } .t2 .slide-num, .t2 h3 { color: #5a2277; }
.t3 { background: #edf8f6; } .t3 .slide-num, .t3 h3 { color: #0a4a45; }
.t4 { background: #fdf0f4; } .t4 .slide-num, .t4 h3 { color: #7c123c; }
.t5 { background: #eef1fc; } .t5 .slide-num, .t5 h3 { color: #3730a3; }
CSS;

$fullHtml = '<!doctype html><html><head><meta charset="utf-8"><style>' . $css . '</style></head><body>' . $pdfSource . '</body></html>';

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isFontSubsettingEnabled', true);
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($fullHtml);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$pdfBytes = $dompdf->output();

// --- Step 4: upload the PDF ---
$ts = time();
$filename = "teacher_training_presentation_rational_numbers_{$ts}.pdf";
$spacesPath = 'public/lms_content_file/' . $filename;
$disk = Storage::disk('digitalocean');
$disk->put($spacesPath, $pdfBytes, 'public');
$url = $disk->url($spacesPath);
if (empty($url)) {
    $endpoint = rtrim((string) env('DO_SPACES_ENDPOINT'), '/');
    $bucket = trim((string) env('DO_SPACES_BUCKET'), '/');
    $url = $endpoint && $bucket ? "{$endpoint}/{$bucket}/{$spacesPath}" : $spacesPath;
}

// --- Step 5: insert the content_master row ---
$contentId = (int) DB::table('content_master')->insertGetId([
    'grade_id' => $gradeId,
    'standard_id' => $standardId,
    'subject_id' => $subjectId,
    'chapter_id' => $chapterId,
    'title' => 'Rational Numbers Teacher training presentation',
    'description' => $webHtml,
    'file_folder' => '/lms_content_file',
    'filename' => $filename,
    'file_type' => 'pdf',
    'file_size' => (string) strlen($pdfBytes),
    'url' => $url,
    'show_hide' => 1,
    'content_category' => 'Teacher training presentation',
    'source' => 'Claude AI',
    'syear' => $syear,
    'sub_institute_id' => $tenant,
    'basic_advance' => '1',
    'created_by' => $userId,
]);

// --- Step 6: link the images to the new row ---
$linked = $store->link($contentId, array_values($imageIds), $chapterId);

echo "New image ids: " . json_encode($imageIds) . "\n";
echo "Image links created: $linked\n";
echo "PDF uploaded: $url\n";
echo "content_master row inserted: id=$contentId\n";
