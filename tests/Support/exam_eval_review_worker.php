<?php

/**
 * One simultaneous request, for ReviewConcurrencyMysqlTest. Not a test itself.
 *
 *   php tests/Support/exam_eval_review_worker.php <spec.json>
 *
 * Boots the real application against the ISOLATED database named in the spec (never `.env`),
 * announces it is ready, waits for the "go" file, then sends one request through the real
 * HTTP kernel -- routes, validation, controller, locks and all -- and writes the status and
 * body to the "out" file. Two of these released together are two requests that really are in
 * flight at once, in two PHP processes, each with its own database connection.
 *
 * Spec: {
 *   "db":      {host, port, user, password, database},
 *   "request": {method, uri, body},
 *   "ready":   path, "go": path, "out": path,
 *   "stall_ms": int   // optional: pause this long after the first read of the sheet, to
 *                     // stand in for a slow network between that read and the write
 * }
 *
 * The queue is faked and no model is reachable: a request that would dispatch a job only
 * records that it did. Refuses to run unless the target is a local `exam_eval_test_*` database.
 */

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

$specPath = $argv[1] ?? '';
$spec = is_file($specPath) ? json_decode((string) file_get_contents($specPath), true) : null;

if (! is_array($spec) || ! isset($spec['db'], $spec['request'], $spec['ready'], $spec['go'], $spec['out'])) {
    fwrite(STDERR, "usage: exam_eval_review_worker.php <spec.json>\n");
    exit(2);
}

$db = $spec['db'];

if (! in_array($db['host'] ?? '', ['127.0.0.1', 'localhost', '::1'], true) || ! preg_match('/^exam_eval_test_[a-z0-9_]{1,12}$/', (string) ($db['database'] ?? ''))) {
    fwrite(STDERR, "refusing: not a local exam_eval_test_* database\n");
    exit(3);
}

// Process-level overrides win over `.env`, so the application cannot reach anything but the isolated server.
foreach ([
    'APP_ENV' => 'testing',
    'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
    'DB_CONNECTION' => 'mysql',
    'DB_HOST' => (string) $db['host'],
    'DB_PORT' => (string) $db['port'],
    'DB_DATABASE' => (string) $db['database'],
    'DB_USERNAME' => (string) $db['user'],
    'DB_PASSWORD' => (string) ($db['password'] ?? ''),
    'REDIS_HOST' => '127.0.0.1',
    'REDIS_PORT' => '1',
    'CACHE_DRIVER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'SESSION_DRIVER' => 'array',
    'GEMINI_API_KEY' => '',
    'OPENAI_API_KEY' => '',
    'OPENROUTER_API_KEY' => '',
    'DEEPSEEK_API_KEY' => '',
    'ANTHROPIC_API_KEY' => '',
] as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $_SERVER[$key] = $value;
}

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

$app = require $root . '/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

config(['api_guard.enforce' => false, 'logging.default' => 'null', 'cache.default' => 'array', 'database.default' => 'mysql']);
DB::purge('mysql');

if (DB::connection()->getDatabaseName() !== $db['database']) {
    fwrite(STDERR, "refusing: connected to the wrong database\n");
    exit(4);
}

Queue::fake();

$stallMs = (int) ($spec['stall_ms'] ?? 0);

if ($stallMs > 0) {
    $fired = false;

    // After the first read of the sheet and before anything is decided: the window the old code
    // validated in, and where a second request could slip in.
    DB::listen(function (QueryExecuted $query) use (&$fired, $stallMs) {
        if (! $fired && str_starts_with($query->sql, 'select') && str_contains($query->sql, '`exam_evaluation_sheet`')) {
            $fired = true;
            usleep($stallMs * 1000);
        }
    });
}

file_put_contents($spec['ready'], (string) getmypid());

$deadline = microtime(true) + 60;

// clearstatcache on every poll, or a "go" file that appears a moment after the first look is not seen.
while (true) {
    clearstatcache(true);

    if (is_file($spec['go'])) {
        break;
    }

    if (microtime(true) > $deadline) {
        fwrite(STDERR, "timed out waiting for go\n");
        exit(5);
    }

    usleep(500);
}

$startedAt = microtime(true);
$body = json_encode($spec['request']['body'] ?? []);
$request = Request::create(
    (string) $spec['request']['uri'],
    (string) ($spec['request']['method'] ?? 'POST'),
    [],
    [],
    [],
    ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
    $body
);

$response = $app->make(HttpKernel::class)->handle($request);

file_put_contents($spec['out'], json_encode([
    'status' => $response->getStatusCode(),
    'body' => json_decode($response->getContent(), true) ?? $response->getContent(),
    'started_at' => $startedAt,
    'finished_at' => microtime(true),
    'dispatched' => collect(['App\\Jobs\\EvaluateAnswerSheetJob', 'App\\Jobs\\IdentifyAnswerSheetJob'])
        ->mapWithKeys(fn (string $job) => [class_basename($job) => count(Queue::pushed($job))])->all(),
]));
