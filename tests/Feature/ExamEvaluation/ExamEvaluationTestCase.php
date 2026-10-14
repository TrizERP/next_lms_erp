<?php

namespace Tests\Feature\ExamEvaluation;

use App\Services\Evaluation\QuestionLabel;
use App\Services\Evaluation\SheetIdentity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Shared setup for the exam-evaluation feature tests.
 *
 * Everything runs on IN-MEMORY SQLITE, forced here: phpunit.xml does not force
 * it and `.env` points at the shared database, so without this a DB test would
 * hit real data. setUp() refuses to continue unless the active connection
 * really is sqlite. (Run these through the process-level overrides in
 * tests/ai-core-baseline/run-safe-baseline.ps1 as well, so nothing even tries
 * to connect to the real server while the application boots.)
 *
 * The three exam_evaluation_* tables come from the REAL migrations, so a bug in
 * a migration fails a test. The tables around them (students, papers,
 * questions, gradebook) are created with only the columns the module reads.
 */
abstract class ExamEvaluationTestCase extends TestCase
{
    protected const TENANT = 7;
    protected const OTHER_TENANT = 8;
    protected const SYEAR = 2026;
    protected const TEACHER = 55;

    /** @var array<string,string|false> Environment as it was before this test redirected it. */
    private array $savedEnvironment = [];

    private string $bootDatabase = '';

    /**
     * Boots the application against a throwaway sqlite file, never the real DB.
     *
     * Booting the framework runs routes/custom_module.php, which SELECTs from
     * `custom_module_tables` while routes are registered. With `.env` as it is
     * that would be a query against the shared database before this test had a
     * chance to switch connections. Pointing the environment at an empty sqlite
     * file that has just that one table means nothing in this test, boot
     * included, can reach anything but a file in the temp directory.
     */
    public function createApplication()
    {
        $this->bootDatabase = (string) tempnam(sys_get_temp_dir(), 'exam-eval-boot-');
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
            'database.connections.sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            // The guard is exercised in its own tests; here the routes are called directly.
            'api_guard.enforce' => false,
            'logging.default' => 'null',
            'cache.default' => 'array',
        ]);
        DB::purge('sqlite');
        $this->assertSame('sqlite', DB::connection()->getDriverName(), 'refusing to run against a real database');

        foreach ([
            '2026_09_21_000000_create_exam_evaluation_tables',
            '2026_10_13_100000_add_review_fields_to_exam_evaluation',
            '2026_10_13_110000_add_identity_confirmation_to_exam_evaluation_sheet',
            '2026_10_13_120000_add_question_label_to_exam_evaluation_answer',
            '2026_10_07_100000_create_platform_audit_log_table',
        ] as $migration) {
            (require base_path("database/migrations/{$migration}.php"))->up();
        }

        // The schema is rebuilt for every test; what was learned about it must be too.
        SheetIdentity::forgetSchema();
        QuestionLabel::forgetSchema();

        $this->createSurroundingTables();
    }

    protected function createSurroundingTables(): void
    {
        Schema::create('standard', function ($t) {
            $t->integer('id')->primary();
            $t->string('name')->nullable();
        });
        Schema::create('academic_section', function ($t) {
            $t->integer('id')->primary();
            $t->string('title')->nullable();
        });
        Schema::create('subject', function ($t) {
            $t->integer('id')->primary();
            $t->string('subject_name')->nullable();
        });
        Schema::create('division', function ($t) {
            $t->integer('id')->primary();
            $t->string('name')->nullable();
            $t->integer('sub_institute_id')->nullable();
        });
        Schema::create('tblstudent', function ($t) {
            $t->integer('id')->primary();
            $t->string('first_name')->nullable();
            $t->string('middle_name')->nullable();
            $t->string('last_name')->nullable();
            $t->string('enrollment_no')->nullable();
            $t->integer('sub_institute_id');
        });
        Schema::create('tblstudent_enrollment', function ($t) {
            $t->increments('id');
            $t->integer('student_id');
            $t->integer('syear')->nullable();
            $t->integer('standard_id')->nullable();
            $t->integer('grade_id')->nullable();
            $t->integer('section_id')->nullable();
            $t->string('roll_no')->nullable();
            $t->string('drop_code')->nullable();
            $t->integer('term_id')->nullable();
            $t->integer('sub_institute_id');
        });
        Schema::create('question_paper', function ($t) {
            $t->integer('id')->primary();
            $t->integer('grade_id')->nullable();
            $t->integer('standard_id')->nullable();
            $t->integer('subject_id')->nullable();
            $t->string('paper_name')->nullable();
            $t->string('exam_type')->nullable();
            $t->string('question_ids')->nullable();
            $t->integer('total_marks')->nullable();
            $t->integer('sub_institute_id');
            $t->string('syear')->nullable();
        });
        Schema::create('question_type_master', function ($t) {
            $t->integer('id')->primary();
            $t->string('question_type')->nullable();
        });
        Schema::create('lms_question_master', function ($t) {
            $t->integer('id')->primary();
            $t->integer('question_type_id')->nullable();
            $t->text('question_title')->nullable();
            $t->integer('points')->default(1);
            $t->integer('multiple_answer')->default(0);
            $t->longText('answer')->nullable();
            $t->integer('sub_institute_id')->nullable();
        });
        Schema::create('answer_master', function ($t) {
            $t->increments('id');
            $t->integer('question_id');
            $t->string('answer')->nullable();
            $t->integer('correct_answer')->default(0);
        });
        Schema::create('lms_offline_exam', function ($t) {
            $t->increments('id');
            $t->integer('student_id');
            $t->integer('question_paper_id');
            $t->integer('assignment_id')->nullable();
            $t->integer('total_right')->default(0);
            $t->integer('total_wrong')->default(0);
            $t->integer('obtain_marks')->default(0);
            $t->integer('syear')->nullable();
            $t->integer('sub_institute_id');
            $t->integer('created_by')->nullable();
            $t->timestamp('created_at')->nullable();
        });
        Schema::create('lms_offline_exam_answer', function ($t) {
            $t->increments('id');
            $t->integer('question_paper_id');
            $t->integer('offline_exam_id');
            $t->integer('student_id');
            $t->integer('question_id');
            $t->string('ans_status')->nullable();
            $t->integer('created_by')->nullable();
            $t->timestamp('created_at')->nullable();
        });
    }

    /**
     * A three-question paper: an MCQ (2 marks), a written question with a
     * structured rubric (5 marks), and a written question with NO model answer
     * (3 marks). Two students on the class roll, one in each of two divisions.
     */
    protected function seedPaper(): void
    {
        DB::table('standard')->insert(['id' => 1, 'name' => 'Class 7']);
        DB::table('academic_section')->insert(['id' => 1, 'title' => 'Middle']);
        DB::table('subject')->insert(['id' => 1, 'subject_name' => 'Science']);
        DB::table('division')->insert([
            ['id' => 1, 'name' => 'A', 'sub_institute_id' => self::TENANT],
            ['id' => 2, 'name' => 'B', 'sub_institute_id' => self::TENANT],
        ]);

        DB::table('question_paper')->insert([
            'id' => 1, 'grade_id' => 1, 'standard_id' => 1, 'subject_id' => 1,
            'paper_name' => 'Term 1 Science', 'exam_type' => 'term', 'question_ids' => '11,12,13',
            'total_marks' => 10, 'sub_institute_id' => self::TENANT, 'syear' => (string) self::SYEAR,
        ]);

        DB::table('lms_question_master')->insert([
            ['id' => 11, 'question_title' => '<p>Which gas do plants absorb?</p>', 'points' => 2, 'multiple_answer' => 0, 'answer' => null, 'sub_institute_id' => self::TENANT],
            ['id' => 12, 'question_title' => 'Explain photosynthesis.', 'points' => 5, 'multiple_answer' => 0, 'sub_institute_id' => self::TENANT, 'answer' => json_encode([
                'model_answer' => 'Plants make glucose from carbon dioxide and water using light.',
                'marking_points' => [
                    ['mark' => 1, 'criterion' => 'Names carbon dioxide as an input', 'accept' => ['CO2', 'carbon dioxide'], 'reject' => ['oxygen as an input']],
                    ['mark' => 1, 'criterion' => 'Names water as an input', 'accept' => ['water', 'H2O'], 'reject' => []],
                    ['mark' => 1, 'criterion' => 'Light is the energy source', 'accept' => ['sunlight'], 'reject' => []],
                    ['mark' => 1, 'criterion' => 'Glucose is produced', 'accept' => ['sugar'], 'reject' => []],
                    ['mark' => 1, 'criterion' => 'Oxygen is released', 'accept' => ['O2'], 'reject' => []],
                ],
                'keywords' => [['term' => 'chlorophyll', 'weight' => 0.5, 'synonyms' => ['green pigment']]],
                'common_errors' => [['erroneous_answer' => 'Says plants breathe in oxygen to make food', 'mark_ceiling' => 1]],
                'full_credit_threshold' => 4,
            ])],
            ['id' => 13, 'question_title' => 'Define a catalyst.', 'points' => 3, 'multiple_answer' => 0, 'answer' => '', 'sub_institute_id' => self::TENANT],
        ]);

        DB::table('answer_master')->insert([
            ['question_id' => 11, 'answer' => 'Carbon dioxide', 'correct_answer' => 1],
            ['question_id' => 11, 'answer' => 'Oxygen', 'correct_answer' => 0],
        ]);

        DB::table('tblstudent')->insert([
            ['id' => 101, 'first_name' => 'Asha', 'middle_name' => '', 'last_name' => 'Patel', 'enrollment_no' => 'GR101', 'sub_institute_id' => self::TENANT],
            ['id' => 102, 'first_name' => 'Ravi', 'middle_name' => '', 'last_name' => 'Shah', 'enrollment_no' => 'GR102', 'sub_institute_id' => self::TENANT],
            ['id' => 201, 'first_name' => 'Other', 'middle_name' => '', 'last_name' => 'School', 'enrollment_no' => 'GR201', 'sub_institute_id' => self::OTHER_TENANT],
        ]);
        DB::table('tblstudent_enrollment')->insert([
            ['student_id' => 101, 'syear' => self::SYEAR, 'standard_id' => 1, 'grade_id' => 1, 'section_id' => 1, 'roll_no' => '1', 'sub_institute_id' => self::TENANT, 'term_id' => 1],
            // A second term row for the same child: must not make the roster double-count them.
            ['student_id' => 101, 'syear' => self::SYEAR, 'standard_id' => 1, 'grade_id' => 1, 'section_id' => 1, 'roll_no' => '1', 'sub_institute_id' => self::TENANT, 'term_id' => 2],
            ['student_id' => 102, 'syear' => self::SYEAR, 'standard_id' => 1, 'grade_id' => 1, 'section_id' => 2, 'roll_no' => '2', 'sub_institute_id' => self::TENANT, 'term_id' => 1],
        ]);
    }

    protected function makeBatch(array $over = []): int
    {
        return DB::table('exam_evaluation_batch')->insertGetId($over + [
            'sub_institute_id' => self::TENANT, 'syear' => self::SYEAR, 'question_paper_id' => 1,
            'name' => 'Class 7 Science', 'status' => 'Review', 'total_marks' => 10,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * An evaluated sheet with one answer per paper question:
     *   Q1 objective, AI 2/2 (confident)
     *   Q2 written,   AI 3/5, confidence 60  -> flagged for the teacher
     *   Q3 written,   AI 0/3, confidence 0   -> flagged (no model answer)
     *
     * Its student is CONFIRMED by default: a sheet only reaches this stage once a
     * teacher has said whose it is. A test about the unconfirmed case passes
     * `'identity_confirmed_at' => null`.
     */
    protected function makeSheet(int $batchId, array $over = []): int
    {
        $sheetId = DB::table('exam_evaluation_sheet')->insertGetId($over + [
            'batch_id' => $batchId, 'sub_institute_id' => self::TENANT, 'syear' => self::SYEAR,
            'original_name' => 'scan.pdf', 'file_name' => 'scan-' . uniqid() . '.pdf', 'file_type' => 'pdf', 'mime_type' => 'application/pdf',
            'student_id' => 101, 'identity_source' => 'enrollment_no', 'identity_confidence' => 95,
            'identity_confirmed_at' => now(), 'identity_confirmed_by' => self::TEACHER,
            'status' => 'Needs review', 'ai_total' => 5, 'max_marks' => 10, 'percentage' => 50,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $rows = [
            [1, 11, 1, 2, 2, 'correct', 100, 'Matches the answer key.'],
            [2, 12, 0, 5, 3, 'partially_correct', 60, 'Two of five points made.'],
            [3, 13, 0, 3, 0, 'wrong', 0, 'No model answer is saved for this question -- please mark it by hand.'],
        ];

        foreach ($rows as [$no, $questionId, $objective, $max, $ai, $status, $confidence, $remark]) {
            DB::table('exam_evaluation_answer')->insert([
                'sheet_id' => $sheetId, 'batch_id' => $batchId, 'question_id' => $questionId, 'question_no' => $no,
                'question_type' => 'x', 'is_objective' => $objective, 'detected_answer' => 'text', 'max_marks' => $max,
                'ai_marks' => $ai, 'status' => $status, 'ai_confidence' => $confidence, 'ai_remark' => $remark,
                'page' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $sheetId;
    }

    /** @return array<string,mixed> */
    protected function body(array $extra = []): array
    {
        return $extra + ['sub_institute_id' => self::TENANT, 'syear' => self::SYEAR, 'user_id' => self::TEACHER];
    }

    protected function review(int $sheetId, array $extra = [], int $tenant = self::TENANT): \Illuminate\Testing\TestResponse
    {
        return $this->postJson("/api/exam-evaluation/sheets/{$sheetId}/review", array_merge($this->body($extra), ['sub_institute_id' => $tenant]));
    }
}
