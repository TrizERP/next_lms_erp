<?php

namespace Tests\Feature\CoursePresentation;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Shared setup for the Course Presentation tests.
 *
 * Everything runs on IN-MEMORY SQLITE, forced here (phpunit.xml does not force it and `.env` points at the shared
 * database). The application is booted against a throwaway sqlite file that holds only `custom_module_tables`,
 * the one table the route files read at boot, so nothing in a test, boot included, can reach the real server.
 * setUp() refuses to continue unless the active connection is sqlite. Same approach as ExamEvaluationTestCase.
 *
 * Run with the process-level overrides:
 *   DB_HOST=127.0.0.1 DB_PORT=1 APP_KEY=base64:<32 bytes> php -d memory_limit=-1 vendor/bin/phpunit tests/Feature/CoursePresentation
 */
abstract class CoursePresentationTestCase extends TestCase
{
    protected const TENANT = 1;
    protected const OTHER_TENANT = 2;
    protected const CHAPTER = 8592;
    protected const STANDARD = 42;
    protected const SUBJECT = 3975;

    /** @var array<string,string|false> */
    private array $savedEnvironment = [];
    private string $bootDatabase = '';

    public function createApplication()
    {
        $this->bootDatabase = (string) tempnam(sys_get_temp_dir(), 'course-presentation-boot-');
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
            'GEMINI_API_KEY' => '',
        ] as $key => $value) {
            $this->savedEnvironment[$key] = getenv($key);
            putenv("{$key}={$value}");
            $_ENV[$key] = $_SERVER[$key] = $value;
        }

        return parent::createApplication();
    }

    protected string $runDir = '';

    protected function tearDown(): void
    {
        if ($this->runDir !== '' && is_dir(storage_path($this->runDir))) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(storage_path($this->runDir), \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($it as $p) {
                $p->isDir() ? @rmdir($p->getPathname()) : @unlink($p->getPathname());
            }
            @rmdir(storage_path($this->runDir));
        }
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
            'database.connections.sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'api_guard.enforce' => false,
            'logging.default' => 'null',
            'cache.default' => 'array',
        ]);
        DB::purge('sqlite');
        $this->assertSame('sqlite', DB::connection()->getDriverName(), 'refusing to run against a real database');
        $this->createTables();
        // Draft folders go to a scratch folder of this test run, never beside real drafts or stored decks.
        $this->runDir = 'framework/testing/course-presentation-' . getmypid() . '-' . uniqid();
        config(['course_presentation_pilot.generation.run_dir' => $this->runDir]);
    }

    private function createTables(): void
    {
        $text = 'TEXT';
        // The live database is MySQL, where LIKE treats a backslash as the escape character (the existing queries rely on it: 'study\_deck\_%').
        // SQLite has no default escape, so give this throwaway connection a LIKE with MySQL's behaviour.
        DB::connection()->getPdo()->sqliteCreateFunction('like', function (string $pattern, ?string $value): int {
            $re = '';
            for ($i = 0, $n = strlen($pattern); $i < $n; $i++) {
                $c = $pattern[$i];
                if ($c === '\\' && $i + 1 < $n) {
                    $re .= preg_quote($pattern[++$i], '/');
                } else {
                    $re .= $c === '%' ? '.*' : ($c === '_' ? '.' : preg_quote($c, '/'));
                }
            }

            return (int) preg_match('/^' . $re . '$/is', (string) $value);
        }, 2);
        Schema::create('chapter_master', function ($t) {
            $t->integer('id')->primary();
            $t->string('chapter_name')->nullable();
            $t->integer('standard_id');
            $t->integer('subject_id');
            $t->integer('sub_institute_id');
            $t->integer('extraction_id')->nullable();
        });
        Schema::create('document_extractions', function ($t) {
            $t->integer('id')->primary();
            $t->string('document_type')->nullable();
            $t->integer('chapter_id')->nullable();
            $t->integer('standard_id')->nullable();
            $t->integer('subject_id')->nullable();
            $t->integer('sub_institute_id')->nullable();
            $t->longText('md_content')->nullable();
        });
        Schema::create('lms_learning_outcomes', function ($t) {
            $t->integer('id')->primary();
            $t->integer('curriculum_id');
            $t->integer('extraction_id')->nullable();
            $t->integer('standard_id');
            $t->integer('subject_id');
            $t->integer('chapter_id');
            $t->string('type')->nullable();
            $t->string('code')->nullable();
        });
        Schema::create('lms_curriculum', function ($t) {
            $t->integer('id')->primary();
            $t->integer('extraction_id')->nullable();
            $t->integer('sub_institute_id')->nullable();
            $t->integer('standard_id')->nullable();
            $t->integer('subject_id')->nullable();
            $t->string('curriculum_name')->nullable();
            $t->string('status')->nullable();
        });
        Schema::create('semantic_intelligence', function ($t) {
            $t->integer('id')->primary();
            $t->integer('extraction_id')->nullable();
            $t->integer('sub_institute_id')->nullable();
            $t->integer('standard_id')->nullable();
            $t->integer('subject_id')->nullable();
            $t->integer('chapter_id')->nullable();
            $t->integer('total_concepts')->nullable();
            $t->string('qulity_flag')->nullable();
            $t->longText('full_intelegance_json')->nullable();
        });
        Schema::create('lms_concept', function ($t) {
            $t->integer('id')->primary();
            $t->integer('chapter_id');
            $t->string('name');
            $t->text('description')->nullable();
            $t->text('definition')->nullable();
            $t->integer('topic_id')->nullable();
            $t->integer('concept_show_hide')->nullable();
        });
        Schema::create('topic_master', function ($t) {
            $t->integer('id')->primary();
            $t->integer('chapter_id');
            $t->string('name');
            $t->text('description')->nullable();
            $t->integer('topic_sort_order')->nullable();
            $t->integer('topic_show_hide')->nullable();
        });
        Schema::create('standard', function ($t) {
            $t->integer('id')->primary();
            $t->string('name');
        });
        Schema::create('subject', function ($t) {
            $t->integer('id')->primary();
            $t->string('subject_name');
        });
        Schema::create('lms_concept_intelligence_index', function ($t) {
            $t->increments('id');
            $t->integer('chapter_id')->nullable();
        });
        Schema::create('content_master', function ($t) {
            $t->integer('id')->primary();
            $t->integer('standard_id')->default(0);
            $t->integer('subject_id')->default(0);
            $t->integer('chapter_id')->nullable();
            $t->integer('sub_institute_id')->nullable();
            $t->string('content_category')->nullable();
            $t->string('title')->nullable();
            $t->string('filename')->nullable();
            $t->integer('show_hide')->nullable();
            $t->string('source')->nullable();
        });
        // The study-deck picture store, as in the live schema (picture bytes, one row per school and checksum, and the
        // deck-to-picture links with one row per pair).
        Schema::create('study_deck_images', function ($t) {
            $t->bigIncrements('id');
            $t->integer('sub_institute_id');
            $t->integer('chapter_id')->nullable();
            $t->string('sha256', 64);
            $t->string('mime_type', 32);
            $t->string('format', 8);
            $t->integer('byte_size');
            $t->integer('width');
            $t->integer('height');
            $t->string('source_url_hash', 40)->nullable();
            $t->string('source_url', 2048)->nullable();
            $t->binary('data');
            $t->timestamp('created_at')->nullable();
            $t->unique(['sub_institute_id', 'sha256']);
        });
        Schema::create('study_deck_image_links', function ($t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('image_id');
            $t->integer('content_id');
            $t->integer('chapter_id')->nullable();
            $t->timestamp('created_at')->nullable();
            $t->unique(['image_id', 'content_id']);
        });
        Schema::create('school_setup', function ($t) {
            $t->integer('Id')->primary();
            $t->string('is_Lms')->nullable();
        });
        Schema::create('lms_prayogshala_activity', function ($t) {
            $t->integer('id')->primary();
            $t->integer('chapter_id')->nullable();
            $t->integer('sub_institute_id')->nullable();
            $t->string('status')->nullable();
            $t->timestamp('deleted_at')->nullable();
        });
    }

    /** The pilot chapter plus the stored material a healthy chapter would have. */
    protected function seedPilotChapter(): void
    {
        DB::table('standard')->insert(['id' => self::STANDARD, 'name' => '9']);
        DB::table('subject')->insert(['id' => self::SUBJECT, 'subject_name' => 'Science']);
        DB::table('chapter_master')->insert(['id' => self::CHAPTER, 'chapter_name' => 'Exploration: Entering the World of Secondary Science', 'standard_id' => self::STANDARD, 'subject_id' => self::SUBJECT, 'sub_institute_id' => self::TENANT, 'extraction_id' => 81]);
        DB::table('document_extractions')->insert([
            ['id' => 81, 'document_type' => 'Chapter', 'chapter_id' => self::CHAPTER, 'standard_id' => self::STANDARD, 'subject_id' => self::SUBJECT, 'sub_institute_id' => self::TENANT, 'md_content' => str_repeat('Atoms combine. ', 400)],
            ['id' => 52, 'document_type' => 'Curriculum', 'chapter_id' => null, 'standard_id' => self::STANDARD, 'subject_id' => self::SUBJECT, 'sub_institute_id' => self::TENANT, 'md_content' => str_repeat('Curriculum text. ', 400)],
        ]);
        DB::table('lms_curriculum')->insert(['id' => 82, 'extraction_id' => 52, 'sub_institute_id' => self::TENANT, 'standard_id' => self::STANDARD, 'subject_id' => self::SUBJECT, 'curriculum_name' => 'Science Curriculum', 'status' => 'active']);
        foreach ([[1002, 'learning_outcome', 'LO-39'], [1003, 'learning_outcome', 'LO-40'], [4169, 'competency', 'C1.1']] as [$id, $type, $code]) {
            DB::table('lms_learning_outcomes')->insert(['id' => $id, 'curriculum_id' => 82, 'extraction_id' => 52, 'standard_id' => self::STANDARD, 'subject_id' => self::SUBJECT, 'chapter_id' => self::CHAPTER, 'type' => $type, 'code' => $code]);
        }
        DB::table('lms_concept')->insert([['id' => 1, 'chapter_id' => self::CHAPTER, 'name' => 'Atom'], ['id' => 2, 'chapter_id' => self::CHAPTER, 'name' => 'Ion']]);
        DB::table('semantic_intelligence')->insert(['id' => 13, 'extraction_id' => 81, 'sub_institute_id' => self::TENANT, 'standard_id' => self::STANDARD, 'subject_id' => self::SUBJECT, 'chapter_id' => self::CHAPTER, 'total_concepts' => 1, 'qulity_flag' => 'good',
            'full_intelegance_json' => json_encode(['concepts' => array_map(fn ($n) => ['concept' => ['concept_name' => $n], 'pedagogy_recommendations' => [['strategy' => 'Inquiry Based Teaching']]], ['Atom', 'Mole', 'Bond'])])]);
        DB::table('lms_prayogshala_activity')->insert([
            ['id' => 68, 'chapter_id' => self::CHAPTER, 'sub_institute_id' => self::TENANT, 'status' => 'review'],
            ['id' => 69, 'chapter_id' => self::CHAPTER, 'sub_institute_id' => self::TENANT, 'status' => 'review'],
        ]);
    }

    /**
     * Stands in for api.session (which would verify a JWT) by attaching a session store holding what it would have
     * hydrated. The staff.only middleware still runs for real.
     */
    protected function asTeacher(?int $tenant = self::TENANT, string $profile = 'Teacher')
    {
        TestSession::$values = array_filter(['sub_institute_id' => $tenant, 'user_profile_name' => $profile, 'user_id' => 55], fn ($v) => $v !== null);
        $this->app['router']->aliasMiddleware('api.session', TestSession::class);
        // The content-create gate (lms.auth + perm) verifies a token and the school's permission table; its own
        // tests live elsewhere. Here the routes are asserted to CARRY it, and it is passed through.
        $this->app['router']->aliasMiddleware('lms.auth', PassThrough::class);
        $this->app['router']->aliasMiddleware('perm', PassThrough::class);

        return $this;
    }
}

class TestSession
{
    /** @var array<string,mixed> */
    public static array $values = [];

    public function handle($request, \Closure $next)
    {
        $store = app('session.store');
        $request->setLaravelSession($store);
        foreach (self::$values as $key => $value) {
            $store->put($key, $value);
        }

        return $next($request);
    }
}

class PassThrough
{
    public function handle($request, \Closure $next, ...$params)
    {
        return $next($request);
    }
}
