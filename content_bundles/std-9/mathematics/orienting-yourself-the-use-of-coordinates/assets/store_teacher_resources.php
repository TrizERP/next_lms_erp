<?php
// Run from the project root with:
//   php artisan tinker --execute="require 'C:/xampp/htdocs/next_lms_erp/content_bundles/std-9/mathematics/orienting-yourself-the-use-of-coordinates/assets/store_teacher_resources.php';"
//
// Stores 4 new Teacher Resource items for chapter 8594 (Std 9 Maths,
// "Orienting Yourself: The Use of Coordinates"): Mindmap, KSA Framework,
// Assessment Rubrics, Learning Outcomes. Matches the exact storage pattern
// already used for every other chapter's Teacher Resources
// (content_master, content_category = "Teacher Training", one row per
// resource type, file uploaded to the same /lms_teacher_resource folder on
// DigitalOcean Spaces) - the same table and category chapter 22905's own
// Teacher Resources use, so these appear in the same place in the UI.
//
// Does not touch row #61460 (the Teacher training presentation deck) or
// anything for any other chapter.

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;

$dir = __DIR__ . '/';
$chapterId = 8594;
$gradeId = 12;
$standardId = 42;
$subjectId = 3976;
$tenant = 1;
$syear = 2026;
$userId = 1;
$chapterName = 'Orienting Yourself: The Use of Coordinates';

function renderDocToPdf(string $title, string $bodyHtml): string
{
    $css = <<<'CSS'
@page { margin: 28px 32px; }
body { font-family: DejaVu Sans, sans-serif; color: #1e293b; font-size: 12.5px; line-height: 1.6; margin: 0; }
h1 { font-size: 19px; color: #3730a3; margin: 0 0 4px; }
h2 { font-size: 14.5px; color: #3730a3; border-bottom: 2px solid #c7d2fe; padding-bottom: 4px; margin: 20px 0 8px; }
p { margin: 0 0 10px; }
table { width: 100%; border-collapse: collapse; margin: 8px 0 14px; }
th, td { border: 1px solid #e2e8f0; padding: 6px 8px; text-align: left; vertical-align: top; }
th { background: #eef2ff; color: #3730a3; font-weight: 600; }
strong { color: #1e293b; }
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

function insertRow(array $base, string $title, string $filename, string $fileType, int $fileSize, string $url): int
{
    $row = $base + [
        'title' => $title,
        'description' => null,
        'file_folder' => '/lms_teacher_resource',
        'filename' => $filename,
        'file_type' => $fileType,
        'file_size' => (string) $fileSize,
        'url' => $url,
        'sort_order' => null,
        'meta_tags' => null,
        'content_category' => 'Teacher Training',
        'source' => 'Claude AI',
        'basic_advance' => '1',
    ];
    DB::table('content_master')->insert($row);

    return (int) DB::getPdo()->lastInsertId();
}

$base = [
    'grade_id' => $gradeId,
    'standard_id' => $standardId,
    'subject_id' => $subjectId,
    'chapter_id' => $chapterId,
    'show_hide' => 1,
    'sub_institute_id' => $tenant,
    'syear' => $syear,
    'created_by' => $userId,
];

$results = [];

// 1. Mindmap (image, no PDF conversion needed)
$bytes = file_get_contents($dir . 'mindmap.png');
$ts = time();
$filename = "lms_teacher_resource_mindmap_orienting_yourself_{$ts}.png";
$url = uploadToSpaces($bytes, $filename);
$results['mindmap'] = insertRow($base, 'Mindmap', $filename, 'png', strlen($bytes), $url);

// 2. KSA Framework
$html = file_get_contents($dir . 'ksa-framework.html');
$pdf = renderDocToPdf($chapterName . ' - KSA Framework', $html);
$filename = "lms_teacher_resource_ksa_framework_{$ts}.pdf";
$url = uploadToSpaces($pdf, $filename);
$results['ksa_framework'] = insertRow($base, 'KSA Framework (Knowledge, Skill And Ability)', $filename, 'pdf', strlen($pdf), $url);

// 3. Assessment Rubrics
$html = file_get_contents($dir . 'assessment-rubrics.html');
$pdf = renderDocToPdf($chapterName . ' - Assessment Rubrics', $html);
$filename = "lms_teacher_resource_assessment_rubrics_{$ts}.pdf";
$url = uploadToSpaces($pdf, $filename);
$results['assessment_rubrics'] = insertRow($base, 'Assessment Rubrics', $filename, 'pdf', strlen($pdf), $url);

// 4. Learning Outcomes
$html = file_get_contents($dir . 'learning-outcomes.html');
$pdf = renderDocToPdf($chapterName . ' - Learning Outcomes', $html);
$filename = "lms_teacher_resource_learning_outcomes_{$ts}.pdf";
$url = uploadToSpaces($pdf, $filename);
$results['learning_outcomes'] = insertRow($base, 'Learning Outcomes', $filename, 'pdf', strlen($pdf), $url);

echo json_encode($results, JSON_PRETTY_PRINT) . "\n";
echo "Paste this output back to Claude to verify.\n";
