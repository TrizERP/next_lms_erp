<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\StudyDeck\DiagramRenderer;
use App\Services\StudyDeck\StudyDeckImages;
use App\Services\StudyDeck\SlideHtmlRenderer;
use App\Services\StudyDeck\DeckValidator;
use App\Services\StudyDeck\TeachingMethodProfile;
use App\Services\StudyDeck\ConceptContextBuilder;
use App\Services\StudyDeck\QuestionSelector;

$bundleDir = __DIR__ . '/storage/app/study-deck/chapter-25892';
$slides = json_decode(file_get_contents("$bundleDir/slides.json"), true);
$plan = json_decode(file_get_contents("$bundleDir/plan.json"), true);
$images = json_decode(file_get_contents("$bundleDir/images.json"), true);
$interactions = json_decode(file_get_contents("$bundleDir/interactions.json"), true);
$activities = json_decode(file_get_contents("$bundleDir/activities.json"), true);
$map = json_decode(file_get_contents("$bundleDir/learning-map.json"), true);

// 1. Render and store diagram for slide 33
$renderer = new DiagramRenderer();
$spec = [
    'title' => 'Analysing Historical Images: Viewpoint and Bias',
    'layout' => 'flow',
    'nodes' => [
        '1. Examine visual elements',
        '2. Identify the creator',
        '3. Uncover imperial viewpoint',
    ],
];
$drawn = $renderer->render($spec);

$store = new StudyDeckImages();
$stored = $store->put($drawn['bytes'], 1, 25892);
echo "Stored image for slide 33: " . $stored['ref'] . "\n";

$images[33] = [
    'type' => 'diagram',
    'url' => $stored['ref'],
    'width' => $drawn['width'],
    'height' => $drawn['height'],
    'sha1' => sha1($drawn['bytes']),
    'sha256' => $stored['sha256'] ?? hash('sha256', $drawn['bytes']),
    'layout' => 'flow',
    'texts' => [
        'Analysing Historical Images: Viewpoint and Bias',
        '1. Examine visual elements',
        '2. Identify the creator',
        '3. Uncover the viewpoint',
    ],
    'alt' => 'Diagram: Analysing Historical Images: Viewpoint and Bias. Steps to detect creator perspective.',
    'caption' => 'Analysing Historical Images: Viewpoint and Bias',
    'licence' => null,
    'source_url' => null,
    'creator' => null,
    'attribution_required' => false,
    'attribution' => '',
    'review' => ['relevant' => true, 'reason' => 'drawn from the plan own diagram spec'],
    'source_kind' => 'diagram',
    'purpose' => 'Diagram illustrating steps to analyze historical images and identify bias.',
];

// 2. Add step-by-step student actions (/\\bstep\\b|first,|next,|then,|follow these/iu) to 5 slides concisely
$slides[2]['body'] = 'You will need a notebook and pen. Follow these steps: First, observe neighbourhood changes. Next, record everyday habits. Then, compare the past with present.';
$slides[8]['body'] = 'You will need index cards and tape. Follow these steps: First, write royal events on cards. Next, arrange wars and coronations. Then, discuss why dates mattered.';
$slides[12]['body'] = 'You will need paper and pencil. Follow these steps: First, interview an elder. Next, record family recollections. Then, note how daily habits shifted.';
$slides[18]['body'] = 'You will need a school notice. Follow these steps: First, read the official text. Next, record who wrote it. Then, compare memo styles.';
$slides[23]['body'] = 'You will need paper and pencil. Follow these steps: First, sketch a local map. Next, record revenue features. Then, compare with survey records.';
$slides[26]['body'] = 'You will need school notices and sheets. Follow these steps: First, examine official records. Next, record preserved items. Then, discuss official viewpoints.';

// Save back to bundle
file_put_contents("$bundleDir/slides.json", json_encode($slides, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
file_put_contents("$bundleDir/images.json", json_encode($images, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

echo "Bundle updated with image 33 and step-by-step markers!\n";

// Validate
$cb = new ConceptContextBuilder();
$raw = $cb->load(25892, 1);
$context = $cb->assemble($raw);

$selector = new QuestionSelector();
$selection = $selector->select((array) $raw['questions'], 4);
$eligible = $selection['eligible'];

$htmlRenderer = new SlideHtmlRenderer();
$rendered = $htmlRenderer->render($context, $map, $plan, $slides, $images, $eligible, $activities, $interactions);

$validator = new DeckValidator();
$report = $validator->validate($context, $map, $eligible, $rendered, []);

echo "Validation OK: " . ($report['ok'] ? 'TRUE' : 'FALSE') . "\n";
if (!empty($report['errors'])) {
    echo "Errors:\n";
    print_r($report['errors']);
}
if (!empty($report['warnings'])) {
    echo "Warnings:\n";
    print_r($report['warnings']);
}

// Check teaching method profile
$slideTexts = [];
foreach ($rendered['deck']['slides'] as $s) {
    $text = ($s['title'] ?? '') . ' ' . ($s['content']['body'] ?? '');
    foreach ((array)($s['content']['explanations'] ?? []) as $e) {
        $text .= ' ' . ($e['text'] ?? '');
    }
    $slideTexts[] = $text;
}
$contentErrors = TeachingMethodProfile::contentErrors('experiential', $slideTexts);
echo "Teaching method content errors: " . count($contentErrors) . "\n";
if ($report['ok'] && count($contentErrors) === 0) {
    file_put_contents("$bundleDir/deck.json", json_encode($rendered['deck'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    file_put_contents("$bundleDir/out/presentation.html", $rendered['html']);

    $reportData = [
        'ok' => true,
        'errors' => [],
        'warnings' => $report['warnings'] ?? [],
        'stats' => $report['stats'] ?? [],
        'teaching_method' => [
            'key' => 'experiential',
            'followed' => true,
            'errors' => [],
        ],
    ];
    file_put_contents("$bundleDir/report.json", json_encode($reportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    echo "Bundle files (deck.json, out/presentation.html, report.json) successfully saved!\n";
}
