<?php

namespace Tests\Feature;

use App\Http\Controllers\api\lms\PrayogshalaApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Prayogshala controller: scoping, hierarchy validation and CRUD.
 *
 * RUNS ON IN-MEMORY SQLITE, FORCED HERE. phpunit.xml leaves its sqlite lines commented
 * out, so a test that touches the DB would otherwise write to the live shared vivek_erp.
 * setUp swaps the default connection to sqlite and refuses to continue if that did not
 * take, so this suite cannot reach the real database.
 *
 * Middleware is not exercised (api.session needs a real JWT and tbluserprofilemaster);
 * the session `api.session` would hydrate is attached by hand, and a separate test
 * asserts the route table puts that middleware, and the write gate, in front of the
 * controller.
 */
class PrayogshalaApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        DB::purge('sqlite');
        $this->assertSame('sqlite', DB::connection()->getDriverName(), 'refusing to run against a real database');

        Schema::create('school_setup', function ($t) {
            $t->integer('Id')->primary();
            $t->string('is_Lms')->nullable();
        });
        Schema::create('standard', function ($t) {
            $t->integer('id')->primary();
            $t->string('name');
        });
        Schema::create('subject', function ($t) {
            $t->integer('id')->primary();
            $t->string('subject_name');
        });
        Schema::create('chapter_master', function ($t) {
            $t->integer('id')->primary();
            $t->integer('sub_institute_id');
            $t->integer('grade_id')->nullable();
            $t->integer('standard_id');
            $t->integer('subject_id');
            $t->string('chapter_name');
        });
        Schema::create('topic_master', function ($t) {
            $t->integer('id')->primary();
            $t->integer('chapter_id');
            $t->string('name');
            $t->integer('topic_sort_order')->default(0);
        });
        Schema::create('lms_concept', function ($t) {
            $t->integer('id')->primary();
            $t->integer('chapter_id');
            $t->integer('topic_id')->nullable();
            $t->string('name')->nullable();
        });
        // The real migration, so the test also proves it creates what the controller reads.
        (require base_path('database/migrations/2026_10_08_100000_create_lms_prayogshala_activity_table.php'))->up();

        DB::table('school_setup')->insert([
            ['Id' => 1, 'is_Lms' => 'Y'],   // platform tenant
            ['Id' => 7, 'is_Lms' => 'Y'],   // institute A, platform-linked
            ['Id' => 8, 'is_Lms' => 'N'],   // institute B, not platform-linked
        ]);
        DB::table('standard')->insert([['id' => 42, 'name' => '9'], ['id' => 43, 'name' => '10']]);
        DB::table('subject')->insert([['id' => 3975, 'subject_name' => 'Science'], ['id' => 4064, 'subject_name' => 'Social Science']]);
        DB::table('chapter_master')->insert([
            ['id' => 100, 'sub_institute_id' => 1, 'grade_id' => 12, 'standard_id' => 42, 'subject_id' => 3975, 'chapter_name' => 'Ch A'],
            ['id' => 200, 'sub_institute_id' => 1, 'grade_id' => 13, 'standard_id' => 43, 'subject_id' => 4064, 'chapter_name' => 'Power Sharing'],
            ['id' => 300, 'sub_institute_id' => 8, 'grade_id' => 12, 'standard_id' => 42, 'subject_id' => 3975, 'chapter_name' => 'B only chapter'],
        ]);
        DB::table('topic_master')->insert([
            ['id' => 11, 'chapter_id' => 100, 'name' => 'Topic One', 'topic_sort_order' => 1],
            ['id' => 12, 'chapter_id' => 100, 'name' => 'Topic Two', 'topic_sort_order' => 2],
            ['id' => 21, 'chapter_id' => 200, 'name' => 'Other chapter topic', 'topic_sort_order' => 1],
        ]);
        DB::table('lms_concept')->insert([
            ['id' => 5, 'chapter_id' => 100, 'topic_id' => 11, 'name' => 'Concept Five'],
            ['id' => 6, 'chapter_id' => 100, 'topic_id' => 12, 'name' => 'Concept Six'],
        ]);
    }

    private function act(string $method, array $input, int $tenant, string $profile = 'Teacher', bool $student = false, ?int $id = null)
    {
        $request = Request::create('/x', 'POST', $input);
        $store = app('session.store');
        $store->flush();
        $request->setLaravelSession($store);
        $store->put('sub_institute_id', $tenant);
        $store->put('user_id', 55);
        $store->put('syear', 2026);
        $store->put('user_profile_name', $profile);
        $store->put('is_student', $student);

        $controller = app(PrayogshalaApiController::class);
        $response = $id === null ? $controller->{$method}($request) : $controller->{$method}($request, $id);

        return [$response->getStatusCode(), $response->getData(true)];
    }

    private function valid(array $over = []): array
    {
        return array_merge([
            'chapter_id' => 100,
            'title' => 'Test the pH of household liquids',
            'activity_type' => 'experiment',
            'objective' => 'Classify liquids as acidic or basic.',
            'materials_required' => ['Litmus paper', 'Lemon juice', ''],
            'procedure_steps' => ['Dip the paper.', 'Note the colour.'],
            'resources' => [['type' => 'video', 'title' => 'Demo', 'url' => 'https://example.org/v.mp4']],
        ], $over);
    }

    public function test_create_derives_standard_subject_and_grade_from_the_chapter_and_owns_it_to_the_caller(): void
    {
        [$status, $body] = $this->act('store', $this->valid(), 7);

        $this->assertSame(201, $status);
        $this->assertSame(42, $body['data']['standard_id']);
        $this->assertSame(3975, $body['data']['subject_id']);
        $this->assertSame(['Litmus paper', 'Lemon juice'], $body['data']['materials_required'], 'blank list items are dropped');
        $row = DB::table('lms_prayogshala_activity')->first();
        $this->assertSame(7, (int) $row->sub_institute_id);
        $this->assertSame(12, (int) $row->grade_id);
        $this->assertSame(55, (int) $row->created_by);
    }

    public function test_the_body_cannot_choose_the_tenant(): void
    {
        $this->act('store', $this->valid(['sub_institute_id' => 8]), 7);

        $this->assertSame(7, (int) DB::table('lms_prayogshala_activity')->value('sub_institute_id'));
    }

    public function test_a_client_supplied_standard_or_subject_that_disagrees_with_the_chapter_is_refused(): void
    {
        [$status] = $this->act('store', $this->valid(['standard_id' => 43]), 7);
        $this->assertSame(422, $status);
        [$status] = $this->act('store', $this->valid(['subject_id' => 4064]), 7);
        $this->assertSame(422, $status);
        $this->assertSame(0, DB::table('lms_prayogshala_activity')->count());
    }

    public function test_one_institute_never_receives_another_institutes_activities(): void
    {
        $this->act('store', $this->valid(['chapter_id' => 300, 'title' => 'B secret']), 8);
        $this->act('store', $this->valid(['title' => 'A own']), 7);

        // Institute A cannot even see B's chapter, let alone B's rows.
        [$status] = $this->act('index', ['chapter_id' => 300], 7);
        $this->assertSame(404, $status);

        [, $b] = $this->act('index', ['chapter_id' => 300], 8);
        $this->assertSame(['B secret'], array_column($b['data']['activities'], 'title'));

        // B is not platform-linked, so the platform chapter - and A's rows on it - are not
        // visible to it at all.
        [$status] = $this->act('index', ['chapter_id' => 100], 8);
        $this->assertSame(404, $status);

        // Nor can B read A's row directly by id.
        $aId = (int) DB::table('lms_prayogshala_activity')->where('title', 'A own')->value('id');
        [$status] = $this->act('show', [], 8, 'Teacher', false, $aId);
        $this->assertSame(404, $status);
    }

    public function test_a_platform_linked_institute_sees_platform_rows_but_cannot_edit_them(): void
    {
        $this->act('store', $this->valid(['title' => 'Platform activity']), 1);

        [, $body] = $this->act('index', ['chapter_id' => 100], 7);
        $this->assertSame(['Platform activity'], array_column($body['data']['activities'], 'title'));
        $this->assertFalse($body['data']['activities'][0]['editable']);

        $id = $body['data']['activities'][0]['id'];
        [$status] = $this->act('update', ['title' => 'Hijacked'], 7, 'Teacher', false, $id);
        $this->assertSame(404, $status);
        [$status] = $this->act('destroy', [], 7, 'Teacher', false, $id);
        $this->assertSame(404, $status);
        $this->assertSame('Platform activity', DB::table('lms_prayogshala_activity')->value('title'));
    }

    public function test_students_cannot_write_and_do_not_see_hidden_activities(): void
    {
        [$status] = $this->act('store', $this->valid(), 7, 'Student', true);
        $this->assertSame(403, $status);

        $this->act('store', $this->valid(['title' => 'Draft', 'show_hide' => false]), 7);
        $this->act('store', $this->valid(['title' => 'Published']), 7);

        [, $teacher] = $this->act('index', ['chapter_id' => 100], 7);
        $this->assertCount(2, $teacher['data']['activities']);
        $this->assertTrue($teacher['data']['can_manage']);

        [, $student] = $this->act('index', ['chapter_id' => 100], 7, 'Student', true);
        $this->assertSame(['Published'], array_column($student['data']['activities'], 'title'));
        $this->assertFalse($student['data']['can_manage']);

        $hiddenId = (int) DB::table('lms_prayogshala_activity')->where('title', 'Draft')->value('id');
        [$status] = $this->act('show', [], 7, 'Student', true, $hiddenId);
        $this->assertSame(404, $status);
    }

    public function test_update_is_partial_and_delete_is_soft_and_hides_the_row(): void
    {
        $this->act('store', $this->valid(), 7);
        $id = (int) DB::table('lms_prayogshala_activity')->value('id');

        [$status, $body] = $this->act('update', ['title' => 'Renamed'], 7, 'Teacher', false, $id);
        $this->assertSame(200, $status);
        $this->assertSame('Renamed', $body['data']['title']);
        $this->assertSame(['Dip the paper.', 'Note the colour.'], $body['data']['procedure_steps'], 'untouched fields survive');

        [$status] = $this->act('update', ['chapter_id' => 200], 7, 'Teacher', false, $id);
        $this->assertSame(422, $status, 'an activity cannot be moved to another chapter');

        [$status] = $this->act('destroy', [], 7, 'Teacher', false, $id);
        $this->assertSame(200, $status);
        $this->assertNotNull(DB::table('lms_prayogshala_activity')->value('deleted_at'));
        [, $list] = $this->act('index', ['chapter_id' => 100], 7);
        $this->assertSame([], $list['data']['activities']);
    }

    public function test_invalid_input_is_rejected_server_side(): void
    {
        [$status] = $this->act('index', ['chapter_id' => 'abc'], 7);
        $this->assertSame(422, $status);
        [$status] = $this->act('index', ['chapter_id' => 999999], 7);
        $this->assertSame(404, $status);
        [$status] = $this->act('store', $this->valid(['activity_type' => 'bogus']), 7);
        $this->assertSame(422, $status);
        [$status] = $this->act('store', $this->valid(['resources' => [['type' => 'link', 'url' => 'javascript:alert(1)']]]), 7);
        $this->assertSame(422, $status);
        [$status] = $this->act('store', $this->valid(['concept_id' => 999]), 7);
        $this->assertSame(422, $status, 'a concept from outside the chapter is refused');
        [$status] = $this->act('store', $this->valid(['concept_id' => 5]), 7);
        $this->assertSame(201, $status);
    }

    public function test_activities_are_topic_aware_and_the_topic_must_belong_to_the_chapter(): void
    {
        [$status, $body] = $this->act('store', $this->valid(['topic_id' => 11, 'concept_id' => 5]), 7);
        $this->assertSame(201, $status);
        $this->assertSame('Topic One', $body['data']['topic_name']);
        $this->assertSame('Concept Five', $body['data']['concept_name']);

        [$status] = $this->act('store', $this->valid(['topic_id' => 21]), 7);
        $this->assertSame(422, $status, 'a topic of another chapter is refused');
        [$status] = $this->act('store', $this->valid(['topic_id' => 11, 'concept_id' => 6]), 7);
        $this->assertSame(422, $status, 'a concept that sits under a different topic is refused');

        [, $list] = $this->act('index', ['chapter_id' => 100], 7);
        $this->assertSame([['id' => 11, 'name' => 'Topic One'], ['id' => 12, 'name' => 'Topic Two']], $list['data']['topics']);
    }

    public function test_the_content_list_merge_carries_activities_for_the_verified_institute_only(): void
    {
        $service = app(\App\Services\lms\Prayogshala\PrayogshalaService::class);
        $this->act('store', $this->valid(['title' => 'Platform one', 'topic_id' => 11]), 1);
        $this->act('store', $this->valid(['title' => 'Hidden draft', 'show_hide' => false]), 1);
        $chapter = $service->visibleChapter(100, 7);

        $asLearner = $service->assetsForChapter($chapter, 7, true);
        $this->assertSame(['Platform one'], array_column($asLearner, 'title'));
        $this->assertSame('Prayogshala', $asLearner[0]['content_category']);
        $this->assertStringStartsWith('prayogshala:', $asLearner[0]['id'], 'ids are namespaced away from content_master ids');
        $this->assertSame('Topic One', $asLearner[0]['topic_name']);
        $this->assertSame('Platform one', $asLearner[0]['prayogshala']['title']);

        $asStaff = $service->assetsForChapter($chapter, 7, false);
        $this->assertCount(2, $asStaff);

        // No verified institute -> nothing, never "everything".
        $this->assertSame([], $service->assetsForChapter($chapter, null, false));
        // An institute that cannot see the platform tenant gets none of its rows.
        $this->assertSame([], $service->assetsForChapter($chapter, 8, false));
    }

    public function test_it_is_not_subject_specific(): void
    {
        [$status, $body] = $this->act('store', [
            'chapter_id' => 200,
            'title' => 'Map the distribution of power',
            'activity_type' => 'map_activity',
        ], 1);

        $this->assertSame(201, $status);
        $this->assertSame(4064, $body['data']['subject_id']);
        $this->assertSame('Map activity', $body['data']['activity_type_label']);
    }

    public function test_routes_sit_behind_the_session_gate_and_writes_behind_the_permission_gate(): void
    {
        foreach (['lms/prayogshala', 'lms/prayogshala/{id}', 'lms/prayogshala/store', 'lms/prayogshala/{id}/update', 'lms/prayogshala/{id}/delete'] as $uri) {
            $route = collect(Route::getRoutes()->getRoutes())->first(fn ($r) => $r->uri() === 'api/' . $uri);
            $this->assertNotNull($route, $uri);
            $this->assertContains('api.session', $route->gatherMiddleware(), $uri);
            if (str_contains($uri, 'store') || str_contains($uri, 'update') || str_contains($uri, 'delete')) {
                $this->assertContains('perm:lms.content,create', $route->gatherMiddleware(), $uri);
            }
        }
    }
}
