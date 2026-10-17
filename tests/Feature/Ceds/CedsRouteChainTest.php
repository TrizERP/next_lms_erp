<?php

namespace Tests\Feature\Ceds;

use GenTux\Jwt\JwtToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The REAL route (`GET /api/lms/ceds/resources`) through the REAL middleware chain:
 * api.session (bearer JWT verified, session hydrated from its claims) -> staff.only -> controller.
 *
 * Throwaway sqlite file and a TEST-ONLY signing secret, both set before the application
 * boots; nothing here can reach the shared database or the real JWT secret. Tokens are signed
 * by the same JwtToken class the login endpoints use.
 */
class CedsRouteChainTest extends TestCase
{
    protected const SECRET = 'ceds-test-only-secret-0123456789abcdef0123456789abcdef';
    protected const PLATFORM = 1;
    protected const LMS_SCHOOL = 7;
    protected const OTHER_SCHOOL = 8;
    protected const URL = '/api/lms/ceds/resources';

    /** @var array<string,string|false> */
    private array $savedEnvironment = [];
    private string $bootDatabase = '';

    public function createApplication()
    {
        $this->bootDatabase = (string) tempnam(sys_get_temp_dir(), 'ceds-chain-');
        $pdo = new \PDO('sqlite:' . $this->bootDatabase);
        $pdo->exec('CREATE TABLE custom_module_tables (id INTEGER PRIMARY KEY, module_name TEXT, access_link TEXT)');
        unset($pdo);

        foreach ([
            'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $this->bootDatabase, 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '1',
            'REDIS_HOST' => '127.0.0.1', 'REDIS_PORT' => '1', 'SESSION_DRIVER' => 'array', 'CACHE_DRIVER' => 'array',
            'JWT_SECRET' => self::SECRET, 'JWT_ALGO' => 'HS256',
        ] as $key => $value) {
            $this->savedEnvironment[$key] = getenv($key);
            putenv("{$key}={$value}");
            $_ENV[$key] = $_SERVER[$key] = $value;
        }

        $app = parent::createApplication();

        // Booting loads .env, and in a process that has already booted one application that load can put the
        // real JWT_SECRET back. The secret is read at call time, so pin the test-only one again after boot.
        foreach (['JWT_SECRET' => self::SECRET, 'JWT_ALGO' => 'HS256'] as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $_SERVER[$key] = $value;
        }

        return $app;
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
        $this->assertSame('sqlite', DB::connection()->getDriverName(), 'refusing to run against a non-sqlite connection');
        $this->seedFixtures();
    }

    // ----------------------------------------------------------------- authentication chain

    public function test_the_route_is_registered_with_both_gates(): void
    {
        $route = app('router')->getRoutes()->match(app('request')->create(self::URL, 'GET'));
        $this->assertSame('api/lms/ceds/resources', $route->uri());
        $this->assertSame(['api', 'api.session', 'staff.only'], $route->gatherMiddleware());
        $this->assertSame(['GET', 'HEAD'], $route->methods());
    }

    public function test_no_token_is_refused_and_returns_no_data(): void
    {
        $r = $this->getJson(self::URL . '?chapter_id=8592');
        $this->assertContains($r->status(), [401, 403]);
        $this->assertArrayNotHasKey('formats', $r->json() ?? []);
    }

    public function test_a_garbage_token_and_a_token_signed_with_another_secret_are_refused(): void
    {
        $this->withHeader('Authorization', 'Bearer not-a-jwt')->getJson(self::URL . '?chapter_id=8592')->assertStatus(401);

        $forged = (new JwtToken(app(\GenTux\Jwt\Drivers\JwtDriverInterface::class)))
            ->createToken($this->claims(self::PLATFORM, 11), 'a-different-secret-0123456789abcdef0123456789abcdef', 'HS256');
        $this->withHeader('Authorization', 'Bearer ' . $forged)->getJson(self::URL . '?chapter_id=8592')->assertStatus(401);
    }

    public function test_an_expired_token_is_refused(): void
    {
        $this->asToken($this->claims(self::PLATFORM, 11, ['exp' => time() - 3600]))->getJson(self::URL . '?chapter_id=8592')->assertStatus(401);
    }

    public function test_a_student_token_passes_api_session_but_is_refused_by_staff_only(): void
    {
        $r = $this->asToken($this->claims(self::PLATFORM, 21, ['is_student' => true, 'user_profile_id' => 2]))->getJson(self::URL . '?chapter_id=8592');
        $r->assertStatus(403);
        $this->assertArrayNotHasKey('formats', $r->json());
    }

    public function test_a_parent_profile_is_refused_by_staff_only(): void
    {
        $this->asToken($this->claims(self::PLATFORM, 31, ['user_profile_id' => 3]))->getJson(self::URL . '?chapter_id=8592')->assertStatus(403);
    }

    // ---------------------------------------------------------------- the data, per school

    public function test_a_teacher_gets_chapter_8592_with_live_rows_only(): void
    {
        $json = $this->teacher(self::PLATFORM)->getJson(self::URL . '?chapter_id=8592&pedagogy=P2')->assertOk()->json();
        $by = collect($json['formats'])->keyBy('format');

        $this->assertSame([61401], array_column($by['D2']['items'], 'id'));
        $this->assertSame(1, $by['D2']['hidden_ignored']);
        $this->assertSame('pending_review', $by['D4']['status']);
        $this->assertSame('Pending Review', $by['D4']['items'][0]['review_label']);
        $this->assertSame('partial', $by['D1']['status'], 'this fixture has the deck but no revision notes');
        $this->assertSame([61401], array_column($by['D1']['items'], 'id'));
        $this->assertSame(['enabled' => false, 'adapt_enabled' => false], $json['generation']);
        $ids = collect($json['formats'])->pluck('items')->flatten(1)->pluck('id')->all();
        foreach ([60602, 61406, 99001] as $never) {
            $this->assertNotContains($never, $ids);
        }
    }

    public function test_chapter_8593_is_separate(): void
    {
        $this->enableChapters(8593);
        $json = $this->teacher(self::PLATFORM)->getJson(self::URL . '?chapter_id=8593')->assertOk()->json();
        $by = collect($json['formats'])->keyBy('format');
        $this->assertSame([61406], array_column($by['D2']['items'], 'id'));
        $this->assertSame([3], array_column($by['D4']['items'], 'id'));
    }

    public function test_another_school_cannot_see_the_platform_chapter_even_if_it_names_the_platform(): void
    {
        $r = $this->teacher(self::OTHER_SCHOOL)->getJson(self::URL . '?chapter_id=8592&sub_institute_id=1');
        $r->assertStatus(404);
        $this->assertArrayNotHasKey('formats', $r->json());
    }

    public function test_an_lms_school_reads_platform_plus_own_rows_but_not_another_schools(): void
    {
        $json = $this->teacher(self::LMS_SCHOOL)->getJson(self::URL . '?chapter_id=8592')->assertOk()->json();
        $ids = collect($json['formats'])->pluck('items')->flatten(1)->pluck('id')->all();
        $this->assertContains(61401, $ids);
        $this->assertContains(99002, $ids);
        $this->assertNotContains(99001, $ids);
    }

    public function test_the_whole_chain_reads_only(): void
    {
        $this->enableChapters(8592, 8593); // before the first request: the route keeps its controller (and its scope) after that
        $before = $this->snapshot();
        $writes = [];
        DB::listen(function ($q) use (&$writes) {
            if (!preg_match('/^\s*select\b/i', $q->sql)) {
                $writes[] = $q->sql;
            }
        });

        $this->teacher(self::PLATFORM)->getJson(self::URL . '?chapter_id=8592&pedagogy=P1')->assertOk();
        $this->teacher(self::LMS_SCHOOL)->getJson(self::URL . '?chapter_id=8593')->assertOk();

        $this->assertSame([], $writes);
        $this->assertSame($before, $this->snapshot());
    }

    // ---------------------------------------------------------------------------- helpers

    /** @return array<string,mixed> */
    protected function claims(int $school, int $user, array $over = []): array
    {
        return $over + ['exp' => time() + 3600, 'id' => $user, 'sub_institute_id' => $school, 'user_profile_id' => 1, 'client_id' => 1, 'is_admin' => 0, 'is_student' => false];
    }

    protected function asToken(array $claims): static
    {
        $token = (new JwtToken(app(\GenTux\Jwt\Drivers\JwtDriverInterface::class)))->createToken($claims, self::SECRET, 'HS256');

        return $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    protected function teacher(int $school): static
    {
        return $this->asToken($this->claims($school, 10 + $school));
    }

    // ------------------------------------------------------------- only Std 9 Science, Chapter 1

    public function test_through_the_real_chain_only_chapter_1_is_enabled(): void
    {
        $this->teacher(self::PLATFORM)->getJson(self::URL . '?chapter_id=8592')->assertOk();
        foreach ([self::PLATFORM, self::LMS_SCHOOL] as $school) {
            $r = $this->teacher($school)->getJson(self::URL . '?chapter_id=8593');
            $r->assertStatus(403);
            $this->assertArrayNotHasKey('formats', $r->json());
        }
        // A school that cannot see the chapter still gets 404 before the scope is even considered.
        $this->teacher(self::OTHER_SCHOOL)->getJson(self::URL . '?chapter_id=8593')->assertStatus(404);
    }

    /** Test-only: switch the picker on for other fixture chapters, to test logic that is not about the scope. */
    private function enableChapters(int ...$ids): void
    {
        config(['ceds_fit_matrix.scope.chapters' => array_map(fn (int $id) => ['chapter_id' => $id, 'standard_id' => 42, 'subject_id' => 3975], $ids)]);
    }

    private function snapshot(): string
    {
        return json_encode([DB::table('content_master')->orderBy('id')->get(), DB::table('lms_prayogshala_activity')->orderBy('id')->get()]);
    }

    private function seedFixtures(): void
    {
        Schema::create('school_setup', function ($t) {
            $t->integer('Id')->primary();
            $t->string('is_Lms')->nullable();
            $t->string('institute_type')->nullable();
            $t->string('ShortCode')->nullable();
            $t->string('SchoolName')->nullable();
        });
        Schema::create('academic_year', function ($t) {
            $t->increments('id');
            $t->integer('sub_institute_id');
            $t->integer('syear');
            $t->integer('term_id');
            $t->integer('sort_order')->default(0);
            $t->string('start_date');
            $t->string('end_date');
        });
        Schema::create('tbluserprofilemaster', function ($t) {
            $t->integer('id')->primary();
            $t->string('name');
            $t->integer('parent_id')->nullable();
        });
        Schema::create('tbluser', function ($t) {
            $t->integer('id')->primary();
            $t->string('user_name')->nullable();
            $t->string('first_name')->nullable();
            $t->string('last_name')->nullable();
            $t->string('email')->nullable();
        });
        Schema::create('class_teacher', function ($t) {
            $t->integer('teacher_id');
            $t->integer('sub_institute_id');
            $t->integer('syear');
            $t->integer('grade_id')->nullable();
            $t->integer('standard_id')->nullable();
            $t->integer('division_id')->nullable();
        });
        Schema::create('chapter_master', function ($t) {
            $t->integer('id')->primary();
            $t->string('chapter_name');
            $t->integer('standard_id');
            $t->integer('subject_id');
            $t->integer('sub_institute_id');
        });
        Schema::create('content_master', function ($t) {
            $t->integer('id')->primary();
            $t->integer('chapter_id')->nullable();
            $t->integer('concept_id')->nullable();
            $t->string('title')->nullable();
            $t->text('filename')->nullable();
            $t->string('file_type')->nullable();
            $t->integer('show_hide')->nullable();
            $t->string('content_category')->nullable();
            $t->string('source')->nullable();
            $t->integer('sub_institute_id')->nullable();
            $t->string('created_at')->nullable();
        });
        Schema::create('lms_prayogshala_activity', function ($t) {
            $t->integer('id')->primary();
            $t->integer('chapter_id');
            $t->integer('topic_id')->nullable();
            $t->integer('concept_id')->nullable();
            $t->string('title');
            $t->string('status');
            $t->string('generation_status')->nullable();
            $t->integer('show_hide')->default(1);
            $t->integer('sort_order')->default(0);
            $t->longText('lab_config')->nullable();
            $t->string('deleted_at')->nullable();
            $t->integer('sub_institute_id');
        });

        DB::table('school_setup')->insert([
            ['Id' => self::PLATFORM, 'is_Lms' => 'N'], ['Id' => self::LMS_SCHOOL, 'is_Lms' => 'Y'], ['Id' => self::OTHER_SCHOOL, 'is_Lms' => 'N'],
        ]);
        foreach ([self::PLATFORM, self::LMS_SCHOOL, self::OTHER_SCHOOL] as $school) {
            DB::table('academic_year')->insert(['sub_institute_id' => $school, 'syear' => 2026, 'term_id' => 1,
                'start_date' => '2000-01-01', 'end_date' => '2099-12-31']);
        }
        DB::table('tbluserprofilemaster')->insert([['id' => 1, 'name' => 'Teacher'], ['id' => 2, 'name' => 'Student'], ['id' => 3, 'name' => 'Parent']]);
        DB::table('chapter_master')->insert([
            ['id' => 8592, 'chapter_name' => 'Exploration', 'standard_id' => 42, 'subject_id' => 3975, 'sub_institute_id' => self::PLATFORM],
            ['id' => 8593, 'chapter_name' => 'Cell', 'standard_id' => 42, 'subject_id' => 3975, 'sub_institute_id' => self::PLATFORM],
        ]);

        $c = fn (int $id, int $ch, string $cat, string $type, int $sh, string $file, int $tenant = self::PLATFORM) => [
            'id' => $id, 'chapter_id' => $ch, 'concept_id' => null, 'title' => "row $id", 'filename' => $file, 'file_type' => $type,
            'show_hide' => $sh, 'content_category' => $cat, 'source' => 'Claude AI', 'sub_institute_id' => $tenant, 'created_at' => '2026-10-10',
        ];
        DB::table('content_master')->insert([
            $c(61401, 8592, 'Classroom Presentation', 'pptx', 1, 'study_deck_exploration_4207133.pptx'),
            $c(60602, 8592, 'Classroom Presentation', 'pdf', 0, 'presentation_exploration_17887'),
            $c(61406, 8593, 'Classroom Presentation', 'pptx', 1, 'study_deck_cell_the_building_block_of_life_23f14ac59c14.pptx'),
            $c(99001, 8592, 'Revision Notes', 'pdf', 1, 'study_revision_notes_x_all_aaaaaaaaaaaa.pdf', self::OTHER_SCHOOL),
            $c(99002, 8592, 'Revision Notes', 'pdf', 1, 'study_revision_notes_x_all_bbbbbbbbbbbb.pdf', self::LMS_SCHOOL),
        ]);
        $lab = fn (int $id, int $ch) => ['id' => $id, 'chapter_id' => $ch, 'topic_id' => 1000 + $id, 'concept_id' => null, 'title' => "lab $id",
            'status' => 'review', 'generation_status' => 'ready', 'show_hide' => 1, 'sort_order' => $id, 'lab_config' => '{"x":1}',
            'deleted_at' => null, 'sub_institute_id' => self::PLATFORM];
        DB::table('lms_prayogshala_activity')->insert([$lab(1, 8592), $lab(3, 8593)]);
    }
}
