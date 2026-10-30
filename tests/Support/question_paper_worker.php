<?php

/**
 * A REAL, long-running Question Paper queue worker for QuestionPaperAutoProcessingTest. Not a test itself.
 *
 *   php tests/Support/question_paper_worker.php <spec.json>
 *
 * It runs exactly what production runs (`queue:work question_papers --queue=question-papers`, the same Laravel
 * worker loop polling the `jobs` table) in its own PHP process, against a SCRATCH sqlite file, with the AI parser
 * and Spaces storage replaced by local fakes. Nothing starts it per job: it is started once, then picks up
 * whatever is queued, which is the behaviour under test.
 *
 * Spec: { "db": "<abs path of a qp-worker-*.sqlite file in the system temp dir>",
 *         "files_dir": "<folder holding the uploaded PDFs>", "parser_file": "<json: {header, questions}>",
 *         "ready": "<file written once the worker loop is about to start>",
 *         "cache_driver": "array|file" (optional), "heartbeat_key": "<unique cache key>" (optional) }
 *
 * Refuses to run against anything but a scratch sqlite file inside the system temp directory, so it cannot reach
 * the shared database whatever `.env` says (process-level variables win over `.env`).
 */

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Tests\Support\FileBackedDocumentStorage;
use Tests\Support\FixtureQuestionPaperParser;

$specPath = $argv[1] ?? '';
$spec = is_file($specPath) ? json_decode((string) file_get_contents($specPath), true) : null;

if (! is_array($spec) || ! isset($spec['db'], $spec['files_dir'], $spec['parser_file'], $spec['ready'])) {
    fwrite(STDERR, "usage: question_paper_worker.php <spec.json>\n");
    exit(2);
}

$tmp = rtrim(str_replace('\\', '/', realpath(sys_get_temp_dir()) ?: sys_get_temp_dir()), '/');
$db = str_replace('\\', '/', (string) $spec['db']);
if (! str_starts_with($db, $tmp . '/') || ! preg_match('/^qp-worker-[A-Za-z0-9_.-]+\.sqlite$/', basename($db)) || ! is_file($db)) {
    fwrite(STDERR, "refusing: not an existing scratch qp-worker-*.sqlite file in the system temp directory\n");
    exit(3);
}

foreach ([
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => $db,
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '1',
    'REDIS_HOST' => '127.0.0.1',
    'REDIS_PORT' => '1',
    // 'file' lets the test process read the worker's heartbeat; the key is unique per test so it can never touch a real one.
    'CACHE_DRIVER' => (string) ($spec['cache_driver'] ?? 'array'),
    'QUESTION_PAPER_HEARTBEAT_KEY' => (string) ($spec['heartbeat_key'] ?? 'qp-test-worker-unused'),
    'SESSION_DRIVER' => 'array',
    'QUEUE_CONNECTION' => 'database',
    'LOG_CHANNEL' => 'stderr',
    'GEMINI_API_KEY' => '',
    'OPENAI_API_KEY' => '',
    'DEEPSEEK_API_KEY' => '',
    'ANTHROPIC_API_KEY' => '',
] as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $_SERVER[$key] = $value;
}

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(ConsoleKernel::class);
$kernel->bootstrap();

// Belt and braces: the connection the worker will really use must be the scratch file.
$connection = $app['db']->connection();
if ($connection->getDriverName() !== 'sqlite' || str_replace('\\', '/', (string) $connection->getDatabaseName()) !== $db) {
    fwrite(STDERR, "refusing: the application is not on the scratch database\n");
    exit(4);
}

$app->instance(\App\Services\Documents\DocumentStorageService::class, new FileBackedDocumentStorage((string) $spec['files_dir']));
$app->instance(\App\Services\QuestionPapers\QuestionPaperParser::class, new FixtureQuestionPaperParser((string) $spec['parser_file']));

file_put_contents((string) $spec['ready'], (string) getmypid());

// The production command line, minus --max-jobs/--stop-when-empty: it keeps running and polling.
exit($kernel->call('queue:work', [
    'connection' => 'question_papers',
    '--queue' => 'question-papers',
    '--sleep' => 1,
    '--tries' => 6,
    '--timeout' => 120,
    '--max-time' => 180,
]));
