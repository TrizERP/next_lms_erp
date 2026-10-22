<?php

namespace Tests\Feature\ExamEvaluation\IsolatedMysql;

use Illuminate\Support\Facades\DB;

/**
 * Lets a test run against a THROWAWAY MySQL/MariaDB -- opt-in, and never the shared one.
 *
 * SQLite has no row locks and one connection, so it cannot show how migrations behave on
 * MySQL or how two simultaneous requests really contend. These tests can, but only when
 * pointed at a server you started for the purpose. Nothing is attempted unless ALL of:
 *
 *   EXAM_EVAL_ISOLATED_MYSQL_HOST       127.0.0.1 or localhost -- anything else is refused
 *   EXAM_EVAL_ISOLATED_MYSQL_PORT       e.g. 3399
 *   EXAM_EVAL_ISOLATED_MYSQL_USER       a user that may CREATE and DROP DATABASE
 *   EXAM_EVAL_ISOLATED_MYSQL_PASSWORD   may be empty
 *
 * are set. The database used is created fresh with a name of this trait's own choosing
 * (`exam_eval_test_<suffix>`), never one supplied by the environment, and is dropped
 * afterwards; the server's other databases are never read or written. The application
 * database settings (`.env`, the shared server) are never consulted.
 *
 * Example, with a scratch server on port 3399:
 *   EXAM_EVAL_ISOLATED_MYSQL_HOST=127.0.0.1 EXAM_EVAL_ISOLATED_MYSQL_PORT=3399 \
 *   EXAM_EVAL_ISOLATED_MYSQL_USER=root EXAM_EVAL_ISOLATED_MYSQL_PASSWORD= \
 *   php vendor/bin/phpunit tests/Feature/ExamEvaluation/IsolatedMysql
 */
trait UsesIsolatedMysql
{
    private const ISOLATED_CONNECTION = 'exam_eval_isolated_mysql';

    private ?\PDO $isolatedAdmin = null;

    private string $isolatedDatabase = '';

    private ?string $previousDefaultConnection = null;

    /** @var array<string,string> */
    protected array $isolatedServer = [];

    /**
     * Creates the throwaway database and makes it the application's default connection.
     * Call after parent::setUp(); skips the test when the opt-in is not there.
     */
    protected function useIsolatedMysql(string $suffix): void
    {
        $host = (string) getenv('EXAM_EVAL_ISOLATED_MYSQL_HOST');
        $port = (string) getenv('EXAM_EVAL_ISOLATED_MYSQL_PORT');
        $user = (string) getenv('EXAM_EVAL_ISOLATED_MYSQL_USER');
        $password = (string) getenv('EXAM_EVAL_ISOLATED_MYSQL_PASSWORD');

        if ($host === '' || $port === '' || $user === '') {
            $this->markTestSkipped('Opt-in: needs a throwaway local MySQL/MariaDB (see UsesIsolatedMysql). It never runs against the shared database.');
        }

        $this->assertContains($host, ['127.0.0.1', 'localhost', '::1'], 'the isolated MySQL must be on this machine');
        $this->assertMatchesRegularExpression('/^\d{2,5}$/', $port);
        $this->assertMatchesRegularExpression('/^[a-z0-9_]{1,12}$/', $suffix);

        $this->isolatedDatabase = 'exam_eval_test_' . $suffix;
        $this->isolatedServer = ['host' => $host, 'port' => $port, 'user' => $user, 'password' => $password, 'database' => $this->isolatedDatabase];

        try {
            $this->isolatedAdmin = new \PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $password, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_TIMEOUT => 5]);
        } catch (\PDOException $exception) {
            $this->markTestSkipped('The isolated MySQL is not reachable: ' . $exception->getMessage());
        }

        $this->isolatedAdmin->exec("DROP DATABASE IF EXISTS `{$this->isolatedDatabase}`");
        $this->isolatedAdmin->exec("CREATE DATABASE `{$this->isolatedDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        config(['database.connections.' . self::ISOLATED_CONNECTION => [
            'driver' => 'mysql',
            'host' => $host,
            'port' => $port,
            'database' => $this->isolatedDatabase,
            'username' => $user,
            'password' => $password,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => 'InnoDB',
        ]]);

        $this->previousDefaultConnection = (string) config('database.default');
        config(['database.default' => self::ISOLATED_CONNECTION]);
        DB::purge(self::ISOLATED_CONNECTION);

        $this->assertSame($this->isolatedDatabase, DB::connection()->getDatabaseName(), 'refusing to continue: not on the throwaway database');
        $this->assertSame('mysql', DB::connection()->getDriverName());
    }

    /** Puts the default connection back and drops the throwaway database. Call from tearDown(), before the parent's. */
    protected function dropIsolatedMysql(): void
    {
        if ($this->isolatedAdmin === null) {
            return;
        }

        DB::purge(self::ISOLATED_CONNECTION);

        if ($this->previousDefaultConnection !== null) {
            config(['database.default' => $this->previousDefaultConnection]);
        }

        $this->isolatedAdmin->exec("DROP DATABASE IF EXISTS `{$this->isolatedDatabase}`");
        $this->isolatedAdmin = null;
    }

    /** @return array<string,mixed>|null One row as an associative array, from the throwaway database. */
    protected function isolatedRow(string $sql, array $bindings = []): ?array
    {
        $row = DB::selectOne($sql, $bindings);

        return $row === null ? null : (array) $row;
    }
}
