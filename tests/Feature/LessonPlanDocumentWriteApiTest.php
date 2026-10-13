<?php

namespace Tests\Feature;

use App\Http\Controllers\api\lms\LessonPlanDocumentLookupApiController;
use App\Http\Controllers\api\lms\LessonPlanDocumentWriteApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Lesson Plan document write side: the upsert, the day-wise diff-delete, the CSV
 * activity-id columns and the mapping_value guard.
 *
 * RUNS ON IN-MEMORY SQLITE, FORCED HERE - same convention as PrayogshalaApiTest, so
 * this suite cannot reach the real shared vivek_erp database.
 *
 * Middleware is not exercised (api.session/lms.auth need a real JWT); a separate test
 * asserts the route table puts the write gate in front of the controller, same as
 * PrayogshalaApiTest does for its own routes.
 */
class LessonPlanDocumentWriteApiTest extends TestCase
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
            $t->integer('standard_id');
            $t->integer('subject_id');
            $t->string('chapter_name');
        });
        Schema::create('topic_master', function ($t) {
            $t->integer('id')->primary();
            $t->integer('sub_institute_id');
            $t->integer('chapter_id');
            $t->string('name');
            $t->integer('topic_sort_order')->default(0);
        });
        Schema::create('content_master', function ($t) {
            $t->integer('id')->primary();
            $t->integer('sub_institute_id');
            $t->integer('standard_id')->nullable();
            $t->integer('subject_id')->nullable();
            $t->integer('chapter_id')->nullable();
            $t->integer('topic_id')->nullable();
            $t->string('title');
        });
        Schema::create('question_paper', function ($t) {
            $t->integer('id')->primary();
            $t->integer('sub_institute_id');
            $t->integer('standard_id')->nullable();
            $t->integer('subject_id')->nullable();
            $t->string('paper_name');
        });

        // The real migrations, so this test also proves they create what the
        // controllers read/write.
        (require base_path('database/migrations/2023_03_05_115658_create_lms_lesson_plan_table.php'))->up();
        (require base_path('database/migrations/2023_03_05_115658_create_lms_lessonplan_dayswise_table.php'))->up();
        (require base_path('database/migrations/2023_03_05_115658_create_lms_mapping_type_table.php'))->up();

        // mapping_value has no migration anywhere in this repo - it is a live,
        // undocumented column in production. Add it here to simulate that live state
        // so the round-trip test below is meaningful; the guard test drops it again to
        // simulate an environment where it is genuinely absent.
        Schema::table('lms_lesson_plan', fn ($t) => $t->longText('mapping_value')->nullable());

        DB::table('standard')->insert(['id' => 42, 'name' => '9']);
        DB::table('subject')->insert(['id' => 3975, 'subject_name' => 'Science']);
        DB::table('chapter_master')->insert([
            'id' => 100, 'sub_institute_id' => 7, 'standard_id' => 42, 'subject_id' => 3975, 'chapter_name' => 'Ch A',
        ]);
        DB::table('topic_master')->insert([
            'id' => 11, 'sub_institute_id' => 7, 'chapter_id' => 100, 'name' => 'Topic One', 'topic_sort_order' => 1,
        ]);
        DB::table('content_master')->insert([
            ['id' => 501, 'sub_institute_id' => 7, 'standard_id' => 42, 'subject_id' => 3975, 'chapter_id' => 100, 'topic_id' => 11, 'title' => 'Intro slides'],
            ['id' => 502, 'sub_institute_id' => 8, 'standard_id' => 42, 'subject_id' => 3975, 'chapter_id' => 100, 'topic_id' => 11, 'title' => 'Other tenant slides'],
        ]);
        DB::table('question_paper')->insert([
            ['id' => 701, 'sub_institute_id' => 7, 'standard_id' => 42, 'subject_id' => 3975, 'paper_name' => 'Unit test 1'],
        ]);
    }

    private function valid(array $over = []): array
    {
        return array_merge([
            'sub_institute_id' => 7,
            'syear' => 2026,
            'standard_id' => 42,
            'subject_id' => 3975,
            'chapter_id' => 100,
            'topic_id' => 11,
            'summary' => [
                'number_of_periods' => 5,
                'teaching_time' => '40 min',
                'assessment_time' => '10 min',
                'learning_time' => '20 min',
                'assessment_qualifying' => 'Qualifying note',
                'mapping' => [['type_id' => 1, 'value_id' => 2]],
            ],
            'teaching' => [
                'focus_point' => '<p>Focus</p>',
                'pedagogical_process' => '<p>Process</p>',
                'resource' => '<p>Resource</p>',
                'classroom_presentation' => '<p>Presentation</p>',
                'classroom_diversity' => '<p>Diversity</p>',
                'classroom_activity_ids' => [501],
            ],
            'learning' => [
                'learning_objective' => '<p>Objective</p>',
                'learning_knowledge' => '<p>Knowledge</p>',
                'learning_skill' => '<p>Skill</p>',
                'prerequisite' => '<p>Prerequisite</p>',
                'self_study_homework' => '<p>Homework</p>',
                'self_study_activity_ids' => [501],
                'assessment' => '<p>Assessment</p>',
                'assessment_activity_ids' => [701],
            ],
            'map_and_alignment' => [
                'hard_word' => '<p>Hard word</p>',
                'tag_metatag' => '<p>Tag</p>',
                'value_integration' => '<p>Value</p>',
                'global_connection' => '<p>Global</p>',
                'sel' => '<p>SEL</p>',
                'stem' => '<p>STEM</p>',
                'vocational_training' => '<p>Vocational</p>',
                'simulation' => '<p>Simulation</p>',
                'games' => '<p>Games</p>',
                'activities' => '<p>Activities</p>',
                'real_life_application' => '<p>Real life</p>',
            ],
            'days' => [
                [
                    'day' => 1,
                    'topic_name' => 'Day one topic',
                    'class_time' => '40',
                    'during_content' => 'Content',
                    'assessment_qualifying' => 'Qualifying',
                    'learning_objective' => 'Objective',
                    'learning_outcome' => 'Outcome',
                    'pedagogical_process' => 'Process',
                    'resource' => 'Resource',
                    'closure' => 'Closure',
                    'self_study_homework' => 'Homework',
                    'self_study_activity_ids' => [501],
                    'assessment' => 'Assessment',
                    'assessment_activity_ids' => [701],
                ],
            ],
        ], $over);
    }

    private function save(array $input): array
    {
        $request = Request::create('/x', 'POST', $input);
        $controller = app(LessonPlanDocumentWriteApiController::class);
        $response = $controller->save($request);

        return [$response->getStatusCode(), $response->getData(true)];
    }

    public function test_create_inserts_a_new_plan_and_its_day_rows(): void
    {
        [$status, $body] = $this->save($this->valid());

        $this->assertSame(200, $status);
        $this->assertTrue($body['status']);
        $planId = $body['data']['id'];

        $plan = DB::table('lms_lesson_plan')->where('id', $planId)->first();
        $this->assertSame(7, (int) $plan->sub_institute_id);
        $this->assertSame(100, (int) $plan->chapter_id);
        $this->assertSame('<p>Focus</p>', $plan->focauspoint);

        $days = DB::table('lms_lessonplan_dayswise')->where('lpid', $planId)->get();
        $this->assertCount(1, $days);
        $this->assertSame('Day one topic', $days[0]->topicname);
    }

    public function test_update_path_updates_in_place_and_does_not_duplicate(): void
    {
        [, $first] = $this->save($this->valid());
        $planId = $first['data']['id'];

        [$status, $second] = $this->save($this->valid(['id' => $planId, 'teaching' => array_merge(
            $this->valid()['teaching'],
            ['focus_point' => '<p>Updated focus</p>']
        )]));

        $this->assertSame(200, $status);
        $this->assertSame($planId, $second['data']['id']);
        $this->assertSame(1, DB::table('lms_lesson_plan')->count());
        $this->assertSame('<p>Updated focus</p>', DB::table('lms_lesson_plan')->value('focauspoint'));
    }

    public function test_day_rows_not_resubmitted_are_deleted(): void
    {
        $withThreeDays = $this->valid();
        $withThreeDays['days'] = [
            array_merge($withThreeDays['days'][0], ['day' => 1]),
            array_merge($withThreeDays['days'][0], ['day' => 2]),
            array_merge($withThreeDays['days'][0], ['day' => 3]),
        ];
        [, $first] = $this->save($withThreeDays);
        $planId = $first['data']['id'];
        $this->assertSame(3, DB::table('lms_lessonplan_dayswise')->where('lpid', $planId)->count());

        $withTwoDays = $withThreeDays;
        $withTwoDays['id'] = $planId;
        $withTwoDays['days'] = [$withThreeDays['days'][0], $withThreeDays['days'][2]];
        $this->save($withTwoDays);

        $remainingDays = DB::table('lms_lessonplan_dayswise')->where('lpid', $planId)->pluck('days')->map(fn ($d) => (int) $d)->sort()->values()->all();
        $this->assertSame([1, 3], $remainingDays);
    }

    public function test_activity_ids_persist_as_comma_joined_strings(): void
    {
        [, $body] = $this->save($this->valid(['teaching' => array_merge(
            $this->valid()['teaching'],
            ['classroom_activity_ids' => [501, 502]]
        )]));
        $planId = $body['data']['id'];

        $this->assertSame('501,502', DB::table('lms_lesson_plan')->where('id', $planId)->value('classroomactivity'));
        $day = DB::table('lms_lessonplan_dayswise')->where('lpid', $planId)->first();
        $this->assertSame('501', $day->selfstudyactivity);
        $this->assertSame('701', $day->assessmentactivity);
    }

    public function test_mapping_value_round_trips_as_json_and_the_schema_guard_holds_without_the_column(): void
    {
        [, $body] = $this->save($this->valid());
        $planId = $body['data']['id'];
        $raw = DB::table('lms_lesson_plan')->where('id', $planId)->value('mapping_value');
        $this->assertSame(['1' => 2], json_decode($raw, true));

        // mapping_value has no migration in this repo - simulate that by dropping it
        // and confirming save() still succeeds instead of erroring on a missing column.
        Schema::table('lms_lesson_plan', fn ($t) => $t->dropColumn('mapping_value'));
        [$status] = $this->save($this->valid());
        $this->assertSame(200, $status);
    }

    public function test_missing_required_field_is_rejected_with_422(): void
    {
        $missingFocusPoint = $this->valid();
        unset($missingFocusPoint['teaching']['focus_point']);

        [$status, $body] = $this->save($missingFocusPoint);

        $this->assertSame(422, $status);
        $this->assertFalse($body['status']);
        $this->assertArrayHasKey('teaching.focus_point', $body['errors']);
    }

    public function test_lookup_endpoints_return_scoped_results(): void
    {
        $topicsController = app(LessonPlanDocumentLookupApiController::class);

        $topics = $topicsController->topics(Request::create('/x', 'GET', ['sub_institute_id' => 7, 'chapter_id' => 100]));
        $this->assertSame(['Topic One'], array_column($topics->getData(true)['data'], 'name'));

        $content = $topicsController->contentSearch(Request::create('/x', 'GET', ['sub_institute_id' => 7, 'chapter_id' => 100]));
        $this->assertSame(['Intro slides'], array_column($content->getData(true)['data'], 'title'), 'another tenant\'s content is not returned');

        $questions = $topicsController->questionPaperSearch(Request::create('/x', 'GET', ['sub_institute_id' => 7, 'standard_id' => 42]));
        $this->assertSame(['Unit test 1'], array_column($questions->getData(true)['data'], 'title'));
    }

    public function test_save_route_sits_behind_the_write_permission_gate(): void
    {
        $route = collect(Route::getRoutes()->getRoutes())->first(fn ($r) => $r->uri() === 'api/lesson-plan-doc/save');
        $this->assertNotNull($route);
        $this->assertContains('api.session', $route->gatherMiddleware());
        $this->assertContains('lms.auth', $route->gatherMiddleware());
        $this->assertContains('perm:lms.content,create', $route->gatherMiddleware());
    }
}
