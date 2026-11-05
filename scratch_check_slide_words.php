<?php
$bundleDir = __DIR__ . '/storage/app/study-deck/chapter-25892';
$slides = json_decode(file_get_contents("$bundleDir/slides.json"), true);
foreach ([2, 8, 12, 18, 23, 26] as $n) {
    echo "--- Slide $n ---\n" . $slides[$n]['body'] . "\n";
}
