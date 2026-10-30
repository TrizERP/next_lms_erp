<?php

/**
 * Safety probe for QuestionPaperDevLauncherTest. Not a test itself.
 *
 *   php tests/Support/dev_launcher_probe.php <scratch sqlite file>
 *
 * Boots the application with the SAME environment overrides the launcher will pass to its server and worker, and prints the
 * database and queue it really resolves to. The test refuses to start the launcher unless that is the scratch file, so a
 * worker can never be pointed at the shared database by a mistake in the overrides.
 */

use Illuminate\Contracts\Console\Kernel;

$db = $argv[1] ?? '';
$tmp = rtrim(str_replace('\\', '/', realpath(sys_get_temp_dir()) ?: sys_get_temp_dir()), '/');
$norm = str_replace('\\', '/', $db);
if (! str_starts_with($norm, $tmp . '/') || ! preg_match('/^qp-dev-[A-Za-z0-9_.-]+\.sqlite$/', basename($norm)) || ! is_file($db)) {
    fwrite(STDERR, "refusing: not a scratch qp-dev-*.sqlite file in the system temp directory\n");
    exit(3);
}

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$connection = $app['db']->connection();
echo json_encode([
    'driver' => $connection->getDriverName(),
    'database' => str_replace('\\', '/', (string) $connection->getDatabaseName()),
    'queue_default' => config('queue.default'),
    'question_papers_queue' => config('queue.connections.question_papers.queue'),
]), PHP_EOL;
