<?php
// Run from the project root with:
//   php artisan tinker --execute="require 'C:/xampp/htdocs/next_lms_erp/content_bundles/std-9/mathematics/orienting-yourself-the-use-of-coordinates/assets/update_content.php';"
//
// Updates content_master row #61460 (chapter 8594, "Teacher training presentation")
// IN PLACE with the HTML that now carries real study-deck-image: references, and
// links the 4 images to this content row so they are never swept up as "unused" by
// a future study-deck:images-prune run. Does not insert a new row, so there is no
// risk of duplicating the chapter's Teacher Training content.

$contentId = 61460;
$chapterId = 8594;
$imageIds = [157, 158, 159, 160];

$html = file_get_contents(__DIR__ . '/../out/teacher-training.html');
if ($html === false || trim($html) === '') {
    throw new RuntimeException('Could not read the updated teacher-training.html');
}

$before = DB::table('content_master')->where('id', $contentId)->first(['id', 'chapter_id', 'content_category']);
if (!$before || (int) $before->chapter_id !== $chapterId || $before->content_category !== 'Teacher training presentation') {
    throw new RuntimeException('Row ' . $contentId . ' is not the expected chapter 8594 Teacher training presentation row - stopping.');
}

$updated = DB::table('content_master')->where('id', $contentId)->update([
    'description' => $html,
]);

$linked = (new App\Services\StudyDeck\StudyDeckImages())->link($contentId, $imageIds, $chapterId);

echo "Rows updated: $updated\n";
echo "New image links created: $linked\n";
echo "New description length: " . strlen($html) . " chars\n";
