<?php
// Run from the project root with:
//   php artisan tinker --execute="require 'C:/xampp/htdocs/next_lms_erp/content_bundles/std-9/mathematics/orienting-yourself-the-use-of-coordinates/assets/update_teacher_resources.php';"
//
// Regenerates and UPDATES the 4 existing Teacher Resource rows for chapter
// 8594 (#61465 Mindmap, #61466 KSA Framework, #61467 Assessment Rubrics,
// #61468 Learning Outcomes) with richer, per-topic content that matches the
// depth of chapter 22905's reference resources. Updates the SAME rows in
// place (new file uploaded, row's filename/url/file_size updated) rather
// than inserting new ones, so there is no duplication.

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;

$dir = __DIR__ . '/';

function renderDocToPdf(string $title, string $bodyHtml): string
{
    $css = <<<'CSS'
@page { margin: 28px 32px; }
body { font-family: DejaVu Sans, sans-serif; color: #1e293b; font-size: 13px; line-height: 1.6; margin: 0; }
strong { color: inherit; }

.cover { background: #141b2e; color: #ffffff; border-radius: 12px; padding: 22px 26px; margin-bottom: 0; }
.cover .eyebrow { display: block; font-size: 9.5px; letter-spacing: 0.14em; text-transform: uppercase; color: #9fb0d9; font-weight: 700; margin-bottom: 8px; }
.cover h1 { color: #ffffff; font-size: 21px; margin: 0; }
.cover .dek { color: #c7d3e6; font-size: 11px; margin-top: 10px; line-height: 1.55; }

.palette-rule { width: 100%; border-collapse: collapse; margin: 14px 0 22px; }
.palette-rule td { height: 5px; padding: 0; }

h1 { font-size: 19px; color: #3730a3; margin: 0 0 4px; }
h2 { font-size: 14.5px; margin: 0 0 3px; }
p.intro { font-size: 11px; color: #475569; margin: 4px 0 10px; }

.topic { margin: 0 0 20px; padding: 14px 16px; border-radius: 10px; page-break-inside: avoid; }
.topic .num { display: block; font-size: 9px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; margin-bottom: 4px; }

table { width: 100%; border-collapse: collapse; margin: 6px 0; font-size: 10.5px; }
th, td { border: 1px solid #e2e8f0; padding: 6px 8px; text-align: left; vertical-align: top; }
th { font-weight: 700; font-size: 9.5px; text-transform: uppercase; letter-spacing: 0.03em; }

.notes { margin-top: 10px; padding: 11px 13px; border-radius: 8px; border-left: 4px solid; background: rgba(255,255,255,0.55); }
.notes strong { display: block; margin-bottom: 5px; font-size: 9.5px; text-transform: uppercase; letter-spacing: 0.04em; }
.notes ul { margin: 0; padding-left: 15px; }
.notes li { margin-bottom: 3px; }

ul.outcomes { list-style: none; margin: 8px 0 0; padding: 0; }
ul.outcomes li { margin-bottom: 6px; padding: 7px 9px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 10.5px; }
ul.outcomes li .code { font-weight: 700; color: #0f7a72; margin-right: 6px; }

.t1 { background: #fdf3ed; } .t1 h2 { color: #9a4015; } .t1 .num { color: #c2572a; } .t1 th { background: #fbe8dd; color: #7a3012; } .t1 .notes { border-color: #c2572a; }
.t2 { background: #f8f0fb; } .t2 h2 { color: #5a2277; } .t2 .num { color: #8338a8; } .t2 th { background: #f0def7; color: #5a2277; } .t2 .notes { border-color: #8338a8; }
.t3 { background: #edf8f6; } .t3 h2 { color: #0a4a45; } .t3 .num { color: #0f7a72; } .t3 th { background: #d9f2ef; color: #0a4a45; } .t3 .notes { border-color: #0f7a72; }
.t4 { background: #fdf0f4; } .t4 h2 { color: #7c123c; } .t4 .num { color: #b5174e; } .t4 th { background: #fbdce8; color: #7c123c; } .t4 .notes { border-color: #b5174e; }
.t5 { background: #eef1fc; } .t5 h2 { color: #3730a3; } .t5 .num { color: #4f46e5; } .t5 th { background: #e0e4fb; color: #3730a3; } .t5 .notes { border-color: #4f46e5; }
CSS;
    $html = '<!doctype html><html><head><meta charset="utf-8"><title>' . e($title) . '</title><style>' . $css . '</style></head><body>' . $bodyHtml . '</body></html>';

    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isFontSubsettingEnabled', true);
    $options->set('isRemoteEnabled', true);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    return $dompdf->output();
}

function uploadToSpaces(string $bytes, string $filename): string
{
    $spacesPath = 'public/lms_teacher_resource/' . $filename;
    $disk = Storage::disk('digitalocean');
    $disk->put($spacesPath, $bytes, 'public');
    $url = $disk->url($spacesPath);
    if (empty($url)) {
        $endpoint = rtrim((string) env('DO_SPACES_ENDPOINT'), '/');
        $bucket = trim((string) env('DO_SPACES_BUCKET'), '/');
        $url = $endpoint && $bucket ? "{$endpoint}/{$bucket}/{$spacesPath}" : $spacesPath;
    }

    return $url;
}

function updateRow(int $id, int $chapterId, string $expectedTitle, string $filename, string $fileType, int $fileSize, string $url): int
{
    $row = DB::table('content_master')->where('id', $id)->first(['id', 'chapter_id', 'title', 'content_category']);
    if (!$row || (int) $row->chapter_id !== $chapterId || $row->title !== $expectedTitle || $row->content_category !== 'Teacher Training') {
        throw new RuntimeException("Row $id is not the expected chapter $chapterId \"$expectedTitle\" row - stopping.");
    }

    return DB::table('content_master')->where('id', $id)->update([
        'filename' => $filename,
        'file_type' => $fileType,
        'file_size' => (string) $fileSize,
        'url' => $url,
    ]);
}

$chapterId = 8594;
$ts = time();
$results = [];

// 1. Mindmap (#61465) - deeper 3-level branching tree
$bytes = file_get_contents($dir . 'mindmap.png');
$filename = "lms_teacher_resource_mindmap_orienting_yourself_{$ts}.png";
$url = uploadToSpaces($bytes, $filename);
$results['mindmap'] = updateRow(61465, $chapterId, 'Mindmap', $filename, 'png', strlen($bytes), $url);

// 2. KSA Framework (#61466) - now per-topic (5 sections) with narrative framing
$html = file_get_contents($dir . 'ksa-framework.html');
$pdf = renderDocToPdf('Orienting Yourself: The Use of Coordinates - KSA Framework', $html);
$filename = "lms_teacher_resource_ksa_framework_{$ts}.pdf";
$url = uploadToSpaces($pdf, $filename);
$results['ksa_framework'] = updateRow(61466, $chapterId, 'KSA Framework (Knowledge, Skill And Ability)', $filename, 'pdf', strlen($pdf), $url);

// 3. Assessment Rubrics (#61467) - now per-topic (5 sections) with Notes for Teachers
$html = file_get_contents($dir . 'assessment-rubrics.html');
$pdf = renderDocToPdf('Orienting Yourself: The Use of Coordinates - Assessment Rubrics', $html);
$filename = "lms_teacher_resource_assessment_rubrics_{$ts}.pdf";
$url = uploadToSpaces($pdf, $filename);
$results['assessment_rubrics'] = updateRow(61467, $chapterId, 'Assessment Rubrics', $filename, 'pdf', strlen($pdf), $url);

// 4. Learning Outcomes (#61468) - now global list + 5 topic sections
$html = file_get_contents($dir . 'learning-outcomes.html');
$pdf = renderDocToPdf('Orienting Yourself: The Use of Coordinates - Learning Outcomes', $html);
$filename = "lms_teacher_resource_learning_outcomes_{$ts}.pdf";
$url = uploadToSpaces($pdf, $filename);
$results['learning_outcomes'] = updateRow(61468, $chapterId, 'Learning Outcomes', $filename, 'pdf', strlen($pdf), $url);

echo json_encode($results, JSON_PRETTY_PRINT) . "\n";
echo "Each value is rows updated (1 = success). Paste this output back to Claude to verify.\n";
