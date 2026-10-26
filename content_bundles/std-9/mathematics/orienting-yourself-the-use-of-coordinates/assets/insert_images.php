<?php
// Run from the project root with:
//   php artisan tinker --execute="require 'content_bundles/std-9/mathematics/orienting-yourself-the-use-of-coordinates/assets/insert_images.php';"
//
// Inserts the 4 diagrams for chapter 8594 (Std 9 Maths, "Orienting Yourself: The Use
// of Coordinates") into study_deck_images, tagged to that chapter, tenant 1 (platform
// library). Safe to re-run: StudyDeckImages::put() de-dupes by sha256, so a repeat just
// returns the same ids instead of inserting twice.

$store = new App\Services\StudyDeck\StudyDeckImages();
$dir = __DIR__ . '/';

$files = [
    'quadrants' => 'quadrants.png',
    'coordinates_of_a_point' => 'coordinates-of-a-point.png',
    'distance_formula' => 'distance-formula.png',
    'reflection' => 'reflection.png',
];

$results = [];
foreach ($files as $key => $f) {
    $bytes = file_get_contents($dir . $f);
    $results[$key] = $store->put($bytes, 1, 8594, null);
}

echo json_encode($results, JSON_PRETTY_PRINT) . PHP_EOL;
echo "\nCopy the 'id' for each key above back to Claude so it can swap them into the Teacher Training HTML.\n";
