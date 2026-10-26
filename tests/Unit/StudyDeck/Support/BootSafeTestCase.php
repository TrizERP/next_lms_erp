<?php

namespace Tests\Unit\StudyDeck\Support;

use Tests\TestCase;

/**
 * A Tests\TestCase that cannot reach the shared database.
 *
 * `.env` points at the shared MySQL and phpunit.xml does not force sqlite, and booting the framework runs
 * routes/custom_module.php, which SELECTs from `custom_module_tables`. So the environment is pointed, before the
 * application boots, at an empty temporary sqlite file holding only that table (and the real host at a refused port).
 * The same boot-safe pattern as tests/Feature/ExamEvaluation/ExamEvaluationTestCase.php, without its tables.
 */
abstract class BootSafeTestCase extends TestCase
{
    /** @var array<string,string|false> */
    private array $savedEnvironment = [];

    private string $bootDatabase = '';

    public function createApplication()
    {
        $this->bootDatabase = (string) tempnam(sys_get_temp_dir(), 'study-deck-boot-');
        $pdo = new \PDO('sqlite:' . $this->bootDatabase);
        $pdo->exec('CREATE TABLE custom_module_tables (id INTEGER PRIMARY KEY, module_name TEXT, access_link TEXT)');
        unset($pdo);

        foreach ([
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => $this->bootDatabase,
            'DB_HOST' => '127.0.0.1',
            'DB_PORT' => '1',
            'REDIS_HOST' => '127.0.0.1',
            'REDIS_PORT' => '1',
            'CACHE_DRIVER' => 'array',
        ] as $key => $value) {
            $this->savedEnvironment[$key] = getenv($key);
            putenv("{$key}={$value}");
            $_ENV[$key] = $_SERVER[$key] = $value;
        }

        return parent::createApplication();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        foreach ($this->savedEnvironment as $key => $previous) {
            if ($previous === false) {
                putenv($key);
                unset($_ENV[$key], $_SERVER[$key]);
            } else {
                putenv("{$key}={$previous}");
                $_ENV[$key] = $_SERVER[$key] = $previous;
            }
        }
        if ($this->bootDatabase !== '' && is_file($this->bootDatabase)) {
            @unlink($this->bootDatabase);
        }
    }
}
