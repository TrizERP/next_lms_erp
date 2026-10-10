<?php

namespace Tests\Feature;

use App\Http\Controllers\api\lms\PrayogshalaApiController;
use App\Services\lms\Prayogshala\PrayogshalaGenerator;
use App\Services\lms\Prayogshala\PrayogshalaService;
use App\Services\lms\Prayogshala\SimulationConfigValidator;
use App\Services\QuestionGeneration\H5p\ClaudeQuestionClient;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Topic-wise Prayogshala generation: validation of the simulation config, the one-activity-per-topic
 * rule, idempotency, failure/retry/regeneration, honesty about thin source material, and tenancy.
 *
 * In-memory SQLite, forced in setUp (phpunit.xml would otherwise point at the live shared DB), and the
 * AI provider is a fake: no network call, no key, no cost.
 */
class PrayogshalaGenerationTest extends TestCase
{
    private FakeProvider $ai;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'queue.default' => 'sync',
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
            $t->integer('syear')->nullable();
            $t->integer('grade_id')->nullable();
            $t->integer('standard_id');
            $t->integer('subject_id');
            $t->string('chapter_name');
            $t->integer('sort_order')->default(0);
        });
        Schema::create('topic_master', function ($t) {
            $t->integer('id')->primary();
            $t->integer('chapter_id');
            $t->string('name');
            $t->text('description')->nullable();
            $t->integer('topic_sort_order')->default(0);
        });
        Schema::create('lms_concept', function ($t) {
            $t->integer('id')->primary();
            $t->integer('chapter_id');
            $t->integer('topic_id')->nullable();
            $t->string('name')->nullable();
            $t->text('definition')->nullable();
            $t->text('description')->nullable();
        });
        Schema::create('document_extractions', function ($t) {
            $t->integer('id')->primary();
            $t->integer('chapter_id');
            $t->string('document_type');
            $t->longText('md_content')->nullable();
        });
        (require base_path('database/migrations/2026_10_08_100000_create_lms_prayogshala_activity_table.php'))->up();
        (require base_path('database/migrations/2026_10_09_100000_add_generation_tracking_to_lms_prayogshala_activity_table.php'))->up();

        DB::table('school_setup')->insert([['Id' => 1, 'is_Lms' => 'Y'], ['Id' => 7, 'is_Lms' => 'Y'], ['Id' => 8, 'is_Lms' => 'N']]);
        DB::table('standard')->insert([['id' => 42, 'name' => '9']]);
        DB::table('subject')->insert([['id' => 3975, 'subject_name' => 'Science']]);
        DB::table('chapter_master')->insert([
            ['id' => 100, 'sub_institute_id' => 1, 'syear' => 2026, 'grade_id' => 12, 'standard_id' => 42, 'subject_id' => 3975, 'chapter_name' => 'Matter in Our Surroundings'],
            ['id' => 300, 'sub_institute_id' => 8, 'syear' => 2026, 'grade_id' => 12, 'standard_id' => 42, 'subject_id' => 3975, 'chapter_name' => 'B only chapter'],
        ]);
        DB::table('topic_master')->insert([
            ['id' => 11, 'chapter_id' => 100, 'name' => 'Vaporisation', 'description' => 'Learners study how a liquid changes to vapour on heating and what boiling means.', 'topic_sort_order' => 1],
            ['id' => 12, 'chapter_id' => 100, 'name' => 'Thin topic', 'description' => 'Short.', 'topic_sort_order' => 2],
            ['id' => 13, 'chapter_id' => 100, 'name' => 'Latent heat', 'description' => 'Learners study heat absorbed during a change of state without a rise in temperature.', 'topic_sort_order' => 3],
            ['id' => 31, 'chapter_id' => 300, 'name' => 'B topic', 'description' => 'A long enough description of a topic owned only by institute B.', 'topic_sort_order' => 1],
        ]);
        DB::table('lms_concept')->insert([
            ['id' => 5, 'chapter_id' => 100, 'topic_id' => 11, 'name' => 'Boiling point', 'definition' => 'The temperature at which a liquid boils.'],
            ['id' => 6, 'chapter_id' => 100, 'topic_id' => 11, 'name' => 'Evaporation', 'definition' => 'Surface change from liquid to vapour.'],
            ['id' => 7, 'chapter_id' => 100, 'topic_id' => 13, 'name' => 'Latent heat of vaporisation', 'definition' => 'Heat needed to change state.'],
            ['id' => 8, 'chapter_id' => 300, 'topic_id' => 31, 'name' => 'B concept', 'definition' => 'x'],
        ]);
        DB::table('document_extractions')->insert([
            ['id' => 1, 'chapter_id' => 100, 'document_type' => 'Chapter', 'md_content' => "Intro paragraph about everything that is unrelated to the topic at all and long enough.\n\nVaporisation happens when a liquid is heated and its boiling point is reached; evaporation occurs at the surface at any temperature.\n\nAnother unrelated paragraph that talks about something else entirely, still long enough to count."],
        ]);

        $this->ai = new FakeProvider();
        app()->instance(ClaudeQuestionClient::class, $this->ai);
    }

    // ----------------------------------------------------------------- fixtures

    /** A complete, valid generated activity (variable_model + heating visual). */
    public static function goodActivity(array $over = []): array
    {
        return array_merge([
            'title' => 'Heat the liquid: when does it boil?',
            'activity_type' => 'experiment',
            'description' => 'Heat a liquid and watch its temperature and vapour.',
            'objective' => 'Relate heating to vaporisation and the boiling point.',
            'materials_required' => ['Virtual burner', 'Virtual beaker'],
            'procedure_steps' => ['Set the heat.', 'Watch the temperature.'],
            'observation' => 'Record the temperature at which bubbles form.',
            'result' => 'Bubbles form throughout the liquid once the boiling point is reached.',
            'safety_instructions' => 'Real hot liquids need teacher supervision.',
            'estimated_minutes' => 20,
            'lab_config' => [
                'version' => 1,
                'simulation' => ['type' => 'variable_model', 'params' => [
                    'controls' => [['id' => 'heat', 'label' => 'Heat', 'unit' => '%', 'kind' => 'slider', 'min' => 0, 'max' => 100, 'step' => 5, 'default' => 0]],
                    'derived' => [['id' => 'temp', 'label' => 'Temperature', 'unit' => 'C', 'formula' => '25+heat*0.8', 'decimals' => 0]],
                    'visual' => ['kind' => 'heating', 'bind' => ['temperature' => 'temp', 'heat' => 'heat/100', 'boiling_point' => '100']],
                    'observations' => [
                        ['when' => 'temp>=100', 'text' => 'The liquid is boiling at {temp} C (model value).'],
                        ['when' => 'temp<100', 'text' => 'The liquid is at {temp} C and not boiling yet.'],
                    ],
                ]],
                'steps' => [
                    'mission' => ['scenario' => 'Heat a liquid.', 'task' => 'Find when it boils.'],
                    'predict' => ['question' => 'At full heat, does it boil?', 'scenario' => ['heat' => 100], 'options' => [
                        ['id' => 'a', 'label' => 'Yes', 'when' => 'temp>=100'],
                        ['id' => 'b', 'label' => 'No', 'when' => 'temp<100'],
                    ]],
                    'do' => ['instructions' => ['Move the heat slider.']],
                    'observe' => ['prompt' => 'Look at the beaker.'],
                    'explain' => ['text' => 'Heat raises temperature.', 'cases' => [['when' => 'temp>=100', 'text' => 'It reached the boiling point.']]],
                    'concept' => ['text' => 'Vaporisation.', 'points' => ['Boiling point']],
                    'apply' => ['question' => 'Which is vaporisation?', 'options' => [
                        ['id' => 'a', 'label' => 'Water boiling', 'correct' => true, 'feedback' => 'Yes.'],
                        ['id' => 'b', 'label' => 'Ice melting', 'correct' => false, 'feedback' => 'That is melting.'],
                    ]],
                    'reflect' => ['prompts' => ['What surprised you?']],
                ],
            ],
        ], $over);
    }

    private function generator(): PrayogshalaGenerator
    {
        return app(PrayogshalaGenerator::class);
    }

    private function rows(): \Illuminate\Support\Collection
    {
        return DB::table('lms_prayogshala_activity')->whereNull('deleted_at')->get();
    }

    private function act(string $method, array $input, int $tenant, string $profile = 'Teacher', bool $student = false, ?int $id = null)
    {
        $request = Request::create('/x', 'POST', $input);
        $store = app('session.store');
        $store->flush();
        $request->setLaravelSession($store);
        $store->put('sub_institute_id', $tenant);
        $store->put('user_id', 55);
        $store->put('user_profile_name', $profile);
        $store->put('is_student', $student);

        $args = ['request' => $request] + ($id === null ? [] : ['id' => $id]);
        $response = app()->call([app(PrayogshalaApiController::class), $method], $args);

        return [$response->getStatusCode(), $response->getData(true)];
    }

    // ---------------------------------------------------------------- validator

    public function test_a_wellformed_config_is_accepted(): void
    {
        $this->assertSame([], (new SimulationConfigValidator())->validate(self::goodActivity()['lab_config']));
    }

    public function test_the_validator_refuses_unknown_engines_unknown_facts_markup_and_two_correct_answers(): void
    {
        $v = new SimulationConfigValidator();
        $lab = self::goodActivity()['lab_config'];

        $bad = $lab;
        $bad['simulation']['type'] = 'run_javascript';
        $this->assertStringContainsString('Unknown simulation type', $v->validate($bad)[0]);

        $bad = $lab;
        $bad['simulation']['params']['derived'][0]['formula'] = 'secret*2';
        $this->assertStringContainsString("'secret'", implode(' ', $v->validate($bad)));

        $bad = $lab;
        $bad['simulation']['params']['derived'][0]['formula'] = 'heat; alert(1)';
        $this->assertStringContainsString('outside the expression language', implode(' ', $v->validate($bad)));

        $bad = $lab;
        $bad['steps']['mission']['scenario'] = 'Click <script>alert(1)</script>';
        $this->assertStringContainsString('plain text', implode(' ', $v->validate($bad)));

        $bad = $lab;
        $bad['steps']['apply']['options'][1]['correct'] = true;
        $this->assertStringContainsString('exactly one correct', implode(' ', $v->validate($bad)));

        $bad = $lab;
        unset($bad['steps']['reflect']);
        $this->assertNotSame([], $v->validate($bad));

        $bad = $lab;
        $bad['simulation']['params']['visual']['kind'] = 'iframe';
        $this->assertStringContainsString('visual.kind', implode(' ', $v->validate($bad)));
    }

    public function test_a_predict_condition_may_only_use_facts_the_engine_provides(): void
    {
        $lab = self::goodActivity()['lab_config'];
        $lab['steps']['predict']['options'][0]['when'] = 'rpm>3';

        $this->assertStringContainsString("'rpm'", implode(' ', (new SimulationConfigValidator())->validate($lab)));
    }

    // --------------------------------------------------------- source & honesty

    public function test_the_context_is_resolved_through_the_real_hierarchy_and_only_matching_passages_are_used(): void
    {
        $ctx = $this->generator()->context(11, 7);

        $this->assertSame('Science', $ctx['hierarchy']['subject_name']);
        $this->assertSame('9', $ctx['hierarchy']['standard_name']);
        $this->assertSame([5, 6], $ctx['source_refs']['concept_ids']);
        $this->assertSame([1], $ctx['source_refs']['extraction_ids']);
        $this->assertStringContainsString('boiling point is reached', $ctx['excerpt']);
        $this->assertStringNotContainsString('unrelated', $ctx['excerpt']);
        $this->assertTrue($ctx['sufficient']);
    }

    public function test_a_topic_the_institute_cannot_see_does_not_resolve(): void
    {
        $this->assertNull($this->generator()->context(31, 7), "institute 7 cannot see institute 8's topic");
        $this->assertNull($this->generator()->context(11, 8), 'institute 8 is not platform-linked');
        $this->assertNull($this->generator()->context(9999, 7));
    }

    public function test_thin_source_material_is_reported_not_papered_over(): void
    {
        $result = $this->generator()->generate(12, 1, 55);

        $this->assertSame('needs_content', $result['outcome']);
        $this->assertSame(0, $this->ai->calls, 'no model call is made when the source is insufficient');
        $row = $this->rows()->first();
        $this->assertSame('needs_content', $row->generation_status);
        $this->assertNull($row->lab_config);
        $this->assertSame('draft', $row->status);
        $this->assertStringContainsString('Not enough source material', $row->generation_error);
    }

    public function test_the_model_may_decline_and_that_is_recorded_as_needs_content(): void
    {
        $this->ai->queue(['insufficient' => true, 'reason' => 'Only definitions, nothing to manipulate.']);

        $result = $this->generator()->generate(11, 1, 55);

        $this->assertSame('needs_content', $result['outcome']);
        $this->assertNull($this->rows()->first()->lab_config);
    }

    // ----------------------------------------------------- generation, one per topic

    public function test_generation_files_one_reviewable_activity_against_the_topic_with_its_sources(): void
    {
        $this->ai->queue(self::goodActivity());

        $result = $this->generator()->generate(11, 1, 55);

        $this->assertSame('created', $result['outcome']);
        $row = $this->rows()->sole();
        $this->assertSame(11, (int) $row->topic_id);
        $this->assertSame(100, (int) $row->chapter_id);
        $this->assertSame(42, (int) $row->standard_id);
        $this->assertSame(3975, (int) $row->subject_id);
        $this->assertSame('review', $row->status, 'generated content waits for a teacher');
        $this->assertSame('ready', $row->generation_status);
        $this->assertSame(1, (int) $row->generation_version);
        $this->assertSame([5, 6], json_decode($row->concept_ids, true), 'one activity covers every concept of the topic');
        $this->assertNull($row->concept_id, 'a multi-concept topic has no single concept');
        $this->assertSame(64, strlen($row->source_hash));
        $this->assertSame([1], json_decode($row->source_refs, true)['extraction_ids']);
        $this->assertNotNull($row->lab_config);
        $this->assertNotNull($row->generated_at);
    }

    public function test_generating_twice_makes_no_second_activity_and_no_second_model_call(): void
    {
        $this->ai->queue(self::goodActivity());

        $first = $this->generator()->generate(11, 1, 55);
        $second = $this->generator()->generate(11, 1, 55);

        $this->assertSame('created', $first['outcome']);
        $this->assertSame('exists', $second['outcome']);
        $this->assertSame($first['activity_id'], $second['activity_id']);
        $this->assertSame(1, $this->ai->calls);
        $this->assertCount(1, $this->rows());
    }

    public function test_the_database_itself_refuses_a_second_live_activity_for_a_topic(): void
    {
        $this->ai->queue(self::goodActivity());
        $this->generator()->generate(11, 1, 55);

        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('lms_prayogshala_activity')->insert([
            'sub_institute_id' => 1, 'standard_id' => 42, 'subject_id' => 3975, 'chapter_id' => 100,
            'topic_id' => 11, 'topic_slot' => 11, 'title' => 'dup', 'slug' => 'other-slug',
        ]);
    }

    public function test_a_fresh_claim_by_another_worker_answers_busy_without_a_model_call(): void
    {
        DB::table('lms_prayogshala_activity')->insert([
            'sub_institute_id' => 1, 'standard_id' => 42, 'subject_id' => 3975, 'chapter_id' => 100,
            'topic_id' => 11, 'topic_slot' => 11, 'title' => 'Vaporisation', 'slug' => 'topic-11',
            'generation_status' => 'generating', 'status' => 'draft', 'updated_at' => now(),
        ]);

        $this->assertSame('busy', $this->generator()->generate(11, 1, 55)['outcome']);
        $this->assertSame(0, $this->ai->calls);
    }

    public function test_a_dead_claim_is_taken_over(): void
    {
        DB::table('lms_prayogshala_activity')->insert([
            'sub_institute_id' => 1, 'standard_id' => 42, 'subject_id' => 3975, 'chapter_id' => 100,
            'topic_id' => 11, 'topic_slot' => 11, 'title' => 'Vaporisation', 'slug' => 'topic-11',
            'generation_status' => 'generating', 'status' => 'draft', 'updated_at' => now()->subHour(),
        ]);
        $this->ai->queue(self::goodActivity());

        $this->assertSame('created', $this->generator()->generate(11, 1, 55)['outcome']);
        $this->assertCount(1, $this->rows());
    }

    // --------------------------------------------------------- failure and retry

    public function test_a_provider_failure_is_recorded_and_a_retry_reuses_the_same_row(): void
    {
        $this->ai->queue(['__error' => 'Claude is rate limiting this account. Try again shortly.']);
        $failed = $this->generator()->generate(11, 1, 55);

        $this->assertSame('failed', $failed['outcome']);
        $row = $this->rows()->sole();
        $this->assertSame('failed', $row->generation_status);
        $this->assertStringContainsString('rate limiting', $row->generation_error);
        $this->assertNull($row->lab_config, 'nothing fake is shown when the provider is down');

        $this->ai->queue(self::goodActivity());
        $retry = $this->generator()->generate(11, 1, 55);

        $this->assertSame('created', $retry['outcome']);
        $this->assertSame($failed['activity_id'], $retry['activity_id']);
        $row = $this->rows()->sole();
        $this->assertSame('ready', $row->generation_status);
        $this->assertSame(2, (int) $row->generation_attempts);
        $this->assertNull($row->generation_error);
    }

    public function test_an_invalid_config_gets_one_repair_round_then_fails(): void
    {
        $bad = self::goodActivity();
        $bad['lab_config']['simulation']['type'] = 'run_javascript';
        $this->ai->queue($bad);
        $this->ai->queue(self::goodActivity());

        $this->assertSame('created', $this->generator()->generate(11, 1, 55)['outcome']);
        $this->assertSame(2, $this->ai->calls);
        $this->assertStringContainsString('rejected by the validator', $this->ai->lastPrompt);
    }

    public function test_two_invalid_answers_fail_without_storing_the_content(): void
    {
        $bad = self::goodActivity();
        $bad['lab_config']['simulation']['type'] = 'run_javascript';
        $this->ai->queue($bad);
        $this->ai->queue($bad);

        $result = $this->generator()->generate(11, 1, 55);

        $this->assertSame('failed', $result['outcome']);
        $row = $this->rows()->sole();
        $this->assertNull($row->lab_config);
        $this->assertSame('failed', $row->generation_status);
    }

    // ------------------------------------------------------------- regeneration

    public function test_regeneration_is_explicit_replaces_content_and_returns_it_to_review(): void
    {
        $this->ai->queue(self::goodActivity());
        $first = $this->generator()->generate(11, 1, 55);
        DB::table('lms_prayogshala_activity')->where('id', $first['activity_id'])->update(['status' => 'published']);

        $this->ai->queue(self::goodActivity(['title' => 'A better title']));
        $second = $this->generator()->generate(11, 1, 55, true);

        $this->assertSame('regenerated', $second['outcome']);
        $row = $this->rows()->sole();
        $this->assertSame('A better title', $row->title);
        $this->assertSame(2, (int) $row->generation_version);
        $this->assertSame('review', $row->status, 'regenerated content must be reviewed again');
        $this->assertSame($first['activity_id'], (int) $row->id);
    }

    public function test_a_failed_regeneration_keeps_the_existing_activity_intact(): void
    {
        $this->ai->queue(self::goodActivity());
        $first = $this->generator()->generate(11, 1, 55);
        DB::table('lms_prayogshala_activity')->where('id', $first['activity_id'])->update(['status' => 'published']);

        $this->ai->queue(['__error' => 'Claude is temporarily unavailable (HTTP 503).']);
        $this->assertSame('failed', $this->generator()->generate(11, 1, 55, true)['outcome']);

        $row = $this->rows()->sole();
        $this->assertSame('published', $row->status, 'learners keep what they had');
        $this->assertSame('ready', $row->generation_status);
        $this->assertNotNull($row->lab_config);
        $this->assertStringContainsString('503', $row->generation_error);
    }

    // ------------------------------------------------------------------ tenancy

    public function test_a_platform_linked_institute_sees_the_platform_activity_and_cannot_regenerate_it(): void
    {
        $this->ai->queue(self::goodActivity());
        $this->generator()->generate(11, 1, 55);

        $this->assertSame('exists', $this->generator()->generate(11, 7, 55)['outcome']);
        $this->assertSame('forbidden', $this->generator()->generate(11, 7, 55, true)['outcome']);
        $this->assertSame(1, $this->ai->calls);
        $this->assertCount(1, $this->rows(), 'no second activity is created for the same topic');
    }

    public function test_an_unlinked_institute_cannot_generate_for_the_platforms_topic_or_see_it(): void
    {
        $this->assertSame('not_found', $this->generator()->generate(11, 8, 55)['outcome']);
        $this->assertCount(0, $this->rows());
    }

    public function test_an_institute_generates_for_its_own_chapter_and_owns_the_row(): void
    {
        $this->ai->queue(self::goodActivity());

        $this->assertSame('created', $this->generator()->generate(31, 8, 77)['outcome']);
        $this->assertSame(8, (int) $this->rows()->sole()->sub_institute_id);
    }

    // ----------------------------------------------------------------- endpoints

    public function test_the_generate_endpoint_is_for_staff_only(): void
    {
        [$status] = $this->act('generate', ['topic_id' => 11], 1, 'Student', true);
        $this->assertSame(403, $status);
        $this->assertSame(0, $this->ai->calls);

        $this->ai->queue(self::goodActivity());
        [$status, $body] = $this->act('generate', ['topic_id' => 11], 1, 'Teacher');
        $this->assertSame(200, $status);
        $this->assertSame('created', $body['data']['outcome']);
        $this->assertSame('review', $body['data']['activity']['status']);

        [, $again] = $this->act('generate', ['topic_id' => 11], 1, 'Teacher');
        $this->assertSame('exists', $again['data']['outcome']);
        $this->assertSame(1, $this->ai->calls);
    }

    public function test_the_generate_endpoint_reports_a_provider_failure_as_a_failure(): void
    {
        $this->ai->queue(['__error' => 'The Claude API key was rejected. Check ANTHROPIC_API_KEY.']);

        [$status, $body] = $this->act('generate', ['topic_id' => 11], 1, 'Teacher');

        $this->assertSame(502, $status);
        $this->assertSame(0, $body['status_code']);
        $this->assertSame('failed', $body['data']['outcome']);
    }

    public function test_the_generate_endpoint_validates_the_topic(): void
    {
        [$status] = $this->act('generate', [], 1);
        $this->assertSame(422, $status);
        [$status] = $this->act('generate', ['topic_id' => 31], 7);
        $this->assertSame(404, $status, "another institute's topic");
    }

    public function test_manual_creation_cannot_add_a_second_activity_to_a_topic_and_delete_frees_it(): void
    {
        $input = ['chapter_id' => 100, 'topic_id' => 13, 'title' => 'By hand'];

        [$status, $body] = $this->act('store', $input, 1);
        $this->assertSame(201, $status);

        [$status, $dup] = $this->act('store', ['title' => 'Second'] + $input, 1);
        $this->assertSame(422, $status);
        $this->assertStringContainsString('already has a Prayogshala activity', $dup['errors']['topic_id'][0]);

        $this->act('destroy', [], 1, 'Teacher', false, $body['data']['id']);
        [$status] = $this->act('store', ['title' => 'Replacement', 'slug' => 'replacement'] + $input, 1);
        $this->assertSame(201, $status);
    }

    public function test_a_stored_lab_config_goes_through_the_same_validator(): void
    {
        $lab = self::goodActivity()['lab_config'];
        $lab['simulation']['type'] = 'run_javascript';

        [$status, $body] = $this->act('store', ['chapter_id' => 100, 'title' => 'X', 'lab_config' => $lab], 1);

        $this->assertSame(422, $status);
        $this->assertStringContainsString('Unknown simulation type', $body['errors']['lab_config'][0]);
    }

    public function test_content_that_failed_generation_cannot_be_published(): void
    {
        $this->generator()->generate(12, 1, 55); // needs_content
        $id = (int) $this->rows()->sole()->id;

        [$status, $body] = $this->act('update', ['status' => 'published'], 1, 'Teacher', false, $id);

        $this->assertSame(422, $status);
        $this->assertStringContainsString('cannot be published', $body['errors']['status'][0]);
    }

    public function test_the_topic_list_reports_every_topic_with_its_real_state_and_hides_unpublished_from_learners(): void
    {
        $this->ai->queue(self::goodActivity());
        $this->generator()->generate(11, 1, 55);      // ready (review)
        $this->generator()->generate(12, 1, 55);      // needs_content
        // topic 13: never generated

        [, $staff] = $this->act('index', ['chapter_id' => 100], 1);
        $states = array_column($staff['data']['topic_coverage'], 'state', 'topic_id');
        $this->assertEquals([11 => 'ready', 12 => 'needs_content', 13 => 'not_generated'], $states);
        $this->assertSame(2, $staff['data']['topic_coverage'][0]['concept_count']);

        [, $learner] = $this->act('index', ['chapter_id' => 100], 1, 'Student', true);
        $this->assertSame([], $learner['data']['topic_coverage'], 'nothing is published yet');
        $this->assertSame([], $learner['data']['activities']);

        DB::table('lms_prayogshala_activity')->where('topic_id', 11)->update(['status' => 'published']);
        [, $learner] = $this->act('index', ['chapter_id' => 100], 1, 'Student', true);
        $this->assertSame([11], array_column($learner['data']['topic_coverage'], 'topic_id'));
        $this->assertNull($learner['data']['topic_coverage'][0]['activity']['generation_error']);
        $this->assertNull($learner['data']['topic_coverage'][0]['activity']['source_refs']);
    }

    public function test_the_index_can_narrow_to_one_topic_and_refuses_a_topic_of_another_chapter(): void
    {
        $this->ai->queue(self::goodActivity());
        $this->generator()->generate(11, 1, 55);

        [, $body] = $this->act('index', ['chapter_id' => 100, 'topic_id' => 11], 1);
        $this->assertCount(1, $body['data']['activities']);
        [, $body] = $this->act('index', ['chapter_id' => 100, 'topic_id' => 13], 1);
        $this->assertCount(0, $body['data']['activities']);

        [$status] = $this->act('index', ['chapter_id' => 100, 'topic_id' => 31], 1);
        $this->assertSame(422, $status);
    }

    public function test_a_placeholder_is_a_topic_state_not_an_activity_card(): void
    {
        $this->generator()->generate(12, 1, 55); // needs_content placeholder
        $this->ai->queue(self::goodActivity());
        $this->generator()->generate(11, 1, 55); // real activity

        [, $body] = $this->act('index', ['chapter_id' => 100], 1);
        $this->assertSame([11], array_column($body['data']['activities'], 'topic_id'));
        $this->assertSame('needs_content', array_column($body['data']['topic_coverage'], 'state', 'topic_id')[12]);

        $chapter = app(PrayogshalaService::class)->visibleChapter(100, 1);
        $assets = app(PrayogshalaService::class)->assetsForChapter($chapter, 1, false);
        $this->assertSame([11], array_column($assets, 'topic_id'), 'the content list never carries the placeholder');
    }

    public function test_a_chapter_with_no_activities_returns_an_honest_empty_state_but_still_lists_its_topics(): void
    {
        [$status, $body] = $this->act('index', ['chapter_id' => 100], 1);
        $this->assertSame(200, $status);
        $this->assertSame([], $body['data']['activities']);
        $this->assertSame(['not_generated'], array_values(array_unique(array_column($body['data']['topic_coverage'], 'state'))));

        // A learner gets nothing at all: no topic rows without a published activity.
        [, $learner] = $this->act('index', ['chapter_id' => 100], 1, 'Student', true);
        $this->assertSame([], $learner['data']['activities']);
        $this->assertSame([], $learner['data']['topic_coverage']);
    }

    public function test_placeholders_never_reach_a_learner_or_another_institute_even_when_published_flags_are_set(): void
    {
        $this->generator()->generate(12, 1, 55); // needs_content
        DB::table('lms_prayogshala_activity')->update(['status' => 'published', 'show_hide' => 1]);

        [, $learner] = $this->act('index', ['chapter_id' => 100], 7, 'Student', true);
        $this->assertSame([], $learner['data']['activities'], 'published flag alone does not make a placeholder real');
        $this->assertSame([], $learner['data']['topic_coverage']);

        $chapter = app(PrayogshalaService::class)->visibleChapter(100, 7);
        $this->assertSame([], app(PrayogshalaService::class)->assetsForChapter($chapter, 7, true));

        [$status] = $this->act('index', ['chapter_id' => 100], 8);
        $this->assertSame(404, $status, 'an institute that cannot see the chapter cannot see its topics either');
    }

    public function test_each_chapter_lists_only_its_own_activities(): void
    {
        DB::table('chapter_master')->insert(['id' => 101, 'sub_institute_id' => 1, 'syear' => 2026, 'grade_id' => 12, 'standard_id' => 42, 'subject_id' => 3975, 'chapter_name' => 'Second chapter']);
        DB::table('topic_master')->insert(['id' => 41, 'chapter_id' => 101, 'name' => 'Other topic', 'description' => 'A long enough description for the other chapter topic here.']);
        DB::table('lms_concept')->insert(['id' => 51, 'chapter_id' => 101, 'topic_id' => 41, 'name' => 'Other concept', 'definition' => 'Defined.']);
        $this->ai->queue(self::goodActivity());
        $this->generator()->generate(11, 1, 55);
        $this->ai->queue(self::goodActivity(['title' => 'Second chapter lab']));
        $this->generator()->generate(41, 1, 55);

        [, $a] = $this->act('index', ['chapter_id' => 100], 1);
        [, $b] = $this->act('index', ['chapter_id' => 101], 1);

        $this->assertSame([100], array_values(array_unique(array_column($a['data']['activities'], 'chapter_id'))));
        $this->assertSame(['Second chapter lab'], array_column($b['data']['activities'], 'title'));
    }

    public function test_coverage_counts_come_from_the_table_and_paginate(): void
    {
        $this->ai->queue(self::goodActivity());
        $this->generator()->generate(11, 1, 55);
        $this->generator()->generate(12, 1, 55);

        [, $body] = $this->act('coverage', ['standard_id' => 42, 'subject_id' => 3975], 1);
        $chapter = $body['data']['chapters'][0];

        $this->assertSame(100, $chapter['chapter_id']);
        $this->assertSame(3, $chapter['topics']);
        $this->assertSame(1, $chapter['states']['ready']);
        $this->assertSame(1, $chapter['states']['needs_content']);
        $this->assertSame(1, $chapter['not_generated']);
        $this->assertSame(1, $body['data']['pagination']['total']);
    }

    public function test_an_own_activity_wins_over_the_platform_one_for_the_same_topic(): void
    {
        $base = ['standard_id' => 42, 'subject_id' => 3975, 'chapter_id' => 100, 'topic_id' => 13, 'status' => 'published', 'show_hide' => 1];
        DB::table('lms_prayogshala_activity')->insert($base + ['sub_institute_id' => 1, 'topic_slot' => 13, 'title' => 'Platform']);
        DB::table('lms_prayogshala_activity')->insert($base + ['sub_institute_id' => 7, 'topic_slot' => 13, 'title' => 'Own', 'slug' => 'own']);

        $chapter = app(PrayogshalaService::class)->visibleChapter(100, 7);
        $titles = app(PrayogshalaService::class)->rowsForChapter($chapter, 7, true)->pluck('title')->all();

        $this->assertSame(['Own'], $titles);
    }
}

/** A stand-in for the Claude client: returns queued replies, counts calls, never touches the network. */
class FakeProvider extends ClaudeQuestionClient
{
    public int $calls = 0;
    public string $lastPrompt = '';
    /** @var list<array<string,mixed>> */
    private array $replies = [];

    public function __construct()
    {
        parent::__construct(fn () => 'test-key');
    }

    public function queue(array $reply): void
    {
        $this->replies[] = $reply;
    }

    public function complete(string $system, string $user, int|string|null $subInstituteId = null): array
    {
        $this->calls++;
        $this->lastPrompt = $user;
        $reply = array_shift($this->replies);
        if ($reply === null) {
            return ['ok' => false, 'error' => 'FakeProvider: no reply queued.'];
        }
        if (isset($reply['__error'])) {
            return ['ok' => false, 'error' => $reply['__error']];
        }

        return ['ok' => true, 'content' => json_encode($reply), 'model' => 'fake-model', 'finish_reason' => 'end_turn', 'usage' => []];
    }
}
