<?php
$bundleDir = __DIR__ . '/storage/app/study-deck/chapter-25892';
$slides = json_decode(file_get_contents("$bundleDir/slides.json"), true);

$markers = [
    'materials' => ['/you will need|materials|equipment|set up|get ready|prepare/iu', 3],
    'steps' => ['/\\bstep\\b|first,|next,|then,|follow these/iu', 5],
    'record' => ['/record|write down|note down|observation (table|sheet)|draw what/iu', 3],
    'daily_life' => ['/daily life|at home|real life|everyday|in your (home|school|village|town)/iu', 2],
];

foreach ($markers as $name => [$regex, $needed]) {
    $matching = [];
    foreach ($slides as $n => $s) {
        $text = ($s['title'] ?? '') . ' ' . ($s['body'] ?? '');
        foreach ((array)($s['explanations'] ?? []) as $e) {
            $text .= ' ' . ($e['text'] ?? '');
        }
        if (preg_match($regex, $text)) {
            $matching[] = $n;
        }
    }
    echo "$name (needed: $needed): count = " . count($matching) . " [slides: " . implode(', ', $matching) . "]\n";
}

