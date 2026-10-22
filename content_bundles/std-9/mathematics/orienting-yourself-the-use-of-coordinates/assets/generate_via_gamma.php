<?php
// Run from the project root with:
//   php artisan tinker --execute="require 'C:/xampp/htdocs/next_lms_erp/content_bundles/std-9/mathematics/orienting-yourself-the-use-of-coordinates/assets/generate_via_gamma.php';"
//
// Calls the SAME code path the live "Generate" button uses
// (contentController::storeGammaContent), for chapter 8594 (Std 9 Maths,
// "Orienting Yourself: The Use of Coordinates"). This is a REAL call to the
// Gamma API - it spends real credits and can take several minutes.
//
// It does NOT touch or remove content_master row #61460 (the current grounded
// deck). It creates a brand-new row for the Gamma-generated deck. Once you've
// looked at the result and are happy with it, run hide_old_row.php separately
// to hide #61460.

$prompt = file_get_contents(__DIR__ . '/gamma_prompt.txt');
if ($prompt === false || trim($prompt) === '') {
    throw new RuntimeException('Could not read gamma_prompt.txt');
}

$request = Illuminate\Http\Request::create('/api/lms/gamma-content-master', 'POST', [
    'sub_institute_id' => 1,
    'user_id' => 1,
    'syear' => 2026,
    'prompt' => $prompt,
    'chapter_name' => 'Orienting Yourself: The Use of Coordinates',
    'chapter_id' => 8594,
    'content_type' => 'teacher_training_presentation',
    'format' => 'presentation',
    'export_format' => 'pptx',
    'slide_count' => 20,
]);

echo "Calling Gamma for chapter 8594 - this can take several minutes. Please wait...\n";

$controller = app(App\Http\Controllers\lms\contentController::class);
$response = $controller->storeGammaContent($request);

echo "HTTP status: " . $response->getStatusCode() . "\n";
echo $response->getContent() . "\n";
