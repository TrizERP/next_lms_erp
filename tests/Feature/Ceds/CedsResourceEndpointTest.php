<?php

namespace Tests\Feature\Ceds;

use App\Http\Controllers\api\lms\CedsResourceController;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Runs on a THROWAWAY SQLITE FILE, never the shared database (same safeguard as the
 * exam-evaluation tests: the environment is redirected before the application boots, and
 * setUp refuses to continue unless the connection really is sqlite).
 *
 * The fixtures reproduce the rows the read-only inventory found for chapters 8592 and 8593
 * (ids, categories, visibility, lab statuses), plus rows of OTHER schools and chapters that
 * must never appear. The route is registered here with the staff gate only; the real route's
 * api.session wiring is not exercised by this test.
 */
class CedsResourceEndpointTest extends TestCase
{
    private const PLATFORM = 1;     // is_Lms N, owns chapters 8592 / 8593
    private const LMS_SCHOOL = 7;   // is_Lms Y: reads its own rows + the platform's
    private const OTHER_SCHOOL = 8; // is_Lms N: reads only its own

    /** @var array<string,string|false> */
    private array $savedEnvironment = [];
    private string $bootDatabase = '';

    public function createApplication()
    {
        $this->bootDatabase = (string) tempnam(sys_get_temp_dir(), 'ceds-boot-');
        $pdo = new \PDO('sqlite:' . $this->bootDatabase);
        $pdo->exec('CREATE TABLE custom_module_tables (id INTEGER PRIMARY KEY, module_name TEXT, access_link TEXT)');
        unset($pdo);

        foreach ([
            'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $this->bootDatabase, 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '1',
            'REDIS_HOST' => '127.0.0.1', 'REDIS_PORT' => '1', 'SESSION_DRIVER' => 'array', 'CACHE_DRIVER' => 'array',
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
        $this->assertSame('sqlite', DB::connection()->getDriverName(), 'refusing to run against a non-sqlite connection');

        $this->createTables();
        $this->seed8592And8593();
        config(['lms_content.platform_sub_institute_ids' => [self::PLATFORM]]);

        Route::middleware([StartSession::class, 'staff.only'])
            ->get('/_ceds/resources', [CedsResourceController::class, 'show']);
    }

    // --------------------------------------------------------------------- the endpoint

    public function test_chapter_8592_shows_only_live_rows_of_that_chapter(): void
    {
        $json = $this->get8592('P2')->assertOk()->json();
        $byFormat = collect($json['formats'])->keyBy('format');

        $this->assertSame(8592, $json['chapter']['id']);
        $this->assertSame([61401], array_column($byFormat['D2']['items'], 'id'));
        $this->assertSame([61404], array_column($byFormat['D3']['items'], 'id'));
        $this->assertSame([61402], array_column($byFormat['D5']['items'], 'id'));
        $this->assertSame([61403], array_column($byFormat['D6']['items'], 'id'));
        $this->assertSame(3, $byFormat['D2']['hidden_ignored']);
    }

    public function test_hidden_older_rows_unrelated_chapter_rows_and_other_schools_rows_are_never_exposed(): void
    {
        $json = $this->get8592('P2')->assertOk()->json();
        $exposed = collect($json['formats'])->pluck('items')->flatten(1)->pluck('id')->all();

        foreach ([53869, 60600, 60602, 61076, 61082, 53870, 60601, 60599] as $hidden) {
            $this->assertNotContains($hidden, $exposed, "hidden older row $hidden");
        }
        $this->assertNotContains(61406, $exposed, 'chapter 8593 deck');
        $this->assertNotContains(61470, $exposed, 'teacher-training row');
        $this->assertNotContains(99001, $exposed, "another school's row in the same chapter id");
        $this->assertStringNotContainsString('0123456789', json_encode($json), 'no file names leave the server');
    }

    public function test_chapter_8593_resolves_on_its_own(): void
    {
        $this->enableChapters(8593); // the scope is tested separately; this test is about resolving a second chapter on its own
        $json = $this->asPlatform('P1', 8593)->json();
        $byFormat = collect($json['formats'])->keyBy('format');
        $this->assertSame([61406], array_column($byFormat['D2']['items'], 'id'));
        $this->assertSame([61410], array_column($byFormat['D3']['items'], 'id'));
        $this->assertSame([61407], array_column($byFormat['D5']['items'], 'id'));
        $this->assertSame([61408], array_column($byFormat['D6']['items'], 'id'));
        $this->assertSame([3, 4, 17], array_column($byFormat['D4']['items'], 'id'));
    }

    public function test_labs_in_review_are_labelled_pending_review_and_never_available(): void
    {
        $d4 = collect($this->get8592('P2')->json('formats'))->firstWhere('format', 'D4');

        $this->assertSame('pending_review', $d4['status']);
        $this->assertSame([1, 2, 5], array_column($d4['items'], 'id'));
        foreach ($d4['items'] as $lab) {
            $this->assertSame('Pending Review', $lab['review_label']);
            $this->assertSame('pending_review', $lab['status']);
        }
        $this->assertNotContains(6, array_column($d4['items'], 'id'), 'a soft-deleted lab');
        $this->assertNotContains(7, array_column($d4['items'], 'id'), 'a hidden lab');
    }

    public function test_d1_is_met_by_the_existing_presentation_and_revision_notes_and_generation_stays_disabled(): void
    {
        $this->enableChapters(8592, 8593); // before the first request: the route keeps its controller (and its scope) after that
        $json = $this->get8592('P1')->json();
        $d1 = collect($json['formats'])->firstWhere('format', 'D1');

        $this->assertSame('available', $d1['status']);
        $this->assertSame([61401, 61402], array_column($d1['items'], 'id'), 'the live deck and the live revision notes of 8592');
        $this->assertSame(['presentation', 'revision_notes'], array_column($d1['items'], 'component'));
        $this->assertSame(['enabled' => false, 'adapt_enabled' => false], $json['generation']);
        foreach ($json['formats'] as $f) {
            $this->assertArrayNotHasKey('can_generate', $f);
        }

        $d1b = collect($this->asPlatform('P1', 8593)->json('formats'))->firstWhere('format', 'D1');
        $this->assertSame([61406, 61407], array_column($d1b['items'], 'id'), 'and of 8593');
    }

    public function test_d1_records_its_known_gaps_without_filling_them(): void
    {
        $formats = collect($this->get8592(null)->json('formats'))->keyBy('format');
        $this->assertSame(['real_life_links', 'key_vocabulary', 'misconceptions', 'check_yourself'], array_column($formats['D1']['gaps'], 'key'));
        foreach (['D2', 'D3', 'D4', 'D5', 'D6'] as $other) {
            $this->assertArrayNotHasKey('gaps', $formats[$other]);
        }
    }

    public function test_the_d2_entry_is_the_same_as_before_the_d1_mapping(): void
    {
        $d2 = collect($this->get8592('P1')->json('formats'))->firstWhere('format', 'D2');
        $this->assertSame([61401], array_column($d2['items'], 'id'));
        $this->assertSame('study_deck', $d2['items'][0]['kind']);
        $this->assertArrayNotHasKey('component', $d2['items'][0]);
        $this->assertSame(3, $d2['hidden_ignored']);
    }

    public function test_fit_is_returned_per_format_and_ordered_for_the_method(): void
    {
        $formats = $this->get8592('P1')->json('formats');
        $this->assertSame(['D1', 'D2', 'D5', 'D3', 'D6', 'D4'], array_column($formats, 'format'));
        $d4 = collect($formats)->firstWhere('format', 'D4');
        $this->assertSame('W', $d4['fit']);
        $this->assertNotEmpty($d4['warning']);
        // A weak fit warns; the existing lab is still shown (never blocked).
        $this->assertNotEmpty($d4['items']);
    }

    public function test_without_a_method_all_six_formats_are_listed_without_fit(): void
    {
        $json = $this->get8592(null)->assertOk()->json();
        $this->assertSame(['D1', 'D2', 'D3', 'D4', 'D5', 'D6'], array_column($json['formats'], 'format'));
        $this->assertArrayNotHasKey('fit', $json['formats'][0]);
        $this->assertNull($json['pedagogy']);
    }

    // ------------------------------------------------------------ tenancy and authorisation

    public function test_a_school_that_cannot_see_the_chapter_gets_404_and_no_rows(): void
    {
        $r = $this->asSchool(self::OTHER_SCHOOL)->getJson('/_ceds/resources?chapter_id=8592');
        $r->assertStatus(404);
        $this->assertStringNotContainsString('6140', $r->getContent());
    }

    public function test_an_lms_school_reads_the_platform_rows_plus_its_own_but_not_other_schools(): void
    {
        $json = $this->asSchool(self::LMS_SCHOOL)->getJson('/_ceds/resources?chapter_id=8592&pedagogy=P3')->assertOk()->json();
        $ids = collect($json['formats'])->pluck('items')->flatten(1)->pluck('id')->all();

        $this->assertContains(61402, $ids, 'platform row');
        $this->assertContains(99002, $ids, "the school's own row");
        $this->assertNotContains(99001, $ids, "another school's row");
    }

    public function test_a_student_session_is_refused_by_the_staff_gate(): void
    {
        $this->withSession(['sub_institute_id' => self::PLATFORM, 'user_profile_name' => 'Student', 'is_student' => true])
            ->getJson('/_ceds/resources?chapter_id=8592')->assertStatus(403);
    }

    public function test_a_session_without_a_school_is_refused(): void
    {
        $this->withSession(['user_profile_name' => 'Teacher'])->getJson('/_ceds/resources?chapter_id=8592')->assertStatus(403);
    }

    public function test_the_school_is_never_taken_from_the_request(): void
    {
        // Naming the platform school in the query must not widen what another school can read.
        $this->asSchool(self::OTHER_SCHOOL)
            ->getJson('/_ceds/resources?chapter_id=8592&sub_institute_id=1')->assertStatus(404);
    }

    public function test_invalid_input_is_rejected(): void
    {
        $this->asSchool(self::PLATFORM)->getJson('/_ceds/resources?chapter_id=8592&pedagogy=P9')->assertStatus(422);
        $this->asSchool(self::PLATFORM)->getJson('/_ceds/resources')->assertStatus(422);
    }

    // ------------------------------------------------------------------------ read-only

    public function test_a_request_issues_only_select_statements_and_changes_no_row(): void
    {
        $before = $this->snapshot();
        $statements = [];
        DB::listen(function ($q) use (&$statements) {
            $statements[] = $q->sql;
        });

        $this->get8592('P2')->assertOk();
        $this->asSchool(self::LMS_SCHOOL)->getJson('/_ceds/resources?chapter_id=8592')->assertOk();

        $this->assertNotEmpty($statements);
        foreach ($statements as $sql) {
            $this->assertMatchesRegularExpression('/^\s*select\b/i', $sql, $sql);
        }
        $this->assertSame($before, $this->snapshot());
    }

    public function test_the_new_classes_contain_no_write_calls(): void
    {
        $root = dirname(__DIR__, 3) . '/app';
        foreach (['Services/Ceds/ResourceRowLoader.php', 'Services/Ceds/ResourceResolver.php', 'Services/Ceds/CedsFitService.php',
                  'Http/Controllers/api/lms/CedsResourceController.php'] as $file) {
            $code = (string) preg_replace('~/\*.*?\*/|//[^\n]*~s', '', (string) file_get_contents("$root/$file"));
            $this->assertDoesNotMatchRegularExpression('/->(insert|insertGetId|update|updateOrInsert|upsert|delete|truncate|increment|decrement)\s*\(|DB::(statement|unprepared|insert|update|delete|transaction)\b|Schema::|->save\s*\(|Storage::/i', $code, $file);
        }
    }

    // --------------------------------------------------------------------------- helpers

    private function asSchool(int $school): static
    {
        return $this->withSession(['sub_institute_id' => $school, 'user_profile_name' => 'Teacher', 'is_student' => false]);
    }

    private function asPlatform(?string $pedagogy, int $chapter)
    {
        $q = http_build_query(array_filter(['chapter_id' => $chapter, 'pedagogy' => $pedagogy]));

        return $this->asSchool(self::PLATFORM)->getJson('/_ceds/resources?' . $q)->assertOk();
    }

    private function get8592(?string $pedagogy)
    {
        return $this->asPlatform($pedagogy, 8592);
    }

    // ------------------------------------------------------------- only Std 9 Science, Chapter 1

    public function test_only_std_9_science_chapter_1_is_enabled_every_other_chapter_is_refused(): void
    {
        $this->get8592('P1')->assertOk();

        $r = $this->asSchool(self::PLATFORM)->getJson('/_ceds/resources?chapter_id=8593&pedagogy=P1');
        $r->assertStatus(403);
        $this->assertStringContainsString('Std 9 Science, Chapter 1', $r->json('message'));
        $this->assertArrayNotHasKey('formats', $r->json());

        // A chapter that does not exist is still "not found", not "not enabled".
        $this->asSchool(self::PLATFORM)->getJson('/_ceds/resources?chapter_id=999999')->assertStatus(404);
    }

    /** Test-only: switch the picker on for other fixture chapters, to test logic that is not about the scope. */
    private function enableChapters(int ...$ids): void
    {
        config(['ceds_fit_matrix.scope.chapters' => array_map(fn (int $id) => ['chapter_id' => $id, 'standard_id' => 42, 'subject_id' => 3975], $ids)]);
    }

    private function snapshot(): string
    {
        return json_encode([
            DB::table('content_master')->orderBy('id')->get(),
            DB::table('lms_prayogshala_activity')->orderBy('id')->get(),
        ]);
    }

    private function createTables(): void
    {
        Schema::create('school_setup', function ($t) {
            $t->integer('Id')->primary();
            $t->string('is_Lms')->nullable();
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
    }

    private function seed8592And8593(): void
    {
        DB::table('school_setup')->insert([
            ['Id' => self::PLATFORM, 'is_Lms' => 'N'], ['Id' => self::LMS_SCHOOL, 'is_Lms' => 'Y'], ['Id' => self::OTHER_SCHOOL, 'is_Lms' => 'N'],
        ]);
        DB::table('chapter_master')->insert([
            ['id' => 8592, 'chapter_name' => 'Exploration', 'standard_id' => 42, 'subject_id' => 3975, 'sub_institute_id' => self::PLATFORM],
            ['id' => 8593, 'chapter_name' => 'Cell', 'standard_id' => 42, 'subject_id' => 3975, 'sub_institute_id' => self::PLATFORM],
        ]);

        $c = fn (int $id, int $ch, ?string $cat, string $type, ?int $sh, string $file, ?string $src = 'Claude AI', int $tenant = self::PLATFORM) => [
            'id' => $id, 'chapter_id' => $ch, 'concept_id' => null, 'title' => "row $id", 'filename' => $file, 'file_type' => $type,
            'show_hide' => $sh, 'content_category' => $cat, 'source' => $src, 'sub_institute_id' => $tenant, 'created_at' => '2026-10-10 10:00:00',
        ];
        $s = 'exploration_entering_the_world_of_secondary_science';
        $cell = 'cell_the_building_block_of_life';
        DB::table('content_master')->insert([
            // chapter 8592
            $c(53869, 8592, 'Classroom Activity', 'pdf', 0, "classroom_activity_{$s}_1"),
            $c(60600, 8592, 'Classroom Activity', 'pdf', 0, "classroom_activity_{$s}_2"),
            $c(61404, 8592, 'Classroom Activity', 'pdf', 1, "study_classroom_activity_{$s}_all_0123456789ab.pdf"),
            $c(60602, 8592, 'Classroom Presentation', 'pdf', 0, "presentation_{$s}_1"),
            $c(61076, 8592, 'Classroom Presentation', 'pptx', 0, "classroom_presentation_{$s}_1"),
            $c(61082, 8592, 'Classroom Presentation', 'pptx', 0, "classroom_presentation_{$s}_2"),
            $c(61401, 8592, 'Classroom Presentation', 'pptx', 1, "study_deck_{$s}_4207133.pptx"),
            $c(53870, 8592, 'Remedial Class', 'pdf', 0, "remedial_class_{$s}_1"),
            $c(60601, 8592, 'Remedial Class', 'pdf', 0, "remedial_class_{$s}_2"),
            $c(61403, 8592, 'Remedial Class', 'pdf', 1, "study_remedial_class_{$s}_all_0123456789cd.pdf"),
            $c(60599, 8592, 'Revision Notes', 'pdf', 0, "revision_notes_{$s}_1"),
            $c(61402, 8592, 'Revision Notes', 'pdf', 1, "study_revision_notes_{$s}_all_0123456789ef.pdf"),
            $c(61470, 8592, 'Teacher Training', 'pdf', 1, 'lms_teacher_resource_learning_outcomes_1.pdf'),
            // chapter 8593
            $c(61406, 8593, 'Classroom Presentation', 'pptx', 1, "study_deck_{$cell}_23f14ac59c14.pptx"),
            $c(61409, 8593, 'Classroom Activity', 'pdf', 0, "study_classroom_activity_{$cell}_all_542caadaf000.pdf"),
            $c(61410, 8593, 'Classroom Activity', 'pdf', 1, "study_classroom_activity_{$cell}_all_57670e8b2000.pdf"),
            $c(61407, 8593, 'Revision Notes', 'pdf', 1, "study_revision_notes_{$cell}_all_51b91fa1ed81.pdf"),
            $c(61408, 8593, 'Remedial Class', 'pdf', 1, "study_remedial_class_{$cell}_all_24d941f122b7.pdf"),
            // another school's row filed under the same chapter id, and the LMS school's own row
            $c(99001, 8592, 'Revision Notes', 'pdf', 1, "study_revision_notes_{$s}_all_aaaaaaaaaaaa.pdf", 'Claude AI', self::OTHER_SCHOOL),
            $c(99002, 8592, 'Revision Notes', 'pdf', 1, "study_revision_notes_{$s}_all_bbbbbbbbbbbb.pdf", 'Claude AI', self::LMS_SCHOOL),
        ]);

        $lab = fn (int $id, int $ch, string $status, array $extra = []) => $extra + [
            'id' => $id, 'chapter_id' => $ch, 'topic_id' => 1000 + $id, 'concept_id' => null, 'title' => "lab $id", 'status' => $status,
            'generation_status' => 'ready', 'show_hide' => 1, 'sort_order' => $id, 'lab_config' => '{"x":1}', 'deleted_at' => null,
            'sub_institute_id' => self::PLATFORM,
        ];
        DB::table('lms_prayogshala_activity')->insert([
            $lab(1, 8592, 'review', ['concept_id' => 7189, 'generation_status' => null]),
            $lab(2, 8592, 'review', ['concept_id' => 7208, 'generation_status' => null]),
            $lab(5, 8592, 'review'),
            $lab(6, 8592, 'review', ['deleted_at' => '2026-10-01']),      // soft-deleted
            $lab(7, 8592, 'review', ['show_hide' => 0]),                  // hidden
            $lab(3, 8593, 'review', ['concept_id' => 7315]),
            $lab(4, 8593, 'review', ['concept_id' => 7319]),
            $lab(17, 8593, 'review'),
        ]);
    }
}
