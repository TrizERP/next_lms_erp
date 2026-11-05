<?php
$bundleDir = __DIR__ . '/storage/app/study-deck/chapter-25892';
$plan = json_decode(file_get_contents("$bundleDir/plan.json"), true);
echo "Slide 33 plan:\n";
print_r($plan['slides'][32]);

