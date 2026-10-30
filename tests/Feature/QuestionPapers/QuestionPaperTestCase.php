<?php

namespace Tests\Feature\QuestionPapers;

use App\Services\Documents\DocumentStorageService;
use App\Services\QuestionPapers\QuestionPaperParser;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Shared setup for the Question Paper feature tests.
 *
 * Everything runs on a throwaway sqlite file and in-memory sqlite, forced here: `.env` points at the
 * shared database, so setUp() refuses to continue unless the active connection is sqlite. The three
 * new tables come from the REAL migrations; the existing tables around them (document_master,
 * standard, subject, chapter_master, lms_question_master, answer_master) are created with the
 * columns this feature reads or writes, taken from the live schema (SHOW COLUMNS, read-only).
 *
 * No real AI provider, queue or storage is touched: the parser and storage are replaced in the
 * container, and the queue is whatever the test fakes.
 */
abstract class QuestionPaperTestCase extends TestCase
{
    protected const TENANT = 7;
    protected const OTHER_TENANT = 8;
    protected const OWNER = 55;
    protected const COLLEAGUE = 56;

    /** @var array<string,string|false> */
    private array $savedEnvironment = [];

    private string $bootDatabase = '';

    /** Bytes the fake storage returns, keyed by storage path. */
    public array $files = [];

    /** Folder a separate worker process reads uploaded PDFs from (null: in-process fake only). */
    public ?string $fileDir = null;

    public function createApplication()
    {
        $this->bootDatabase = (string) tempnam(sys_get_temp_dir(), 'qp-boot-');
        $pdo = new \PDO('sqlite:' . $this->bootDatabase);
        $pdo->exec('CREATE TABLE custom_module_tables (id INTEGER PRIMARY KEY, module_name TEXT, access_link TEXT)');
        unset($pdo);

        foreach ([
            'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $this->bootDatabase, 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '1',
            'REDIS_HOST' => '127.0.0.1', 'REDIS_PORT' => '1', 'GEMINI_API_KEY' => '',
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

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => ['driver' => 'sqlite', 'database' => $this->sqliteDatabase(), 'prefix' => ''],
            'logging.default' => 'null',
            'cache.default' => 'array',
            'queue.default' => 'database', // never sync: the feature refuses to run inline
        ]);
        DB::purge('sqlite');

        // Opt-in: run the same tests on a THROWAWAY local MariaDB/MySQL (QP_MYSQL_PORT set), because MySQL
        // differs from sqlite where it matters here (rows "changed" vs matched, generated columns, locks).
        // Refuses any non-local host, and rebuilds every table, so it can only ever wipe the scratch server.
        if (getenv('QP_MYSQL_PORT')) {
            $host = getenv('QP_MYSQL_HOST') ?: '127.0.0.1';
            $this->assertContains($host, ['127.0.0.1', 'localhost'], 'the isolated MySQL must be local');
            config([
                'database.default' => 'qp_mysql',
                'database.connections.qp_mysql' => [
                    'driver' => 'mysql', 'host' => $host, 'port' => (int) getenv('QP_MYSQL_PORT'),
                    'database' => getenv('QP_MYSQL_DATABASE') ?: 'qp_test', 'username' => getenv('QP_MYSQL_USER') ?: 'root',
                    'password' => getenv('QP_MYSQL_PASSWORD') ?: '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '',
                ],
            ]);
            // Fresh scratch database per test, made over a direct connection (the app's own SQL guard
            // rightly blocks DROP TABLE, and this must not go through the application's connection).
            $name = preg_replace('/[^a-z0-9_]/i', '', getenv('QP_MYSQL_DATABASE') ?: 'qp_test');
            $admin = new \PDO("mysql:host={$host};port=" . (int) getenv('QP_MYSQL_PORT'), getenv('QP_MYSQL_USER') ?: 'root', getenv('QP_MYSQL_PASSWORD') ?: '');
            $admin->exec("DROP DATABASE IF EXISTS `{$name}`");
            $admin->exec("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4");
            unset($admin);
            DB::purge('qp_mysql');
            $this->assertSame('mysql', DB::connection()->getDriverName());
        } else {
            $this->assertSame('sqlite', DB::connection()->getDriverName(), 'refusing to run against a real database');
        }

        foreach ([
            '2026_10_28_100000_create_question_paper_jobs_tables',
            '2026_10_30_140000_add_scheduled_at_to_question_paper_jobs',
            '2026_10_30_160000_scope_question_paper_items_to_extraction_occurrences',
            '2026_10_28_100100_create_user_notifications_table',
        ] as $migration) {
            (require base_path("database/migrations/{$migration}.php"))->up();
        }

        $this->createSurroundingTables();
        $this->bindFakeStorage();
    }

    /**
     * The sqlite database the test runs on. In memory by default; a test that needs a second PROCESS (a real
     * queue worker) overrides this with the path of a disposable file, which this class never creates anywhere
     * but the system temp directory.
     */
    protected function sqliteDatabase(): string
    {
        return ':memory:';
    }

    protected function createSurroundingTables(): void
    {
        Schema::create('document_master', function ($t) {
            $t->bigIncrements('id');
            $t->integer('sub_institute_id')->nullable();
            $t->string('title')->nullable();
            $t->string('original_file_name')->nullable();
            $t->string('mime_type')->nullable();
            $t->integer('size')->nullable();
            $t->string('checksum_sha256')->nullable();
            $t->string('storage_path')->nullable();
            $t->string('document_type')->nullable();
            $t->string('declared_type', 24)->nullable();
            $t->integer('current_version')->nullable();
            $t->integer('department_id')->nullable();
            $t->integer('owner_id')->nullable();
            $t->integer('created_by')->nullable();
            $t->string('visibility')->nullable();
            $t->text('view_principals')->nullable();
            $t->text('permissions')->nullable();
            $t->string('processing_status')->nullable();
            $t->timestamp('deleted_at')->nullable();
            $t->timestamps();
        });
        (require base_path('database/migrations/2026_10_02_150001_create_document_history_table.php'))->up();
        Schema::create('standard', function ($t) {
            $t->integer('id')->primary();
            $t->string('name')->nullable();
            $t->integer('sort_order')->nullable();
            $t->integer('sub_institute_id')->nullable();
        });
        Schema::create('subject', function ($t) {
            $t->integer('id')->primary();
            $t->string('subject_name')->nullable();
            $t->integer('sub_institute_id')->nullable();
        });
        Schema::create('chapter_master', function ($t) {
            $t->integer('id')->primary();
            $t->integer('standard_id')->nullable();
            $t->integer('subject_id')->nullable();
            $t->string('chapter_name')->nullable();
            $t->integer('sort_order')->nullable();
            $t->integer('sub_institute_id')->nullable();
        });
        Schema::create('lms_question_master', function ($t) {
            $t->bigIncrements('id');
            $t->integer('question_type_id')->nullable();
            $t->integer('standard_id')->nullable();
            $t->integer('subject_id')->nullable();
            $t->integer('chapter_id')->nullable();
            $t->longText('question_title')->nullable();
            $t->string('description', 250)->nullable();
            $t->integer('points')->default(1);
            $t->integer('multiple_answer')->nullable();
            $t->integer('sub_institute_id')->nullable();
            $t->integer('status')->nullable();
            $t->integer('created_by')->nullable();
            $t->timestamp('created_on')->nullable();
            $t->longText('answer')->nullable();
            $t->longText('hint_text')->nullable();
            $t->string('question_format_code', 48)->nullable();
            // MySQL derives this from answer.content_hash. On sqlite it is a plain column filled by a trigger below.
            if (DB::connection()->getDriverName() === 'mysql') {
                $t->char('g_content_hash', 64)->nullable()->storedAs("JSON_UNQUOTE(JSON_EXTRACT(`answer`, '$.content_hash'))");
            } else {
                $t->char('g_content_hash', 64)->nullable();
            }
            $t->timestamp('deleted_at')->nullable();
        });
        // MySQL derives g_content_hash from answer.content_hash; mimic that so duplicate detection is real.
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement("CREATE TRIGGER qm_hash AFTER INSERT ON lms_question_master BEGIN UPDATE lms_question_master SET g_content_hash = json_extract(NEW.answer, '\$.content_hash') WHERE id = NEW.id; END");
        }
        Schema::create('sub_std_map', function ($t) {
            $t->increments('id');
            $t->integer('subject_id');
            $t->integer('standard_id');
            $t->integer('status')->nullable();
            $t->timestamp('deleted_at')->nullable();
        });
        Schema::create('answer_master', function ($t) {
            $t->increments('id');
            $t->integer('question_id');
            $t->string('answer', 250)->nullable();
            $t->string('feedback', 250)->nullable();
            $t->integer('correct_answer')->nullable();
            $t->integer('sub_institute_id')->nullable();
            $t->integer('created_by')->nullable();
            $t->timestamp('created_on')->nullable();
        });

        DB::table('standard')->insert([['id' => 9, 'name' => 'Class 9', 'sub_institute_id' => self::TENANT], ['id' => 19, 'name' => 'Class 9', 'sub_institute_id' => self::OTHER_TENANT]]);
        DB::table('subject')->insert([['id' => 3, 'subject_name' => 'Science', 'sub_institute_id' => self::TENANT], ['id' => 13, 'subject_name' => 'Science', 'sub_institute_id' => self::OTHER_TENANT]]);
        DB::table('sub_std_map')->insert(['subject_id' => 3, 'standard_id' => 9, 'status' => 1]);
        DB::table('chapter_master')->insert([
            ['id' => 101, 'standard_id' => 9, 'subject_id' => 3, 'chapter_name' => 'Matter in Our Surroundings', 'sub_institute_id' => self::TENANT],
            ['id' => 201, 'standard_id' => 19, 'subject_id' => 13, 'chapter_name' => 'Matter in Our Surroundings', 'sub_institute_id' => self::OTHER_TENANT],
        ]);
    }

    /** Storage that serves $this->files and never touches Spaces. */
    protected function bindFakeStorage(): void
    {
        $test = $this;
        $this->app->instance(DocumentStorageService::class, new class($test) extends DocumentStorageService {
            public function __construct(private object $test)
            {
            }

            public function get(string $path): string
            {
                return $this->test->files[$path] ?? throw new \RuntimeException("No fake file at {$path}");
            }

            public function readStream(string $path)
            {
                $contents = $this->test->files[$path] ?? throw new \RuntimeException("No fake file at {$path}");
                $stream = fopen('php://temp', 'w+b');
                fwrite($stream, $contents);
                rewind($stream);

                return $stream;
            }

            public function storeUpload(UploadedFile $file): array
            {
                $path = 'documents/test/' . uniqid() . '.pdf';
                $this->test->files[$path] = (string) file_get_contents($file->getRealPath());
                // When a separate worker process reads the file, it is written through to the shared scratch folder
                // BEFORE the upload returns (and so before the job is queued), like Spaces would be.
                if ($this->test->fileDir !== null) {
                    file_put_contents($this->test->fileDir . DIRECTORY_SEPARATOR . basename($path), $this->test->files[$path]);
                }

                return [
                    'storage_path' => $path,
                    'checksum_sha256' => hash('sha256', $this->test->files[$path]),
                    'size' => strlen($this->test->files[$path]),
                    'mime_type' => 'application/pdf',
                    'original_file_name' => $file->getClientOriginalName(),
                ];
            }
        });
    }

    /** Replace the AI parser with scripted answers: one entry per parseChunk() call. */
    protected function fakeParser(array $responses): object
    {
        $fake = new class($responses) extends QuestionPaperParser {
            public int $calls = 0;

            public function __construct(public array $responses)
            {
            }

            public function parseChunk(array $pages, int $tenant): array
            {
                $next = $this->responses[$this->calls++] ?? ['header' => [], 'questions' => []];
                if ($next instanceof \Throwable) {
                    throw $next;
                }

                return $next;
            }
        };
        $this->app->instance(QuestionPaperParser::class, $fake);

        return $fake;
    }

    /** Stores a PDF in the fake storage, creates its document and queued job; returns the job row. */
    protected function makeJob(array $pages = null, array $academic = [], int $tenant = self::TENANT, int $user = self::OWNER, ?string $bytes = null): object
    {
        $pages ??= ['Class 9 Science. Matter in Our Surroundings. Answer all questions in the space provided below.'];
        $path = 'documents/test/' . uniqid() . '.pdf';
        $this->files[$path] = $bytes ?? TinyPdf::make($pages);
        if ($this->fileDir !== null) {
            file_put_contents($this->fileDir . DIRECTORY_SEPARATOR . basename($path), $this->files[$path]);
        }

        $docId = DB::table('document_master')->insertGetId([
            'sub_institute_id' => $tenant, 'title' => 'Science paper', 'original_file_name' => 'Science paper.pdf',
            'storage_path' => $path, 'owner_id' => $user, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return app(\App\Services\QuestionPapers\QuestionPaperJobService::class)->create($docId, $tenant, $user, $academic);
    }

    protected function process(object $job): void
    {
        app(\App\Services\QuestionPapers\QuestionPaperProcessor::class)->run($job->uuid);
    }

    protected function job(object $job): object
    {
        return DB::table('question_paper_jobs')->where('id', $job->id)->first();
    }

    /** A Request as lms.auth would leave it for a verified token. */
    protected function authed(string $method, array $params = [], int $tenant = self::TENANT, int $user = self::OWNER, int $isAdmin = 0): Request
    {
        $request = Request::create('/api/platform/question-papers', $method, $params);
        $request->attributes->set('lms_auth', ['user_id' => $user, 'sub_institute_id' => $tenant, 'is_admin' => $isAdmin, 'is_student' => false]);

        return $request;
    }

    protected function mcq(string $text, ?string $correct, int $page = 1): array
    {
        return [
            'item_number' => '1', 'page' => $page, 'format' => 'mcq', 'question_text' => $text,
            'options' => [['label' => 'A', 'text' => 'Solid'], ['label' => 'B', 'text' => 'Liquid'], ['label' => 'C', 'text' => 'Gas'], ['label' => 'D', 'text' => 'Plasma']],
            'correct_option' => $correct, 'marks' => 1,
        ];
    }
}
