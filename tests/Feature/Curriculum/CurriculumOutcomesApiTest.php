<?php

namespace Tests\Feature\Curriculum;

use App\Http\Controllers\api\lms\CurriculumOutcomesApiController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Coverage for OutcomeAnalyticsService via CurriculumOutcomesApiController,
 * against a synthetic fixture rather than live data: no live chapter
 * currently combines every tier/gap scenario in one curriculum (confirmed
 * by hand against the dev DB), so this seeds one curriculum with four
 * chapters built to exercise each gap bucket and both achievement tiers
 * deterministically. A reserved, clearly-fake sub_institute_id keeps this
 * fixture from ever being mistaken for real tenant data.
 *
 * DatabaseTransactions rolls back everything this test writes.
 */
class CurriculumOutcomesApiTest extends TestCase
{
    use DatabaseTransactions;

    private const INST = 999888777;
    private const STD = 999888777;
    private const SUBJECT = 999888777;
    private const SYEAR = 2026;
    private const TERM = 999888777;

    private int $curriculumId;
    /** @var array<string,int> */
    private array $chapterIds = [];
    /** @var array<string,int> */
    private array $outcomeIds = [];
    private int $curriculumLevelOutcomeId;
    private int $indicatorIdUnderA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFixture();
    }

    /**
     * Chapter A: Done, assessed, pal_verified achievement 100% (2/2 pass) +
     *            exam_based secondary 90% -> gap "none".
     * Chapter B: Done, assessed, exam_based achievement 30% (no concept
     *            mapping, so no pal_verified) -> gap "learning_gap".
     * Chapter C: Done, zero questions, zero attempts -> gap "assessment_gap".
     * Chapter D: not delivered -> gap "delivery_gap", regardless of anything else.
     * Plus one curriculum-level outcome (chapter_id = 0) -> "not_applicable".
     */
    private function seedFixture(): void
    {
        $this->curriculumId = DB::table('lms_curriculum')->insertGetId([
            'sub_institute_id' => self::INST, 'standard_id' => self::STD, 'subject_id' => self::SUBJECT,
            'syear' => self::SYEAR, 'curriculum_name' => 'Test Curriculum', 'grade_id' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $unitId = DB::table('lms_units')->insertGetId([
            'curriculum_id' => $this->curriculumId, 'unit_number' => 1, 'name' => 'Test Unit',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach (['A', 'B', 'C', 'D'] as $key) {
            $this->chapterIds[$key] = DB::table('chapter_master')->insertGetId([
                'unit_id' => $unitId, 'syear' => self::SYEAR, 'sub_institute_id' => self::INST,
                'standard_id' => self::STD, 'subject_id' => self::SUBJECT,
                'chapter_name' => "Chapter $key", 'sort_order' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $planId = DB::table('lms_intelligence_lesson_plans')->insertGetId([
            'sub_institute_id' => self::INST, 'syear' => self::SYEAR, 'term_id' => self::TERM,
            'standard_id' => self::STD, 'subject_id' => self::SUBJECT, 'plan_title' => 'Test plan',
            'term_start_date' => '2026-01-01', 'term_end_date' => '2026-06-01',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach (['A' => 'completed', 'B' => 'completed', 'C' => 'completed', 'D' => 'not_started'] as $key => $status) {
            DB::table('lms_lesson_plan_periods')->insert([
                'lms_intelligence_lesson_plans_id' => $planId, 'sub_institute_id' => self::INST,
                'scheduled_date' => '2026-02-01', 'week_day' => 'Mon', 'week_number' => 1,
                'period_id' => 1, 'period_slot' => 'P1', 'teacher_id' => 1,
                'chapter_id' => $this->chapterIds[$key], 'status' => $status,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        foreach (['A', 'B'] as $key) {
            DB::table('lms_question_master')->insert([
                'chapter_id' => $this->chapterIds[$key], 'sub_institute_id' => self::INST,
                'question_title' => 'Q', 'question_type_id' => 1,
                'grade_id' => 1, 'standard_id' => self::STD, 'subject_id' => self::SUBJECT,
                'created_by' => 1, 'created_on' => now(),
            ]);
        }

        foreach (['A', 'B', 'C', 'D'] as $key) {
            $this->outcomeIds[$key] = DB::table('lms_learning_outcomes')->insertGetId([
                'curriculum_id' => $this->curriculumId, 'standard_id' => self::STD, 'subject_id' => self::SUBJECT,
                'chapter_id' => $this->chapterIds[$key], 'parent_id' => null,
                'code' => "C-$key", 'type' => 'competency', 'description' => "Outcome $key",
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // A leaf "LI" indicator nested under competency A, same 3-level
        // shape confirmed live (parent_id points at the competency's id).
        $this->indicatorIdUnderA = DB::table('lms_learning_outcomes')->insertGetId([
            'curriculum_id' => $this->curriculumId, 'standard_id' => self::STD, 'subject_id' => self::SUBJECT,
            'chapter_id' => $this->chapterIds['A'], 'parent_id' => $this->outcomeIds['A'],
            'code' => 'C-A-LO-1', 'type' => 'learning_outcome', 'description' => 'Indicator under A',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->curriculumLevelOutcomeId = DB::table('lms_learning_outcomes')->insertGetId([
            'curriculum_id' => $this->curriculumId, 'standard_id' => self::STD, 'subject_id' => self::SUBJECT,
            'chapter_id' => 0, 'parent_id' => null,
            'code' => 'CG-1', 'type' => 'goal', 'description' => 'Goal',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Exam-based evidence: chapter A high, chapter B low.
        DB::table('pal_diagnostic_attempt')->insert([
            'student_id' => 1, 'subject_id' => self::SUBJECT, 'chapter_id' => $this->chapterIds['A'],
            'standard_id' => self::STD, 'sub_institute_id' => self::INST, 'syear' => self::SYEAR,
            'status' => 'submitted', 'percentage' => 90.00,
            'total_questions' => 10, 'correct' => 9, 'incorrect' => 1, 'unanswered' => 0,
        ]);
        DB::table('pal_diagnostic_attempt')->insert([
            'student_id' => 2, 'subject_id' => self::SUBJECT, 'chapter_id' => $this->chapterIds['B'],
            'standard_id' => self::STD, 'sub_institute_id' => self::INST, 'syear' => self::SYEAR,
            'status' => 'submitted', 'percentage' => 30.00,
            'total_questions' => 10, 'correct' => 3, 'incorrect' => 7, 'unanswered' => 0,
        ]);

        // PAL-verified evidence: only outcome A has a concept mapped, with two passing mastery rows.
        $conceptId = DB::table('lms_concept')->insertGetId([
            'name' => 'Test Concept', 'subject_id' => self::SUBJECT, 'standard_id' => self::STD,
            'chapter_id' => $this->chapterIds['A'], 'sub_institute_id' => self::INST, 'syear' => self::SYEAR,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('lms_concept_outcome')->insert([
            'concept_id' => $conceptId, 'outcome_id' => $this->outcomeIds['A'],
            'outcome_type' => 'competency', 'outcome_code' => 'C-A', 'chapter_id' => $this->chapterIds['A'],
            'match_source' => 'llm', 'match_score' => 0.9, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('pal_concept_mastery')->insert([
            'learner_id' => 1, 'concept_ref_id' => $conceptId, 'sub_institute_id' => self::INST,
            'p_mastery' => 0.85, 'mastery_gate' => 0.7, 'attempts' => 5, 'correct' => 4,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('pal_concept_mastery')->insert([
            'learner_id' => 2, 'concept_ref_id' => $conceptId, 'sub_institute_id' => self::INST,
            'p_mastery' => 0.75, 'mastery_gate' => 0.7, 'attempts' => 3, 'correct' => 2,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Resource coverage: chapter A has a real filename; chapter B has only a
        // `url` (no filename) - must NOT count, matching the verified fact that
        // `url` is effectively dead and `filename` is the real pointer.
        DB::table('content_master')->insert([
            'chapter_id' => $this->chapterIds['A'], 'sub_institute_id' => self::INST,
            'standard_id' => self::STD, 'subject_id' => self::SUBJECT,
            'title' => 'Test Resource', 'filename' => 'test.pdf', 'file_type' => 'pdf',
            'syear' => self::SYEAR, 'created_at' => now(),
        ]);
        DB::table('content_master')->insert([
            'chapter_id' => $this->chapterIds['B'], 'sub_institute_id' => self::INST,
            'standard_id' => self::STD, 'subject_id' => self::SUBJECT,
            'title' => 'URL-only resource', 'filename' => null, 'url' => 'http://example.com/video',
            'syear' => self::SYEAR, 'created_at' => now(),
        ]);
    }

    private function baseFilters(): array
    {
        return [
            'sub_institute_id' => self::INST, 'syear' => self::SYEAR,
            'standard_id' => self::STD, 'subject_id' => self::SUBJECT,
            'curriculum_id' => $this->curriculumId,
        ];
    }

    private function callIndex(array $overrides = []): array
    {
        $controller = app(CurriculumOutcomesApiController::class);
        $request = Request::create('/api/intelligence/curriculum-outcomes', 'GET', array_merge($this->baseFilters(), $overrides));
        $response = $controller->index($request);

        return ['status' => $response->getStatusCode(), 'json' => json_decode($response->getContent(), true)];
    }

    private function callDetail(int $outcomeId, array $overrides = []): array
    {
        $controller = app(CurriculumOutcomesApiController::class);
        $request = Request::create('/api/intelligence/curriculum-outcomes/outcome', 'GET', array_merge(
            $this->baseFilters(),
            ['outcome_id' => $outcomeId],
            $overrides
        ));
        $response = $controller->outcomeDetail($request);

        return ['status' => $response->getStatusCode(), 'json' => json_decode($response->getContent(), true)];
    }

    public function test_kpis_reflect_the_seeded_mix_exactly(): void
    {
        $result = $this->callIndex();
        $this->assertSame(200, $result['status']);
        $kpis = $result['json']['data']['kpis'];

        // 6 total = A, B, C, D, the leaf indicator under A, and the goal.
        $this->assertSame(6, $kpis['expected_outcomes']['total']);
        $this->assertSame(5, $kpis['expected_outcomes']['chapter_mapped']);
        $this->assertSame(1, $kpis['expected_outcomes']['curriculum_level']);

        // A, B, C and the indicator under A (same chapter as A) are delivered (Done); D is not.
        $this->assertSame(4, $kpis['delivered']['count']);
        $this->assertSame(80, $kpis['delivered']['percent']);
        $this->assertSame(1, $kpis['delivery_gap']['count']);

        // A (100%, pal_verified) and the indicator (90%, exam_based - it has no concept
        // mapping of its own, so it falls back to chapter A's diagnostic attempt) clear 50%.
        $this->assertSame(2, $kpis['achieved']['count']);
        $this->assertSame(40, $kpis['achieved']['percent']);
        $this->assertSame(2, $kpis['achieved']['tier_breakdown']['exam_based']);
        $this->assertSame(1, $kpis['achieved']['tier_breakdown']['pal_verified']);
        $this->assertSame(2, $kpis['achieved']['tier_breakdown']['unavailable']);

        // A, B and the indicator (chapter A) have assessment evidence; C and D do not.
        $this->assertSame(60, $kpis['assessment_coverage']['percent']);

        // A and the indicator share chapter A's real `filename`; B's url-only row must not count.
        $this->assertSame(40, $kpis['resource_coverage']['percent']);
    }

    public function test_gap_buckets_are_mutually_exclusive_and_match_the_seeded_scenario(): void
    {
        $gaps = $this->callIndex()['json']['data']['gaps'];

        $this->assertSame([$this->outcomeIds['D']], array_column($gaps['delivery_gaps'], 'outcome_id'));
        $this->assertSame([$this->outcomeIds['C']], array_column($gaps['assessment_gaps'], 'outcome_id'));
        $this->assertSame([$this->outcomeIds['B']], array_column($gaps['learning_gaps'], 'outcome_id'));
    }

    public function test_pal_verified_outcome_carries_the_broader_exam_based_signal_as_secondary(): void
    {
        $rows = $this->callIndex()['json']['data']['tracker_rows'];
        $rowA = collect($rows)->firstWhere('outcome_id', $this->outcomeIds['A']);

        $this->assertSame('pal_verified', $rowA['achievement']['tier']);
        $this->assertEquals(100.0, $rowA['achievement']['value']);
        $this->assertNotNull($rowA['achievement']['secondary']);
        $this->assertSame('exam_based', $rowA['achievement']['secondary']['tier']);
        $this->assertEquals(90.0, $rowA['achievement']['secondary']['value']);
        $this->assertSame('none', $rowA['gap_category']);

        // Mastery is the mean of the two learners' raw p_mastery (0.85, 0.75) = 80%,
        // genuinely different from Achievement's pass-rate (100%, both clear gate 0.7).
        $this->assertEquals(80.0, $rowA['achievement']['mastery']);
        // Status is read from Achievement (100 >= 75), never from Mastery.
        $this->assertSame('good', $rowA['achievement']['status']);
    }

    public function test_exam_based_only_outcome_has_no_pal_secondary(): void
    {
        $rows = $this->callIndex()['json']['data']['tracker_rows'];
        $rowB = collect($rows)->firstWhere('outcome_id', $this->outcomeIds['B']);

        $this->assertSame('exam_based', $rowB['achievement']['tier']);
        $this->assertEquals(30.0, $rowB['achievement']['value']);
        $this->assertNull($rowB['achievement']['secondary']);
        $this->assertSame('learning_gap', $rowB['gap_category']);

        // A single diagnostic-attempt percentage gives one number: Achievement and Mastery coincide.
        $this->assertEquals(30.0, $rowB['achievement']['mastery']);
        $this->assertSame('needs_attention', $rowB['achievement']['status']);
    }

    public function test_outcome_with_zero_evidence_is_null_not_zero(): void
    {
        $rows = $this->callIndex()['json']['data']['tracker_rows'];
        $rowC = collect($rows)->firstWhere('outcome_id', $this->outcomeIds['C']);

        $this->assertNull($rowC['achievement']['value']);
        $this->assertSame('unavailable', $rowC['achievement']['tier']);
        $this->assertSame('assessment_gap', $rowC['gap_category']);
    }

    public function test_curriculum_level_outcome_is_not_applicable_everywhere_and_excluded_from_denominators(): void
    {
        $rows = $this->callIndex()['json']['data']['tracker_rows'];
        $goalRow = collect($rows)->firstWhere('outcome_id', $this->curriculumLevelOutcomeId);

        $this->assertNull($goalRow['chapter_id']);
        $this->assertSame('not_applicable', $goalRow['delivered']);
        $this->assertSame('not_applicable', $goalRow['assessed']);
        $this->assertSame('not_applicable', $goalRow['achievement']['tier']);
        $this->assertSame('not_applicable', $goalRow['gap_category']);

        $detail = $this->callDetail($this->curriculumLevelOutcomeId)['json']['data'];
        $this->assertNull($detail['links']);
        $this->assertNull($detail['chapter']);
    }

    public function test_outcome_detail_drawer_includes_mapped_concepts_and_real_deep_links(): void
    {
        $detail = $this->callDetail($this->outcomeIds['A'])['json']['data'];

        $this->assertSame('pal_verified', $detail['achievement']['tier']);
        $this->assertCount(1, $detail['mapped_concepts']);
        $this->assertEquals(100.0, $detail['mapped_concepts'][0]['pal_mastery_pct']);

        $expectedChapterId = $this->chapterIds['A'];
        $this->assertSame(
            "/course-master/" . self::SUBJECT . "-" . self::STD . "/chapters?view=concept-intelligence&chapterId={$expectedChapterId}&expandedChapterId={$expectedChapterId}",
            $detail['links']['concept_intelligence']
        );
    }

    public function test_curriculum_not_belonging_to_the_given_tenant_is_404_not_a_leak(): void
    {
        $result = $this->callIndex(['sub_institute_id' => self::INST + 1]);
        $this->assertSame(404, $result['status']);
    }

    public function test_detail_for_an_outcome_outside_this_curriculum_is_404(): void
    {
        $result = $this->callDetail(900000000);
        $this->assertSame(404, $result['status']);
    }

    public function test_lo_rows_nest_the_leaf_indicator_under_its_competency_not_under_its_goal(): void
    {
        $loRows = $this->callIndex()['json']['data']['lo_rows'];
        $competencyA = collect($loRows)->firstWhere('outcome_id', $this->outcomeIds['A']);

        $this->assertNotNull($competencyA, 'Competency A must appear as an LO row.');
        $this->assertCount(1, $competencyA['indicators']);
        $this->assertSame($this->indicatorIdUnderA, $competencyA['indicators'][0]['outcome_id']);
        // No concept mapping of its own, so it falls back to chapter A's exam-based signal.
        $this->assertSame('exam_based', $competencyA['indicators'][0]['achievement']['tier']);

        $competencyB = collect($loRows)->firstWhere('outcome_id', $this->outcomeIds['B']);
        $this->assertCount(0, $competencyB['indicators']);

        $goalRow = collect($loRows)->firstWhere('outcome_id', $this->curriculumLevelOutcomeId);
        $this->assertNull($goalRow, 'A goal-type row must never appear as an LO row.');
    }

    public function test_resource_breakdown_counts_by_real_file_type_not_by_url(): void
    {
        $breakdown = $this->callIndex()['json']['data']['resource_breakdown'];

        $this->assertSame(1, $breakdown['pdf']);
        $this->assertArrayNotHasKey('link', $breakdown, "The url-only content_master row has no filename and must not be counted as a resource at all.");
    }

    public function test_mastery_distribution_reports_real_band_keys(): void
    {
        $distribution = $this->callIndex()['json']['data']['mastery_distribution'];

        // The fixture's two pal_concept_mastery rows never set `band`, so both fall
        // back to the explicit 'unbanded' marker rather than a fabricated label.
        $this->assertSame(2, $distribution['unbanded'] ?? null);
    }

    public function test_student_rows_only_lists_students_with_real_evidence_and_scores_them_correctly(): void
    {
        $studentRows = $this->callIndex()['json']['data']['student_rows'];

        $this->assertNull($studentRows['note']);
        $studentIds = array_column($studentRows['students'], 'student_id');
        $this->assertContains(1, $studentIds, 'Learner 1 has real pal_concept_mastery evidence and must appear.');
        $this->assertContains(2, $studentIds, 'Learner 2 has real pal_concept_mastery evidence and must appear.');

        $learner1 = collect($studentRows['students'])->firstWhere('student_id', 1);
        // Learner 1's own mastery row for outcome A's one mapped concept is 0.85 >= gate 0.7 -> 100%.
        $this->assertEquals(100.0, $learner1['scores'][$this->outcomeIds['A']]['value']);
        $this->assertSame('pal_verified', $learner1['scores'][$this->outcomeIds['A']]['tier']);
        // Learner 1 has no evidence at all for outcome C.
        $this->assertNull($learner1['scores'][$this->outcomeIds['C']]['value']);
        $this->assertSame('unavailable', $learner1['scores'][$this->outcomeIds['C']]['tier']);
    }
}
