<?php

namespace Tests\Feature\Ceds;

use App\Services\ContentGenerationService;
use GenTux\Jwt\JwtToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CedsFlowFixtures;
use Tests\TestCase;

/**
 * `GET /api/lms/ceds/lesson-flow` through the REAL middleware chain (api.session -> staff.only -> controller),
 * on a throwaway sqlite file with a test-only JWT secret and a FAKE `digitalocean` disk, so nothing here can
 * reach the shared database, the real secret or the real object store.
 *
 * The sidecars are the real shapes (tests/Support/CedsFlowFixtures). Rows and files belonging to hidden older
 * versions and to another school carry marker titles that must never appear in any response.
 */
class CedsLessonFlowRouteTest extends TestCase
{
    private const SECRET = 'ceds-test-only-secret-0123456789abcdef0123456789abcdef';
    private const PLATFORM = 1;
    private const LMS_SCHOOL = 7;
    private const OTHER_SCHOOL = 8;
    private const URL = '/api/lms/ceds/lesson-flow';
    private const METHODS = ['P1', 'P2', 'P3', 'P4', 'P5', 'P6', 'P7', 'P8'];

    /** @var array<string,string|false> */
    private array $savedEnvironment = [];
    private string $bootDatabase = '';

    public function createApplication()
    {
        $this->bootDatabase = (string) tempnam(sys_get_temp_dir(), 'ceds-flow-');
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
        // Booting loads .env and can put the real secret back; the secret is read at call time, so pin the test one.
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
        Storage::fake('digitalocean');
        $this->seedFixtures();
    }

    // ------------------------------------------------------------------------------- wiring

    public function test_the_route_is_registered_read_only_behind_both_gates(): void
    {
        $route = app('router')->getRoutes()->match(app('request')->create(self::URL, 'GET'));
        $this->assertSame(['api', 'api.session', 'staff.only'], $route->gatherMiddleware());
        $this->assertSame(['GET', 'HEAD'], $route->methods());
    }

    public function test_no_token_a_bad_token_a_student_and_a_parent_are_refused(): void
    {
        $this->getJson(self::URL . '?chapter_id=8592')->assertStatus(401);
        $this->withHeader('Authorization', 'Bearer nope')->getJson(self::URL . '?chapter_id=8592')->assertStatus(401);
        $this->asSchool(self::PLATFORM, ['is_student' => true, 'user_profile_id' => 2])->getJson(self::URL . '?chapter_id=8592')->assertStatus(403);
        $this->asSchool(self::PLATFORM, ['user_profile_id' => 3])->getJson(self::URL . '?chapter_id=8592')->assertStatus(403);
    }

    // ------------------------------------------------------------------- topics and the sample

    public function test_topics_are_listed_with_their_names_before_a_method_is_chosen(): void
    {
        $json = $this->teacher()->getJson(self::URL . '?chapter_id=8592')->assertOk()->json();

        $this->assertNull($json['flow'], 'no flow until a method and a topic are chosen');
        $this->assertSame([23809, 23810, 23812], array_column($json['topics'], 'id'));
        $this->assertSame('Scientific Models and Simplification', collect($json['topics'])->firstWhere('id', 23810)['name']);
        $this->assertSame(61401, $json['deck_content_id']);
        $this->assertSame(['enabled' => false, 'adapt_enabled' => false], $json['generation']);
        $this->assertSame(array_map(fn ($c) => $c, array_keys($json['pedagogies'])), self::METHODS);
        $this->assertSame(40, $json['default_period_minutes']);
    }

    public function test_p2_for_the_sample_topic_returns_the_verified_flow(): void
    {
        $json = $this->flow('P2', 23810);
        $flow = $json['flow'];

        $this->assertSame(['Engage', 'Explore', 'Explain', 'Elaborate', 'Evaluate'], array_column($flow['phases'], 'name'));
        $this->assertSame([4, 12, 10, 8, 6], array_column($flow['phases'], 'minutes'));
        $this->assertSame('Scientific Models and Simplification', $json['topic']['name']);

        $by = collect($flow['phases'])->keyBy('name');
        $this->assertSame([2], array_column($by['Engage']['resources'], 'slide'));
        $this->assertSame([7, 8, 10], array_column($by['Explain']['resources'], 'slide'));
        $this->assertSame([9], array_column($by['Elaborate']['resources'], 'slide'));
        $this->assertSame('overrun', $by['Explore']['warnings'][0]['code']);
        $this->assertSame(['activity', 'lab'], array_column($by['Explore']['resources'], 'type'));
        $this->assertSame('Pending Review', $by['Explore']['resources'][1]['review_label']);
        $this->assertSame(61404, $by['Explore']['resources'][0]['content_id']);
    }

    public function test_all_eight_methods_return_their_phases_in_order_with_timings_that_fill_the_period(): void
    {
        $phases = ['P1' => 6, 'P2' => 5, 'P3' => 6, 'P4' => 4, 'P5' => 5, 'P6' => 6, 'P7' => 5, 'P8' => 5];
        foreach (self::METHODS as $method) {
            $flow = $this->flow($method, 23810)['flow'];
            $this->assertSame($method, $flow['pedagogy']);
            $this->assertCount($phases[$method], $flow['phases'], $method);
            $this->assertSame(40, array_sum(array_column($flow['phases'], 'minutes')), $method);
            $this->assertSame(range(1, $phases[$method]), array_column($flow['phases'], 'index'));
        }
    }

    public function test_p5_p6_and_p7_show_their_phases_even_when_nothing_fits(): void
    {
        $p7 = $this->flow('P7', 23810)['flow'];
        $this->assertCount(5, $p7['phases']);
        $this->assertSame([true, true, true, true, true], array_column($p7['phases'], 'teacher_led'));

        $p6 = collect($this->flow('P6', 23810)['flow']['phases']);
        $this->assertSame(0, $p6->where('teacher_led', false)->count(), 'topic 23810 has no misconception slide, so nothing is forced');
        $p6b = collect($this->flow('P6', 23812)['flow']['phases']);
        $this->assertSame(['Surface misconception'], $p6b->where('teacher_led', false)->pluck('name')->all());
    }

    // ------------------------------------------------------------------ the teacher's own timings

    public function test_the_teacher_can_change_the_period_and_every_phase(): void
    {
        $f = $this->teacher()->getJson(self::URL . '?chapter_id=8592&pedagogy=P2&topic_id=23810&period=45')->assertOk()->json('flow');
        $this->assertSame(45, $f['period_minutes']);
        $this->assertSame(45, array_sum(array_column($f['phases'], 'minutes')));

        $f = $this->teacher()->getJson(self::URL . '?chapter_id=8592&pedagogy=P2&topic_id=23810&minutes[]=4&minutes[]=12&minutes[]=6&minutes[]=8&minutes[]=6')->assertOk()->json('flow');
        $explain = collect($f['phases'])->firstWhere('name', 'Explain');
        $this->assertSame(6, $explain['minutes']);
        $this->assertSame(10, $explain['suggested_minutes']);
        $this->assertSame('overrun', $explain['warnings'][0]['code'], 'stored slides (9 min) exceed the 6 the teacher chose');

        $f = $this->teacher()->getJson(self::URL . '?chapter_id=8592&pedagogy=P2&topic_id=23810&minutes[]=4&minutes[]=12&minutes[]=10&minutes[]=8&minutes[]=9')->assertOk()->json('flow');
        $this->assertSame('period_mismatch', $f['warnings'][0]['code']);
    }

    // ------------------------------------------------------------------- missing and partial

    public function test_partial_resources_unreadable_deck_and_labs_still_map_what_exists(): void
    {
        $this->enableChapters(8593);
        // Chapter 8593: its deck row exists but the stored deck file is missing; one lab (in review) exists.
        $json = $this->teacher()->getJson(self::URL . '?chapter_id=8593')->assertOk()->json();
        $this->assertSame([23817], array_column($json['topics'], 'id'), 'the topic still comes from the verified lab');
        $this->assertSame('unreadable', $json['warnings'][0]['code']);

        $flow = $this->flow('P2', 23817, 8593)['flow'];
        $by = collect($flow['phases'])->keyBy('name');
        $this->assertSame('lab', $by['Explore']['resources'][0]['type']);
        $this->assertSame('Pending Review', $by['Explore']['resources'][0]['review_label']);
        $this->assertSame('resource_unreadable', $by['Explain']['teacher_led_reason']);
        $this->assertSame('unreadable', $flow['warnings'][0]['code']);
    }

    public function test_a_chapter_with_nothing_stored_has_no_topics(): void
    {
        $this->enableChapters(8594);
        $json = $this->teacher()->getJson(self::URL . '?chapter_id=8594')->assertOk()->json();
        $this->assertSame([], $json['topics']);
        $this->teacher()->getJson(self::URL . '?chapter_id=8594&pedagogy=P2&topic_id=23810')->assertStatus(404);
    }

    // ---------------------------------------------------------------------------- bad input

    public function test_invalid_topic_method_period_and_minutes_are_rejected(): void
    {
        $base = self::URL . '?chapter_id=8592';
        $this->teacher()->getJson("$base&pedagogy=P2&topic_id=424242")->assertStatus(404);
        $this->teacher()->getJson("$base&pedagogy=P2&topic_id=0")->assertStatus(422);
        $this->teacher()->getJson("$base&pedagogy=P2&topic_id=abc")->assertStatus(422);
        $this->teacher()->getJson("$base&topic_id=23810")->assertStatus(422);
        $this->teacher()->getJson("$base&pedagogy=P9&topic_id=23810")->assertStatus(422);
        $this->teacher()->getJson("$base&pedagogy=P2&topic_id=23810&period=5")->assertStatus(422);
        $this->teacher()->getJson("$base&pedagogy=P2&topic_id=23810&period=500")->assertStatus(422);
        $this->teacher()->getJson("$base&pedagogy=P2&topic_id=23810&minutes[]=10&minutes[]=10")->assertStatus(422);
        $this->teacher()->getJson("$base&pedagogy=P2&topic_id=23810&minutes[]=-1&minutes[]=1&minutes[]=1&minutes[]=1&minutes[]=1")->assertStatus(422);
        $this->teacher()->getJson(self::URL)->assertStatus(422);
    }

    // ----------------------------------------------------------------- school isolation, hidden

    public function test_another_school_cannot_read_the_chapter_or_name_the_platform(): void
    {
        $r = $this->teacher(self::OTHER_SCHOOL)->getJson(self::URL . '?chapter_id=8592&sub_institute_id=1&pedagogy=P2&topic_id=23810');
        $r->assertStatus(404);
        $this->assertArrayNotHasKey('flow', $r->json());
    }

    public function test_no_marker_from_a_hidden_older_version_or_another_school_ever_appears(): void
    {
        foreach ([self::PLATFORM, self::LMS_SCHOOL] as $school) {
            foreach (self::METHODS as $method) {
                foreach ([23809, 23810, 23812] as $topic) {
                    $body = $this->teacher($school)->getJson(self::URL . "?chapter_id=8592&pedagogy=$method&topic_id=$topic")->assertOk()->getContent();
                    foreach (['HIDDEN OLD DECK', 'HIDDEN OLD ACTIVITY', 'OTHER SCHOOL ACTIVITY'] as $marker) {
                        $this->assertStringNotContainsString($marker, $body, "$marker for school $school $method $topic");
                    }
                }
            }
        }
    }

    public function test_an_lms_school_gets_the_platform_resources(): void
    {
        $flow = $this->flow('P2', 23810, 8592, self::LMS_SCHOOL)['flow'];
        $this->assertSame([7, 8, 10], array_column(collect($flow['phases'])->firstWhere('name', 'Explain')['resources'], 'slide'));
    }

    // ------------------------------------------------------------------------ read-only, original

    public function test_the_request_reads_only_and_changes_nothing(): void
    {
        $tables = $this->snapshot();
        $files = Storage::disk('digitalocean')->allFiles();
        $writes = [];
        DB::listen(function ($q) use (&$writes) {
            if (!preg_match('/^\s*select\b/i', $q->sql)) {
                $writes[] = $q->sql;
            }
        });

        $this->flow('P2', 23810);
        $this->flow('P8', 23812);
        $this->teacher()->getJson(self::URL . '?chapter_id=8592')->assertOk();

        $this->assertSame([], $writes);
        $this->assertSame($tables, $this->snapshot());
        $this->assertSame($files, Storage::disk('digitalocean')->allFiles());
    }

    public function test_the_original_picker_endpoint_still_returns_the_deck_that_opens_directly(): void
    {
        $json = $this->teacher()->getJson('/api/lms/ceds/resources?chapter_id=8592&pedagogy=P2')->assertOk()->json();
        $d2 = collect($json['formats'])->firstWhere('format', 'D2');
        $this->assertSame([61401], array_column($d2['items'], 'id'));
        $this->assertSame('study_deck', $d2['items'][0]['kind']);
        $this->assertArrayNotHasKey('flow', $json);
        $d1 = collect($json['formats'])->firstWhere('format', 'D1');
        $this->assertSame([61401, 61402], array_column($d1['items'], 'id'), 'D1 still reuses the Presentation and Revision Notes');
    }

    // ---------------------------------------------------------------------------------- helpers

    private function asSchool(int $school, array $over = []): static
    {
        $claims = $over + ['exp' => time() + 3600, 'id' => 10 + $school, 'sub_institute_id' => $school, 'user_profile_id' => 1, 'client_id' => 1, 'is_admin' => 0, 'is_student' => false];
        $token = (new JwtToken(app(\GenTux\Jwt\Drivers\JwtDriverInterface::class)))->createToken($claims, self::SECRET, 'HS256');

        return $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    private function teacher(int $school = self::PLATFORM): static
    {
        return $this->asSchool($school);
    }

    /** @return array<string,mixed> */
    private function flow(string $method, int $topic, int $chapter = 8592, int $school = self::PLATFORM): array
    {
        return $this->teacher($school)->getJson(self::URL . "?chapter_id=$chapter&pedagogy=$method&topic_id=$topic")->assertOk()->json();
    }

    // ------------------------------------------------------------- only Std 9 Science, Chapter 1

    public function test_other_chapters_are_refused_for_topics_and_for_flows(): void
    {
        $this->flow('P2', 23810)['flow'];
        foreach ([8593, 8594] as $other) {
            foreach (['', '&pedagogy=P2&topic_id=23817'] as $extra) {
                $r = $this->teacher()->getJson(self::URL . "?chapter_id=$other$extra");
                $r->assertStatus(403);
                $this->assertStringContainsString('Std 9 Science, Chapter 1', $r->json('message'));
                $this->assertArrayNotHasKey('topics', $r->json());
                $this->assertArrayNotHasKey('flow', $r->json());
            }
        }
        $this->teacher()->getJson(self::URL . '?chapter_id=999999')->assertStatus(404);
    }

    /** Test-only: switch the picker on for other fixture chapters, to test logic that is not about the scope. */
    private function enableChapters(int ...$ids): void
    {
        config(['ceds_fit_matrix.scope.chapters' => array_map(fn (int $id) => ['chapter_id' => $id, 'standard_id' => 42, 'subject_id' => 3975], $ids)]);
    }

    private function snapshot(): string
    {
        return json_encode([DB::table('content_master')->orderBy('id')->get(), DB::table('lms_prayogshala_activity')->orderBy('id')->get(), DB::table('topic_master')->get()]);
    }

    private function putSidecar(string $path, array $json): void
    {
        Storage::disk('digitalocean')->put($path, json_encode($json));
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
        Schema::create('topic_master', function ($t) {
            $t->integer('id')->primary();
            $t->string('name');
            $t->integer('chapter_id')->nullable();
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
            DB::table('academic_year')->insert(['sub_institute_id' => $school, 'syear' => 2026, 'term_id' => 1, 'start_date' => '2000-01-01', 'end_date' => '2099-12-31']);
        }
        DB::table('tbluserprofilemaster')->insert([['id' => 1, 'name' => 'Teacher'], ['id' => 2, 'name' => 'Student'], ['id' => 3, 'name' => 'Parent']]);
        DB::table('chapter_master')->insert([
            ['id' => 8592, 'chapter_name' => 'Exploration', 'standard_id' => 42, 'subject_id' => 3975, 'sub_institute_id' => self::PLATFORM],
            ['id' => 8593, 'chapter_name' => 'Cell', 'standard_id' => 42, 'subject_id' => 3975, 'sub_institute_id' => self::PLATFORM],
            ['id' => 8594, 'chapter_name' => 'Empty chapter', 'standard_id' => 42, 'subject_id' => 3975, 'sub_institute_id' => self::PLATFORM],
        ]);
        DB::table('topic_master')->insert([
            ['id' => 23809, 'name' => 'Science and its questions', 'chapter_id' => 8592],
            ['id' => 23810, 'name' => 'Scientific Models and Simplification', 'chapter_id' => 8592],
            ['id' => 23812, 'name' => 'Law, theory and principle', 'chapter_id' => 8592],
            ['id' => 23817, 'name' => 'Cell size', 'chapter_id' => 8593],
        ]);

        $c = fn (int $id, int $ch, string $cat, string $type, int $sh, string $file, int $tenant = self::PLATFORM) => [
            'id' => $id, 'chapter_id' => $ch, 'concept_id' => null, 'title' => "row $id", 'filename' => $file, 'file_type' => $type,
            'show_hide' => $sh, 'content_category' => $cat, 'source' => 'Claude AI', 'sub_institute_id' => $tenant, 'created_at' => '2026-10-10',
        ];
        $deckFile = 'study_deck_exploration_4207133.pptx';
        $actFile = 'study_classroom_activity_exploration_all_0123456789ab.pdf';
        $noteFile = 'study_revision_notes_exploration_all_0123456789ef.pdf';
        $hiddenDeck = 'study_deck_exploration_old_999.pptx';
        $hiddenAct = 'study_classroom_activity_exploration_all_aaaaaaaaaaaa.pdf';
        $otherAct = 'study_classroom_activity_exploration_all_bbbbbbbbbbbb.pdf';
        DB::table('content_master')->insert([
            $c(61401, 8592, 'Classroom Presentation', 'pptx', 1, $deckFile),
            $c(61404, 8592, 'Classroom Activity', 'pdf', 1, $actFile),
            $c(61402, 8592, 'Revision Notes', 'pdf', 1, $noteFile),
            $c(60602, 8592, 'Classroom Presentation', 'pptx', 0, $hiddenDeck),                         // hidden older version
            $c(60600, 8592, 'Classroom Activity', 'pdf', 0, $hiddenAct),                               // hidden older version
            $c(99001, 8592, 'Classroom Activity', 'pdf', 1, $otherAct, self::OTHER_SCHOOL),            // another school's, higher id
            $c(61406, 8593, 'Classroom Presentation', 'pptx', 1, 'study_deck_cell_23f14ac59c14.pptx'), // deck row whose file is missing
        ]);

        $deck = fn (string $marker = null) => $marker ? ['concepts' => [['id' => 1, 'topic_id' => 23810]], 'slides' => [['n' => 1, 'slide_type' => 'concept_intro', 'title' => $marker, 'concept_ids' => [1], 'question_ids' => [], 'content' => ['minutes' => 1]]]] : CedsFlowFixtures::deck();
        $this->putSidecar(ContentGenerationService::studyDeckSidecarPath($deckFile), $deck());
        $this->putSidecar(ContentGenerationService::studyDeckSidecarPath($hiddenDeck), $deck('HIDDEN OLD DECK'));
        $this->putSidecar(ContentGenerationService::studyDocumentSidecarPath($actFile), CedsFlowFixtures::activities());
        $this->putSidecar(ContentGenerationService::studyDocumentSidecarPath($noteFile), CedsFlowFixtures::notes());
        $marker = fn (string $m) => ['sections' => [['n' => 1, 'type' => 'activity', 'title' => $m, 'topic_id' => 23810, 'concept_ids' => [7187], 'content' => ['minutes' => 5]]]];
        $this->putSidecar(ContentGenerationService::studyDocumentSidecarPath($hiddenAct), $marker('HIDDEN OLD ACTIVITY'));
        $this->putSidecar(ContentGenerationService::studyDocumentSidecarPath($otherAct), $marker('OTHER SCHOOL ACTIVITY'));

        DB::table('lms_prayogshala_activity')->insert([
            ['id' => 1, 'chapter_id' => 8592, 'topic_id' => 23810, 'concept_id' => 7189, 'title' => 'Same Ball, Different Questions', 'status' => 'review', 'generation_status' => 'ready',
                'show_hide' => 1, 'sort_order' => 1, 'lab_config' => '{"x":1}', 'deleted_at' => null, 'sub_institute_id' => self::PLATFORM],
            ['id' => 5, 'chapter_id' => 8592, 'topic_id' => 23809, 'concept_id' => null, 'title' => 'Compass or Wandering?', 'status' => 'review', 'generation_status' => 'ready',
                'show_hide' => 1, 'sort_order' => 2, 'lab_config' => '{"x":1}', 'deleted_at' => null, 'sub_institute_id' => self::PLATFORM],
            ['id' => 3, 'chapter_id' => 8593, 'topic_id' => 23817, 'concept_id' => 7315, 'title' => 'Estimate the size of a cell', 'status' => 'review', 'generation_status' => 'ready',
                'show_hide' => 1, 'sort_order' => 1, 'lab_config' => '{"x":1}', 'deleted_at' => null, 'sub_institute_id' => self::PLATFORM],
        ]);
    }
}
