<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\StudyDeck\ConceptContextBuilder;

$cb = new ConceptContextBuilder();
$raw = $cb->load(25892, 1);
$context = $cb->assemble($raw);

echo "Chapter: " . $context['chapter']['chapter_name'] . "\n";
echo "Subject: " . $context['chapter']['subject_name'] . "\n";
echo "Standard: " . $context['chapter']['standard_name'] . "\n";
echo "Concepts count: " . count($context['concepts']) . "\n";
foreach ($context['concepts'] as $c) {
    echo "  - {$c['id']}: {$c['name']}\n";
}
