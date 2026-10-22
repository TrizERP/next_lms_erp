<?php
// Run from the project root with:
//   php artisan tinker --execute="require 'C:/xampp/htdocs/next_lms_erp/content_bundles/std-9/mathematics/orienting-yourself-the-use-of-coordinates/assets/add_more_diagrams.php';"
//
// Inserts 2 more diagrams (Points on Axes, Midpoint) into study_deck_images
// for chapter 8594, links them, patches their real image IDs into
// out/teacher-training.html (replacing the __POINTS_ON_AXES__ /
// __MIDPOINT__ placeholders), and updates content_master row #61460's
// description in place with the result. No new content_master row.

$store = new App\Services\StudyDeck\StudyDeckImages();
$dir = __DIR__ . '/';
$chapterId = 8594;
$contentId = 61460;

$files = [
    '__POINTS_ON_AXES__' => 'points-on-axes.png',
    '__MIDPOINT__' => 'midpoint.png',
];

$ids = [];
foreach ($files as $placeholder, $f) {
    $bytes = file_get_contents($dir . $f);
    $r = $store->put($bytes, 1, $chapterId, null);
    $ids[$placeholder] = $r['id'];
}

$htmlPath = $dir . '../out/teacher-training.html';
$html = file_get_contents($htmlPath);
foreach ($ids as $placeholder, $id) {
    $html = str_replace("study-deck-image:$placeholder", "study-deck-image:$id", $html);
}
file_put_contents($htmlPath, $html);

$row = DB::table('content_master')->where('id', $contentId)->first(['id', 'chapter_id', 'content_category']);
if (!$row || (int) $row->chapter_id !== $chapterId || $row->content_category !== 'Teacher training presentation') {
    throw new RuntimeException("Row $contentId is not the expected chapter $chapterId Teacher training presentation row - stopping.");
}

$updated = DB::table('content_master')->where('id', $contentId)->update(['description' => $html]);
$linked = $store->link($contentId, array_values($ids), $chapterId);

echo "New image ids: " . json_encode($ids) . "\n";
echo "Rows updated: $updated\n";
echo "New image links created: $linked\n";
