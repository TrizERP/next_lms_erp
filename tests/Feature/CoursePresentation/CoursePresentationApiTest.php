<?php

namespace Tests\Feature\CoursePresentation;

use Illuminate\Support\Facades\DB;

class CoursePresentationApiTest extends CoursePresentationTestCase
{
    private function preflight(array $query)
    {
        return $this->getJson('/api/lms/course-presentation/preflight?' . http_build_query($query));
    }

    private function valid(array $override = []): array
    {
        return $override + ['chapter_id' => self::CHAPTER, 'pedagogy' => 'inquiry_based', 'format' => 'interactive_slides'];
    }

    /** Suitability of one method for the pilot chapter, from the options answer (always 200, for every method). */
    private function suit(string $method): array
    {
        return $this->asTeacher()->getJson('/api/lms/course-presentation/options?chapter_id=' . self::CHAPTER)->assertOk()->json('suitability')[$method];
    }

    private function fit(string $pedagogy, string $format): array
    {
        return $this->asTeacher()->getJson('/api/lms/course-presentation/fit?' . http_build_query(['chapter_id' => self::CHAPTER, 'pedagogy' => $pedagogy, 'format' => $format]))->assertOk()->json('fit');
    }

    private function existing(array $json, string $key): array
    {
        foreach ($json['existing_content'] as $s) {
            if ($s['key'] === $key) {
                return $s;
            }
        }
        $this->fail("No existing-content entry $key");
    }

    /** The textbook is a hidden fallback: read from the registry, because the screen never lists it. */
    private function textbookEntry(): array
    {
        $loader = app(\App\Services\CoursePresentation\SourceLoader::class);
        $chapter = $loader->chapter(self::CHAPTER, self::TENANT);
        $built = (new \App\Services\CoursePresentation\SourceRegistry((int) config('course_presentation_pilot.min_text_chars', 2000)))->build($chapter, $loader->load($chapter));
        foreach ($built['sources'] as $s) {
            if ($s['key'] === 'textbook') {
                return $s;
            }
        }
        $this->fail('No textbook entry in the registry');
    }

    private function source(array $json, string $key): array
    {
        foreach ($json['sources'] as $s) {
            if ($s['key'] === $key) {
                return $s;
            }
        }
        $this->fail("No source $key");
    }

    public function test_the_chapter_resolves_to_standard_42_subject_3975_and_tenant_1(): void
    {
        $this->seedPilotChapter();
        $json = $this->asTeacher()->preflight($this->valid())->assertOk()->json();
        $this->assertSame(self::CHAPTER, $json['chapter']['id']);
        $chapter = DB::table('chapter_master')->find(self::CHAPTER);
        $this->assertSame([42, 3975, 1], [$chapter->standard_id, $chapter->subject_id, $chapter->sub_institute_id]);
    }

    public function test_options_lists_exactly_ten_pedagogies_and_seven_formats(): void
    {
        $this->seedPilotChapter();
        $json = $this->asTeacher()->getJson('/api/lms/course-presentation/options?chapter_id=' . self::CHAPTER)->assertOk()->json();
        $this->assertCount(10, $json['pedagogies']);
        $this->assertCount(7, $json['formats']);
        $this->assertContains('Gamified learning', array_column($json['pedagogies'], 'label'));
        $this->assertContains('Interactive Socratic dialogue', array_column($json['formats'], 'label'));
    }

    public function test_both_choices_are_required_and_must_be_known(): void
    {
        $this->seedPilotChapter();
        $this->asTeacher()->preflight(['chapter_id' => self::CHAPTER, 'format' => 'interactive_slides'])->assertStatus(422);
        $this->asTeacher()->preflight(['chapter_id' => self::CHAPTER, 'pedagogy' => 'inquiry_based'])->assertStatus(422);
        $this->asTeacher()->preflight($this->valid(['pedagogy' => 'made_up']))->assertStatus(422);
        $this->asTeacher()->preflight($this->valid(['format' => 'made_up']))->assertStatus(422);
    }

    public function test_the_preflight_reads_only_and_says_so(): void
    {
        $this->seedPilotChapter();
        $before = [DB::table('content_master')->count(), DB::table('semantic_intelligence')->count(), DB::table('lms_prayogshala_activity')->count()];
        $queries = [];
        DB::listen(function ($q) use (&$queries) { $queries[] = strtolower(ltrim($q->sql)); });

        $json = $this->asTeacher()->preflight($this->valid())->assertOk()->json();

        $this->assertTrue($json['wrote_nothing']);
        $this->assertSame([], array_filter($queries, fn ($sql) => !str_starts_with($sql, 'select')), 'the pre-flight must only SELECT');
        $this->assertSame($before, [DB::table('content_master')->count(), DB::table('semantic_intelligence')->count(), DB::table('lms_prayogshala_activity')->count()]);
    }

    public function test_sources_are_reported_with_their_real_status(): void
    {
        $this->seedPilotChapter();
        $json = $this->asTeacher()->preflight($this->valid())->assertOk()->json();

        $this->assertSame('present', $this->textbookEntry()['status']);
        $this->assertNotContains('textbook', array_column($json['sources'], 'key'), 'the textbook is never listed on the screen');
        $this->assertSame('present', $this->source($json, 'curriculum')['status']);
        $outcomes = $this->source($json, 'outcomes');
        $this->assertSame('present', $outcomes['status']);
        $this->assertStringContainsString('2 learning outcome', $outcomes['summary']);
        // Semantic intelligence names 1 of the chapter's 2 concepts: incomplete, never "present".
        $semantic = $this->source($json, 'semantic_intelligence');
        $this->assertSame('incomplete', $semantic['status']);
        $this->assertStringContainsString('1 of the chapter', $semantic['summary']);
        // No separate content-intelligence source exists; no chapter content is stored.
        // Content Intelligence is the same data as Semantic Intelligence: one category, never a second Missing one.
        $this->assertNotContains('content_intelligence', array_column($json['sources'], 'key'));
        $this->assertSame(1, count(array_filter($json['sources'], fn ($x) => $x['key'] === 'semantic_intelligence')));
        // Whether a presentation is stored is not source material, and is reported apart.
        $this->assertSame('missing', $this->existing($json, 'chapter_content')['status']);
        $this->assertNotContains('chapter_content', array_column($json['sources'], 'key'));
        $this->assertNotContains('study_deck_bundle', array_column($json['sources'], 'key'));
        $this->assertSame([], $json['existing_resources']);
    }

    public function test_prayogshala_activities_are_listed_as_not_used_and_never_as_a_source(): void
    {
        $this->seedPilotChapter();
        $json = $this->asTeacher()->preflight($this->valid())->assertOk()->json();

        $this->assertSame('prayogshala', $json['not_used'][0]['key']);
        $this->assertSame(2, $json['not_used'][0]['count']);
        $this->assertNotContains('prayogshala', array_column($json['sources'], 'key'));
        $this->assertSame('missing', $this->existing($json, 'chapter_content')['status'], 'labs are not Course Presentation content');
    }

    public function test_a_short_textbook_is_incomplete_and_a_second_one_is_a_conflict(): void
    {
        $this->seedPilotChapter();
        DB::table('document_extractions')->where('id', 81)->update(['md_content' => 'Too short']);
        $this->assertSame('incomplete', $this->textbookEntry()['status']);

        DB::table('document_extractions')->where('id', 81)->update(['md_content' => str_repeat('x ', 3000)]);
        DB::table('document_extractions')->insert(['id' => 90, 'document_type' => 'Chapter', 'chapter_id' => self::CHAPTER, 'standard_id' => self::STANDARD, 'subject_id' => self::SUBJECT, 'sub_institute_id' => self::TENANT, 'md_content' => str_repeat('y ', 3000)]);
        $this->assertSame('conflicting', $this->textbookEntry()['status']);
    }

    public function test_material_filed_under_another_subject_standard_or_school_is_rejected_not_attached(): void
    {
        $this->seedPilotChapter();
        // Same chapter id, wrong subject / wrong school / wrong standard.
        DB::table('semantic_intelligence')->insert(['id' => 14, 'extraction_id' => 81, 'sub_institute_id' => self::TENANT, 'standard_id' => self::STANDARD, 'subject_id' => 9999, 'chapter_id' => self::CHAPTER, 'full_intelegance_json' => '{}']);
        DB::table('document_extractions')->insert(['id' => 91, 'document_type' => 'Chapter', 'chapter_id' => self::CHAPTER, 'standard_id' => self::STANDARD, 'subject_id' => self::SUBJECT, 'sub_institute_id' => self::OTHER_TENANT, 'md_content' => str_repeat('z ', 3000)]);
        DB::table('lms_learning_outcomes')->insert(['id' => 5000, 'curriculum_id' => 82, 'standard_id' => 41, 'subject_id' => self::SUBJECT, 'chapter_id' => self::CHAPTER, 'type' => 'learning_outcome', 'code' => 'LO-X']);
        DB::table('content_master')->insert(['id' => 1, 'standard_id' => self::STANDARD, 'subject_id' => self::SUBJECT, 'chapter_id' => self::CHAPTER, 'sub_institute_id' => self::OTHER_TENANT, 'content_category' => 'Classroom Presentation', 'title' => 'Other school', 'show_hide' => 1]);

        $json = $this->asTeacher()->preflight($this->valid())->assertOk()->json();

        $semantic = $this->source($json, 'semantic_intelligence');
        $this->assertSame(1, $semantic['rejected']);
        $this->assertSame([13], array_column($semantic['records'], 'ref'));
        $textbook = $this->textbookEntry();
        $this->assertSame(1, $textbook['rejected']);
        $this->assertSame([81], array_column($textbook['records'], 'ref'));
        $this->assertSame(1, $this->source($json, 'outcomes')['rejected']);
        $this->assertSame('missing', $this->existing($json, 'chapter_content')['status']);
        $this->assertSame([], $json['existing_resources']);
    }

    /** Four analysed concepts: Activity x4, Inquiry x3, Game x1; nothing recommends Project or Flipped. */
    private function seedStrategies(): void
    {
        $rec = fn (string ...$names) => array_map(fn ($n) => ['strategy' => $n, 'why_effective' => 'x'], $names);
        $entries = [
            ['concept' => ['concept_name' => 'Atom'], 'pedagogy_recommendations' => $rec('Activity Based Teaching', 'Inquiry Based Teaching')],
            ['concept' => ['concept_name' => 'Ion'], 'pedagogy_recommendations' => $rec('Activity Based Teaching', 'Inquiry Based Teaching')],
            ['concept' => ['concept_name' => 'Mole'], 'pedagogy_recommendations' => $rec('Activity Based Teaching', 'Inquiry Based Teaching', 'Game Based Teaching')],
            ['concept' => ['concept_name' => 'Bond'], 'pedagogy_recommendations' => $rec('Activity Based Teaching')],
        ];
        DB::table('semantic_intelligence')->where('id', 13)->update(['full_intelegance_json' => json_encode(['concepts' => $entries])]);
    }

    public function test_a_method_the_chapter_analysis_recommends_is_confirmed_suitable(): void
    {
        $this->seedPilotChapter();
        $this->seedStrategies();
        foreach (['inquiry_based' => 3, 'experiential' => 4] as $method => $count) {
            $s = $this->suit($method);
            $this->assertSame('suitable', $s['verdict'], $method);
            $this->assertSame($count, $s['concepts_recommending']);
            $this->assertSame(4, $s['concepts_analysed']);
            $this->assertStringContainsString('is a suitable fit', $s['message']);
            $this->assertNull($s['recommendation']);
        }
    }

    public function test_a_method_the_analysis_does_not_recommend_is_not_suitable_and_a_better_one_is_named(): void
    {
        $this->seedPilotChapter();
        $this->seedStrategies();
        foreach (['project_based', 'flipped_classroom', 'gamified'] as $method) {
            $s = $this->suit($method);
            $this->assertSame('not_suitable', $s['verdict'], $method);
            $this->assertStringContainsString('is not a suitable fit', $s['message']);
            // Best-supported first: Experiential (4 of 4), then Inquiry (3 of 4).
            $this->assertSame(['experiential', 'inquiry_based'], array_column($s['recommendation'], 'key'), $method);
            $this->assertStringContainsString('A more appropriate choice is Experiential / learning by doing (recommended for 4 of 4 concepts) or Inquiry-based learning', $s['message']);
        }
    }

    public function test_a_method_the_analysis_cannot_judge_is_not_called_suitable(): void
    {
        $this->seedPilotChapter();
        $this->seedStrategies();
        foreach (['explicit_instruction', 'problem_based', 'collaborative', 'socratic', 'differentiated_mastery'] as $method) {
            $s = $this->suit($method);
            $this->assertSame('unconfirmed', $s['verdict'], $method);
            $this->assertStringContainsString('not confirmed as a suitable fit', $s['message']);
            $this->assertNotEmpty($s['recommendation']);
        }
    }

    public function test_options_carries_the_verdict_for_every_method(): void
    {
        $this->seedPilotChapter();
        $this->seedStrategies();
        $json = $this->asTeacher()->getJson('/api/lms/course-presentation/options?chapter_id=' . self::CHAPTER)->assertOk()->json();
        $this->assertCount(10, $json['suitability']);
        $this->assertSame('suitable', $json['suitability']['inquiry_based']['verdict']);
        $this->assertSame('not_suitable', $json['suitability']['project_based']['verdict']);
    }

    public function test_without_a_readable_analysis_suitability_is_unknown_not_suitable(): void
    {
        $this->seedPilotChapter();
        DB::table('semantic_intelligence')->where('id', 13)->update(['full_intelegance_json' => 'not json']);
        $s = $this->suit('inquiry_based');
        $this->assertSame('unknown', $s['verdict']);
        $this->assertNull($s['recommendation']);
        // Analysis filed under another subject is ignored, so it cannot make a method look suitable.
        $this->seedStrategies();
        DB::table('semantic_intelligence')->where('id', 13)->update(['subject_id' => 9999]);
        $s = $this->suit('inquiry_based');
        $this->assertSame('unknown', $s['verdict']);
    }

    public function test_a_missing_presentation_never_makes_valid_sources_look_missing(): void
    {
        $this->seedPilotChapter();
        $json = $this->asTeacher()->preflight($this->valid())->assertOk()->json();

        $this->assertSame('missing', $this->existing($json, 'chapter_content')['status']);
        $this->assertSame('present', $this->textbookEntry()['status']);
        foreach (['curriculum', 'outcomes'] as $key) {
            $s = $this->source($json, $key);
            $this->assertSame('present', $s['status'], $key);
            $this->assertSame(['available', 'complete'], [$s['availability'], $s['quality']], $key);
            $this->assertFalse($s['needed_for_slides'], "$key is optional context, not a requirement");
        }
        // Three separate questions: semantic intelligence is AVAILABLE but only PARTLY good, and slides do not need it.
        $sem = $this->source($json, 'semantic_intelligence');
        $this->assertSame(['available', 'partial', false], [$sem['availability'], $sem['quality'], $sem['needed_for_slides']]);
        // Every concept is still accounted for: the one without intelligence is explained from the textbook where it can be, and reported where it cannot.
        $this->assertStringContainsString('Every concept is still explained', $sem['summary']);
        $this->assertStringContainsString('1 could not be tied to any source', $sem['summary']);
    }

    public function test_content_and_semantic_intelligence_are_one_source_with_verified_coverage_and_fields(): void
    {
        $this->seedPilotChapter();
        $rec = fn (string ...$n) => array_map(fn ($x) => ['strategy' => $x], $n);
        DB::table('semantic_intelligence')->where('id', 13)->update(['full_intelegance_json' => json_encode(['concepts' => [
            ['concept' => ['concept_name' => 'Atom'], 'pedagogy_recommendations' => $rec('Inquiry Based Teaching', 'Activity Based Teaching'), 'misconceptions' => [['wrong_idea' => 'a'], ['wrong_idea' => 'b']], 'real_world_applications' => ['x'], 'learning_outcomes' => [], 'assessment_blueprint' => null],
            ['concept' => ['concept_name' => 'Mole'], 'pedagogy_recommendations' => $rec('Inquiry Based Teaching'), 'misconceptions' => [], 'real_world_applications' => ['y', 'z']],
        ]])]);
        $json = $this->asTeacher()->preflight($this->valid())->assertOk()->json();

        // One category, named for both, reported once.
        $sem = array_values(array_filter($json['sources'], fn ($x) => str_contains(strtolower($x['label']), 'intelligence')));
        $this->assertCount(1, $sem);
        $this->assertSame('Content intelligence / Semantic intelligence', $sem[0]['label']);
        $this->assertSame('semantic_intelligence', $sem[0]['key']);
        $this->assertNotContains('missing', array_column($sem, 'status'));
        // Available, partial coverage: 1 of the chapter's 2 concepts matches by name.
        $this->assertSame(['incomplete', 'available', 'partial', 'Available — partial coverage'], [$sem[0]['status'], $sem[0]['availability'], $sem[0]['quality'], $sem[0]['status_label']]);
        $this->assertStringContainsString('are the same stored data', $sem[0]['summary']);
        $this->assertStringContainsString('1 of the chapter\'s 2 concepts have their own intelligence (1 by exact name, 0 by a contained name)', $sem[0]['summary']);
        $this->assertSame(['intelligence_concepts' => 2, 'matched' => 1, 'chapter_concepts' => 2], array_intersect_key($sem[0]['coverage'], array_flip(['intelligence_concepts', 'matched', 'chapter_concepts'])));
        // Only fields that really hold data are reported, with real counts; empty or null ones are not claimed.
        $fields = array_column($sem[0]['coverage']['fields'], null, 'field');
        $this->assertSame(['concepts' => 2, 'items' => 3], array_intersect_key($fields['teaching strategies'], array_flip(['concepts', 'items'])));
        $this->assertSame(['concepts' => 1, 'items' => 2], array_intersect_key($fields['misconceptions'], array_flip(['concepts', 'items'])));
        $this->assertSame(['concepts' => 2, 'items' => 3], array_intersect_key($fields['real-world examples'], array_flip(['concepts', 'items'])));
        $this->assertArrayNotHasKey('learning outcomes', $fields, 'an empty list is not data');
        $this->assertArrayNotHasKey('assessment blueprint', $fields, 'null is not data');
        $this->assertContains('learning outcomes', $sem[0]['coverage']['without_data']);
        $this->assertStringContainsString('No data for:', $sem[0]['summary']);
    }

    public function test_full_coverage_is_plain_available_and_missing_intelligence_is_missing_only_when_there_is_none(): void
    {
        $this->seedPilotChapter();
        DB::table('lms_concept')->where('name', 'Ion')->update(['name' => 'Mole']);
        DB::table('lms_concept')->insert(['id' => 3, 'chapter_id' => self::CHAPTER, 'name' => 'Bond']);
        DB::table('lms_concept')->where('id', 2)->delete();   // concepts: Atom, Bond  (the seeded analysis names Atom, Mole, Bond)
        $sem = fn () => $this->source($this->asTeacher()->preflight($this->valid())->assertOk()->json(), 'semantic_intelligence');

        $s = $sem();
        $this->assertSame(['present', 'Available'], [$s['status'], $s['status_label']]);
        $this->assertStringContainsString('covers all 2 of the chapter\'s concepts', $s['summary']);

        // Only when there is truly no row for the chapter is it Missing.
        DB::table('semantic_intelligence')->delete();
        $this->assertSame(['missing', 'unavailable'], array_values(array_intersect_key($sem(), array_flip(['status', 'availability']))));
        $this->assertNotContains('content_intelligence', array_column($this->asTeacher()->preflight($this->valid())->json('sources') ?? [], 'key'));
    }
    public function test_an_earlier_deck_that_failed_validation_is_reported_with_its_problem_and_never_as_reusable(): void
    {
        $this->seedPilotChapter();
        // The earlier bundle's report is read from a scratch folder, never from the real stored decks' folder.
        config(['course_presentation_pilot.earlier_bundle_base' => $this->runDir . '/earlier']);
        $dir = storage_path($this->runDir . '/earlier/chapter-' . self::CHAPTER);
        mkdir($dir, 0775, true);
        $deck = fn () => $this->existing($this->asTeacher()->preflight($this->valid())->assertOk()->json(), 'study_deck_bundle');

        file_put_contents($dir . '/report.json', json_encode(['ok' => false, 'errors' => ['Numbers in slide text that are not in the chapter text: 124 (C:\\x\\storage\\y)']]));
        $d = $deck();
        $this->assertSame('incomplete', $d['status']);
        $this->assertStringContainsString('did not pass validation', $d['summary']);
        $this->assertStringContainsString('not in the chapter text: 124', $d['summary']);
        $this->assertStringContainsString('without a teaching method', $d['summary']);
        $this->assertStringContainsString('may be repairable', $d['summary']);
        $this->assertStringNotContainsString('storage', $d['summary']);
        $this->assertStringContainsString('never offered as reusable', $d['impact']);

        // Two problems: no repair hint.
        file_put_contents($dir . '/report.json', json_encode(['ok' => false, 'errors' => ['a', 'b']]));
        $this->assertStringNotContainsString('repairable', $deck()['summary']);

        // Even a deck that passed is never reused as it stands.
        file_put_contents($dir . '/report.json', json_encode(['ok' => true, 'errors' => [], 'teaching_method' => ['key' => 'inquiry_based']]));
        $d = $deck();
        $this->assertSame('present', $d['status']);
        $this->assertStringContainsString('for a teaching method', $d['summary']);
        $this->assertStringContainsString('never reused as it stands', $d['impact']);
    }

    public function test_a_client_cannot_name_sources(): void
    {
        $this->seedPilotChapter();
        $this->asTeacher()->preflight($this->valid(['sources' => [['type' => 'semantic_intelligence', 'ref' => 999]]]))->assertStatus(422);
        $this->asTeacher()->preflight($this->valid(['source_ids' => [1]]))->assertStatus(422);
    }

    public function test_a_suitable_combination_is_confirmed_and_may_continue(): void
    {
        $this->seedPilotChapter();
        $this->seedStrategies();
        $fit = $this->fit('inquiry_based', 'interactive_slides');
        $this->assertSame('suitable', $fit['fit_status']);
        $this->assertTrue($fit['continue_allowed']);
        $this->assertSame('possible', $fit['format_fit']['status']);
        $this->assertStringContainsString('Suits the explain phase only', $fit['reason']);
        $this->assertSame([], $fit['recommendations']);
        $this->assertSame('study_deck', $fit['viewer_type']);
        // Educational fit is not technical support: generation is blocked here only because no model is configured
        // (slides do apply the teaching method, and the application can start them).
        $this->assertFalse($fit['generation_supported']);
        $this->assertStringContainsString('No model access', implode(' ', $fit['generation_blockers']));
        $this->assertStringNotContainsString('takes no teaching method', implode(' ', $fit['generation_blockers']));
        $this->assertNotEmpty($fit['admin_commands']);
        $this->asTeacher()->preflight($this->valid())->assertOk()->assertJsonPath('fit.fit_status', 'suitable');
    }

    public function test_a_method_the_chapter_cannot_confirm_is_only_partially_suitable(): void
    {
        $this->seedPilotChapter();
        $this->seedStrategies();
        $fit = $this->fit('explicit_instruction', 'interactive_slides');   // strong with slides in CEDS, unconfirmed for the chapter
        $this->assertSame('partially_suitable', $fit['fit_status']);
        $this->assertTrue($fit['continue_allowed']);
        $this->assertSame('strong', $fit['format_fit']['status']);
        $this->assertSame('unconfirmed', $fit['method_fit']['verdict']);
        $fit = $this->fit('inquiry_based', 'branching_scenario');         // suits the chapter, format not in CEDS
        $this->assertSame('partially_suitable', $fit['fit_status']);
        $this->assertSame('not_evaluated', $fit['format_fit']['status']);
        // The branching scenario is shown in the study-deck player (the story template), not the standalone H5P viewer.
        $this->assertSame('study_deck', $fit['viewer_type']);
        $this->assertSame('the study-deck story player', $fit['viewer_label']);
    }

    public function test_an_unsuitable_combination_is_explained_recommended_against_and_cannot_continue(): void
    {
        $this->seedPilotChapter();
        $this->seedStrategies();
        // Project-based: not recommended for the chapter AND weak with slides in CEDS.
        $fit = $this->fit('project_based', 'interactive_slides');
        $this->assertSame('unsuitable', $fit['fit_status']);
        $this->assertFalse($fit['continue_allowed']);
        $this->assertStringContainsString('Project work is student-built', $fit['reason']);
        $methods = array_values(array_filter($fit['recommendations'], fn ($r) => $r['type'] === 'method'));
        $this->assertSame(['experiential', 'inquiry_based'], array_column($methods, 'key'));
        // Collaborative: the chapter analysis cannot judge it, but CEDS rates it weak with slides.
        $fit = $this->fit('collaborative', 'interactive_slides');
        $this->assertSame('unsuitable', $fit['fit_status']);
        $this->assertStringContainsString('keeps attention on the teacher', $fit['reason']);
        // The server refuses to continue, whatever the screen does.
        $res = $this->asTeacher()->preflight($this->valid(['pedagogy' => 'project_based']))->assertStatus(422);
        $this->assertSame('unsuitable', $res->json('fit.fit_status'));
    }

    public function test_a_format_without_a_generator_is_unsupported_has_no_template_and_may_still_continue_to_see_why(): void
    {
        $this->seedPilotChapter();
        $this->seedStrategies();
        foreach (['scenario_lesson', 'video_lesson', 'simulation_activity', 'worksheet_guide', 'socratic_dialogue'] as $format) {
            $fit = $this->fit('inquiry_based', $format);
            $this->assertSame('unsupported', $fit['fit_status'], $format);
            $this->assertFalse($fit['generation_supported'], $format);
            // It continues, so the screen can say what a template would need and offer Create.
            $this->assertTrue($fit['continue_allowed'], $format);
            $this->assertFalse($fit['template']['available'], $format);
            $this->assertNull($fit['template']['kind'], $format);
            $this->assertNotSame([], $fit['template']['requirements'], $format);
            $this->assertSame('Create', $fit['create']['label']);
            $this->assertFalse($fit['create']['available'], "$format has no generator, so Create is not possible");
            $this->assertStringContainsString('No generator exists', implode(' ', $fit['create']['reasons']), $format);
            $formats = array_column(array_filter($fit['recommendations'], fn ($r) => $r['type'] === 'format'), 'key');
            $this->assertSame(['interactive_slides', 'branching_scenario'], array_values($formats), $format);
            $this->asTeacher()->preflight($this->valid(['format' => $format]))->assertOk()->assertJsonPath('fit.template.available', false);
        }
        // A viewer is known only where one really exists.
        $this->assertSame('study_document', $this->fit('inquiry_based', 'worksheet_guide')['viewer_type']);
        $this->assertNull($this->fit('inquiry_based', 'video_lesson')['viewer_type']);
        $this->assertNull($this->fit('inquiry_based', 'socratic_dialogue')['viewer_type']);
        // An educationally unsuitable combination is still stopped.
        $this->assertFalse($this->fit('project_based', 'interactive_slides')['continue_allowed']);
    }
    public function test_missing_required_sources_are_reported_per_format(): void
    {
        $this->seedPilotChapter();
        $this->seedStrategies();
        DB::table('document_extractions')->where('id', 81)->delete();
        $fit = $this->fit('inquiry_based', 'interactive_slides');
        $this->assertSame(['textbook'], array_column($fit['missing_sources'], 'key'));
        $this->assertStringContainsString('Required source material is missing', implode(' ', $fit['generation_blockers']));
        // Learning outcomes (and the curriculum found through them) are optional: a chapter without them is neither blocked nor warned.
        DB::table('lms_learning_outcomes')->delete();
        foreach (['interactive_slides', 'branching_scenario'] as $format) {
            $fit = $this->fit('inquiry_based', $format);
            $this->assertSame(['textbook'], array_column($fit['missing_sources'], 'key'), $format);
            $this->assertStringNotContainsString('utcome', implode(' ', $fit['generation_blockers']), $format);
        }
        // Restore the chapter text: with only the outcomes gone, generation is not blocked on them.
        DB::table('document_extractions')->insert(['id' => 81, 'document_type' => 'Chapter', 'chapter_id' => self::CHAPTER, 'standard_id' => self::STANDARD, 'subject_id' => self::SUBJECT, 'sub_institute_id' => self::TENANT, 'md_content' => str_repeat('Atoms combine. ', 400)]);
        $fit = $this->fit('inquiry_based', 'interactive_slides');
        $this->assertSame([], $fit['missing_sources']);
        $this->assertSame([], $fit['incomplete_sources']);
    }

    public function test_an_existing_presentation_is_a_duplicate_that_blocks_generation(): void
    {
        $this->seedPilotChapter();
        $this->seedStrategies();
        $this->assertFalse($this->fit('inquiry_based', 'interactive_slides')['duplicate']);
        DB::table('content_master')->insert(['id' => 5, 'standard_id' => self::STANDARD, 'subject_id' => self::SUBJECT, 'chapter_id' => self::CHAPTER, 'sub_institute_id' => self::TENANT, 'content_category' => 'Classroom Presentation', 'title' => 'Existing', 'filename' => 'study_deck_inquiry_based__existing.pptx', 'show_hide' => 1]);
        $fit = $this->fit('inquiry_based', 'interactive_slides');
        $this->assertTrue($fit['duplicate']);
        $this->assertStringContainsString('would be a duplicate', implode(' ', $fit['generation_blockers']));
        // Other formats are not duplicates of a presentation.
        $this->assertFalse($this->fit('inquiry_based', 'branching_scenario')['duplicate']);
    }

    public function test_the_fit_endpoint_obeys_the_same_isolation(): void
    {
        $this->seedPilotChapter();
        DB::table('chapter_master')->insert(['id' => 8615, 'chapter_name' => 'Other', 'standard_id' => self::STANDARD, 'subject_id' => self::SUBJECT, 'sub_institute_id' => self::TENANT]);
        $q = fn (array $o = []) => '/api/lms/course-presentation/fit?' . http_build_query($o + ['chapter_id' => self::CHAPTER, 'pedagogy' => 'inquiry_based', 'format' => 'interactive_slides']);
        $this->asTeacher()->getJson($q(['chapter_id' => 8615]))->assertStatus(403);
        $this->asTeacher(self::OTHER_TENANT)->getJson($q())->assertStatus(404);
        $this->asTeacher(self::TENANT, 'Student')->getJson($q())->assertStatus(403);
        $this->asTeacher()->getJson($q(['pedagogy' => 'nope']))->assertStatus(422);
        $this->asTeacher()->getJson($q(['sources' => [1]]))->assertStatus(422);
    }

    public function test_other_chapters_subjects_and_schools_are_refused(): void
    {
        $this->seedPilotChapter();
        // Another chapter of the same school, standard and subject.
        DB::table('chapter_master')->insert(['id' => 8615, 'chapter_name' => 'Other', 'standard_id' => self::STANDARD, 'subject_id' => self::SUBJECT, 'sub_institute_id' => self::TENANT]);
        $this->asTeacher()->preflight($this->valid(['chapter_id' => 8615]))->assertStatus(403);
        $this->asTeacher()->getJson('/api/lms/course-presentation/options?chapter_id=8615')->assertStatus(403);
        // A chapter that does not exist.
        $this->asTeacher()->preflight($this->valid(['chapter_id' => 424242]))->assertStatus(404);
        // The pilot chapter seen from another school reads as not found.
        $this->asTeacher(self::OTHER_TENANT)->preflight($this->valid())->assertStatus(404);
    }

    public function test_chapter_8_the_former_pilot_is_refused_on_every_endpoint(): void
    {
        $this->seedPilotChapter();
        DB::table('chapter_master')->insert(['id' => 8614, 'chapter_name' => 'Atomic Foundations of Matter', 'standard_id' => self::STANDARD, 'subject_id' => self::SUBJECT, 'sub_institute_id' => self::TENANT]);
        $this->asTeacher()->getJson('/api/lms/course-presentation/options?chapter_id=8614')->assertStatus(403);
        $this->asTeacher()->preflight($this->valid(['chapter_id' => 8614]))->assertStatus(403);
        $this->asTeacher()->getJson('/api/lms/course-presentation/fit?chapter_id=8614&pedagogy=inquiry_based&format=interactive_slides')->assertStatus(403);
        $this->asTeacher()->getJson('/api/lms/course-presentation/preview?chapter_id=8614&pedagogy=inquiry_based&format=interactive_slides')->assertStatus(403);
        $this->asTeacher()->postJson('/api/lms/course-presentation/generate', ['chapter_id' => 8614, 'pedagogy' => 'inquiry_based', 'format' => 'interactive_slides'])->assertStatus(403);
        // Nothing of chapter 8 was written by the refusals.
        $this->assertSame(0, DB::table('content_master')->where('chapter_id', 8614)->count());
    }

    public function test_the_pilot_chapter_with_the_wrong_subject_is_refused(): void
    {
        $this->seedPilotChapter();
        DB::table('chapter_master')->where('id', self::CHAPTER)->update(['subject_id' => 3978]);
        $this->asTeacher()->preflight($this->valid())->assertStatus(403);
    }

    public function test_students_and_parents_are_refused(): void
    {
        $this->seedPilotChapter();
        $this->asTeacher(self::TENANT, 'Student')->preflight($this->valid())->assertStatus(403);
        $this->asTeacher(self::TENANT, 'Parent')->getJson('/api/lms/course-presentation/options?chapter_id=' . self::CHAPTER)->assertStatus(403);
    }

    public function test_a_session_without_a_school_is_refused(): void
    {
        $this->seedPilotChapter();
        $this->asTeacher(null)->preflight($this->valid())->assertStatus(403);
    }
}
