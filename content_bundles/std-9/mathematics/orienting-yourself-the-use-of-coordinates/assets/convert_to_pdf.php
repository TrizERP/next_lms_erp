<?php
// Run from the project root with:
//   php artisan tinker --execute="require 'C:/xampp/htdocs/next_lms_erp/content_bundles/std-9/mathematics/orienting-yourself-the-use-of-coordinates/assets/convert_to_pdf.php';"
//
// For content_master row #61460 (chapter 8594 Teacher training presentation):
//  1. Inserts 4 diagrams (Points on Axes, Midpoint, bare Coordinate System,
//     Sign Pattern) into study_deck_images and links them all to this row.
//  2. Updates the row's `description` (the live web view) with the
//     validated 10-slide HTML - unchanged content, just the 2 placeholders
//     it already references (Points on Axes, Midpoint) resolved to real ids.
//  3. Renders a separate, fuller 10-page PDF from teacher-training-pdf.html
//     (real-world applications, extra misconceptions, a 9-question
//     assessment bank, all 7 diagrams) - this is what downloads as the file,
//     replacing the stored .pptx/.pdf. The validated web HTML is untouched.
// Does not insert a new content_master row.

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;

$dir = __DIR__ . '/';
$chapterId = 8594;
$contentId = 61460;

// --- Step 1: the 4 diagrams ---
$store = new App\Services\StudyDeck\StudyDeckImages();
$imageFiles = [
    '__POINTS_ON_AXES__' => 'points-on-axes.png',
    '__MIDPOINT__' => 'midpoint.png',
    '__COORD_SYSTEM__' => 'coordinate-system.png',
    '__SIGN_PATTERN__' => 'sign-pattern.png',
];
$imageIds = [];
foreach ($imageFiles as $placeholder => $f) {
    $bytes = file_get_contents($dir . $f);
    $r = $store->put($bytes, 1, $chapterId, null);
    $imageIds[$placeholder] = $r['id'];
}
$linked = $store->link($contentId, array_values($imageIds), $chapterId);

// --- Step 2: the validated web view only needs the 2 refs it already has ---
$webHtmlPath = $dir . '../out/teacher-training.html';
$webHtml = file_get_contents($webHtmlPath);
$webHtml = str_replace('study-deck-image:__POINTS_ON_AXES__', 'study-deck-image:' . $imageIds['__POINTS_ON_AXES__'], $webHtml);
$webHtml = str_replace('study-deck-image:__MIDPOINT__', 'study-deck-image:' . $imageIds['__MIDPOINT__'], $webHtml);
file_put_contents($webHtmlPath, $webHtml);

$row = DB::table('content_master')->where('id', $contentId)->first(['id', 'chapter_id', 'content_category']);
if (!$row || (int) $row->chapter_id !== $chapterId || $row->content_category !== 'Teacher training presentation') {
    throw new RuntimeException("Row $contentId is not the expected chapter $chapterId Teacher training presentation row - stopping.");
}
DB::table('content_master')->where('id', $contentId)->update(['description' => $webHtml]);

// --- Step 3: render the fuller PDF from the separate, richer source ---
$pdfSource = file_get_contents($dir . 'teacher-training-pdf.html');
foreach ($imageFiles as $placeholder => $f) {
    // The PDF source uses the same placeholders for these 4; resolve directly from local bytes (no DB round-trip needed).
    $bytes = file_get_contents($dir . $f);
    $dataUri = 'data:image/png;base64,' . base64_encode($bytes);
    $pdfSource = str_replace('src="study-deck-image:' . $placeholder . '"', 'src="' . $dataUri . '"', $pdfSource);
}
// The 3 pre-existing numeric refs (157 quadrants, 159 distance, 160 reflection) resolve via the store.
$pdfHtml = preg_replace_callback('/src="study-deck-image:(\d+)"/', function ($m) use ($store) {
    $doc = $store->forDocument((int) $m[1], 1);
    if (!$doc) {
        return $m[0];
    }
    return 'src="data:' . $doc['mime'] . ';base64,' . base64_encode($doc['bytes']) . '"';
}, $pdfSource);

$css = <<<'CSS'
@page { margin: 28px 32px; }
body { font-family: DejaVu Sans, sans-serif; color: #1e293b; font-size: 12.5px; line-height: 1.6; margin: 0; }
p { margin: 0 0 9px; }
strong { color: inherit; }

.doc-cover { background: #141b2e; color: #ffffff; border-radius: 12px; padding: 22px 26px; margin-bottom: 0; }
.doc-cover .eyebrow2 { display: block; font-size: 9.5px; letter-spacing: 0.14em; text-transform: uppercase; color: #9fb0d9; font-weight: 700; margin-bottom: 8px; }
.doc-cover h1 { color: #ffffff; font-size: 21px; margin: 0; }
.doc-cover .dek { color: #c7d3e6; font-size: 11px; margin-top: 10px; }
.palette-rule { width: 100%; border-collapse: collapse; margin: 14px 0 20px; }
.palette-rule td { height: 5px; padding: 0; }

.slide { border-radius: 10px; padding: 16px 18px; margin: 0 0 14px; page-break-inside: avoid; }
.slide-head { margin: 0 0 9px; }
.slide-num { display: block; font-size: 8.5px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; opacity: 0.75; }
.slide-head h3 { font-size: 15px; margin: 2px 0 0; }

.callout { border-radius: 8px; padding: 10px 12px; margin: 9px 0; border-left: 4px solid; background: rgba(255,255,255,0.65); }
.callout-label { display: block; font-size: 8.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px; }
.callout-warn { border-color: #b45309; } .callout-warn .callout-label { color: #92400e; }
.callout-try { border-color: #0369a1; } .callout-try .callout-label { color: #075985; }
.callout p { font-size: 11px; margin: 0; }

.visual figure { margin: 10px 0; text-align: center; }
.visual img { max-width: 280px; width: 100%; border-radius: 8px; border: 1px solid rgba(0,0,0,0.08); }
.visual figcaption { font-size: 9.5px; color: #475569; margin-top: 5px; font-style: italic; }

table { width: 100%; border-collapse: collapse; margin: 8px 0; font-size: 10px; }
th, td { border: 1px solid rgba(0,0,0,0.12); padding: 6px 7px; text-align: left; vertical-align: top; background: rgba(255,255,255,0.55); }
th { font-weight: 700; text-transform: uppercase; font-size: 9px; }

.t1 { background: #fdf3ed; } .t1 .slide-num, .t1 h3 { color: #9a4015; }
.t2 { background: #f8f0fb; } .t2 .slide-num, .t2 h3 { color: #5a2277; }
.t3 { background: #edf8f6; } .t3 .slide-num, .t3 h3 { color: #0a4a45; }
.t4 { background: #fdf0f4; } .t4 .slide-num, .t4 h3 { color: #7c123c; }
.t5 { background: #eef1fc; } .t5 .slide-num, .t5 h3 { color: #3730a3; }
CSS;

$fullHtml = '<!doctype html><html><head><meta charset="utf-8"><style>' . $css . '</style></head><body>' . $pdfHtml . '</body></html>';

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isFontSubsettingEnabled', true);
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($fullHtml);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$pdfBytes = $dompdf->output();

// --- Step 4: upload the PDF and swap the stored file ---
$ts = time();
$filename = "teacher_training_presentation_orienting_yourself_the_use_of_coordinates_{$ts}.pdf";
$spacesPath = 'public/lms_content_file/' . $filename;
$disk = Storage::disk('digitalocean');
$disk->put($spacesPath, $pdfBytes, 'public');
$url = $disk->url($spacesPath);
if (empty($url)) {
    $endpoint = rtrim((string) env('DO_SPACES_ENDPOINT'), '/');
    $bucket = trim((string) env('DO_SPACES_BUCKET'), '/');
    $url = $endpoint && $bucket ? "{$endpoint}/{$bucket}/{$spacesPath}" : $spacesPath;
}

DB::table('content_master')->where('id', $contentId)->update([
    'filename' => $filename,
    'file_type' => 'pdf',
    'file_size' => (string) strlen($pdfBytes),
    'url' => $url,
]);

echo "New image ids: " . json_encode($imageIds) . "\n";
echo "Image links created: $linked\n";
echo "PDF uploaded: $url\n";
echo "content_master #$contentId updated: description (web, unchanged 10 slides) + file (new richer 10-page PDF)\n";
