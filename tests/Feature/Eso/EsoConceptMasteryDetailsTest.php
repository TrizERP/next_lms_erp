<?php

namespace Tests\Feature\Eso;

use App\Models\Eso\ResponseLog;
use App\Services\Eso\EsoPolicyService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * EsoPolicyService::conceptMasteryDetails() — the "Mastery details" modal's
 * aggregate, plus logResponse()'s wiring into scoreDiagnostic()/
 * recordAttempt()/retrievalCheck(). Same fixture/helper conventions as
 * EsoPolicyServiceTest and EsoChapterDashboardTest.
 */
class EsoConceptMasteryDetailsTest extends TestCase
{
    use DatabaseTransactions;

    private EsoPolicyService $policy;

    private int $subInstituteId;

    private int $studentId;

    private int $subjectId;

    private int $standardId;

    private int $chapterId;

    private int $conceptId;

    private int $kNodeId;

    private int $aNodeId;

    private int $questionCounter = 800000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = app(EsoPolicyService::class);

        $this->subInstituteId = (int) DB::table('school_setup')->insertGetId([
            'SchoolName' => 'ESO Mastery Details Test School',
            'ShortCode' => 'ESOM' . random_int(1000, 9999),
            'ContactPerson' => 'Test Contact',
            'Mobile' => '9999999999',
            'Email' => 'eso-mastery-details-test@example.com',
            'ReceiptHeader' => 'Test',
            'ReceiptAddress' => 'Test',
            'FeeEmail' => 'eso-mastery-details-test@example.com',
            'ReceiptContact' => '9999999999',
            'SortOrder' => '1',
            'Logo' => '',
            'created_at' => now(),
        ]);

        $this->studentId = (int) DB::table('tblstudent')->insertGetId([
            'first_name' => 'Eso', 'last_name' => 'Mastery Learner',
            'sub_institute_id' => $this->subInstituteId, 'file_size' => '', 'file_type' => '',
        ]);

        $this->subjectId = (int) DB::table('subject')->insertGetId([
            'subject_name' => 'ESO Mastery Details Test Subject ' . random_int(1000, 9999),
            'sub_institute_id' => $this->subInstituteId, 'status' => 1, 'created_at' => now(),
        ]);

        $this->standardId = (int) DB::table('standard')->insertGetId([
            'grade_id' => 1, 'name' => '9', 'short_name' => '9', 'sort_order' => 1,
            'sub_institute_id' => $this->subInstituteId,
        ]);

        $this->chapterId = (int) DB::table('chapter_master')->insertGetId([
            'subject_id' => $this->subjectId, 'standard_id' => $this->standardId,
            'sub_institute_id' => $this->subInstituteId, 'chapter_name' => 'ESO Mastery Details Test Chapter',
            'created_at' => now(),
        ]);

        $this->conceptId = (int) DB::table('lms_concept')->insertGetId([
            'name' => 'ESO Mastery Details Test Concept',
            'subject_id' => $this->subjectId, 'standard_id' => $this->standardId,
            'chapter_id' => $this->chapterId, 'sub_institute_id' => $this->subInstituteId,
            'mastery_threshold' => 80, 'syear' => 2026, 'created_at' => now(),
        ]);

        $this->kNodeId = (int) DB::table('pal_concept_nodes')->insertGetId([
            'concept_id' => $this->conceptId, 'sub_institute_id' => $this->subInstituteId,
            'node_type' => 'K', 'label' => 'Knowledge node', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->aNodeId = (int) DB::table('pal_concept_nodes')->insertGetId([
            'concept_id' => $this->conceptId, 'sub_institute_id' => $this->subInstituteId,
            'node_type' => 'A', 'label' => 'Application node', 'sort_order' => 2,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function makeAnswer(bool $correct, ?int $misconceptionId = null, ?int $questionId = null): int
    {
        return (int) DB::table('answer_master')->insertGetId([
            'question_id' => $questionId ?? $this->questionCounter++,
            'answer' => $correct ? 'The correct option' : 'A wrong option',
            'correct_answer' => $correct ? 1 : 0,
            'misconception_id' => $misconceptionId,
            'sub_institute_id' => $this->subInstituteId,
            'created_on' => now(),
        ]);
    }

    public function test_unknown_concept_returns_null(): void
    {
        $this->assertNull($this->policy->conceptMasteryDetails($this->studentId, 999999999, $this->subInstituteId));
    }

    public function test_a_fresh_concept_reports_honest_empty_states_everywhere(): void
    {
        $details = $this->policy->conceptMasteryDetails($this->studentId, $this->conceptId, $this->subInstituteId);

        $this->assertSame('not_started', $details['status']);
        $this->assertSame(0, $details['responses_on_concept']);
        $this->assertStringContainsString('0 recorded responses', $details['confidence_note']);
        $this->assertStringNotContainsString('older', $details['confidence_note']); // no fabricated recency claim

        foreach ($details['mastery_signals'] as $signal) {
            $this->assertNull($signal['value']);
            $this->assertFalse($signal['has_evidence']);
            $this->assertSame(0, $signal['response_count']);
            $this->assertNotEmpty($signal['description']);
        }

        $this->assertSame(['count' => 0, 'correct' => 0], $details['support_with_hint']);
        $this->assertSame(['count' => 0, 'correct' => 0], $details['support_independent']);
        $this->assertSame([], $details['misconceptions']);
        $this->assertSame([], $details['recent_responses']);
    }

    public function test_record_attempt_logs_a_response_and_shows_in_recent_responses(): void
    {
        $questionId = 700100;
        DB::table('lms_question_master')->insert([
            'id' => $questionId, 'question_title' => 'What is -3 + 5?', 'question_type_id' => 1,
        ]);
        $rightAnswer = $this->makeAnswer(true, questionId: $questionId);

        $this->policy->recordAttempt($this->studentId, $this->kNodeId, $this->conceptId, $this->subInstituteId, [
            'answer_master_id' => $rightAnswer,
        ]);

        $this->assertSame(1, ResponseLog::forStudent($this->studentId)->forConcept($this->conceptId)->count());

        $details = $this->policy->conceptMasteryDetails($this->studentId, $this->conceptId, $this->subInstituteId);

        $this->assertSame('in_progress', $details['status']);
        $this->assertSame(1, $details['responses_on_concept']);
        $this->assertCount(1, $details['recent_responses']);
        $this->assertSame('What is -3 + 5?', $details['recent_responses'][0]['question']);
        $this->assertTrue($details['recent_responses'][0]['correct']);
    }

    public function test_hint_used_attempts_split_into_the_support_with_hint_bucket(): void
    {
        $withHint = $this->makeAnswer(true);
        $independent = $this->makeAnswer(false);

        $this->policy->recordAttempt($this->studentId, $this->kNodeId, $this->conceptId, $this->subInstituteId, [
            'answer_master_id' => $withHint, 'hint_used' => true,
        ]);
        $this->policy->recordAttempt($this->studentId, $this->kNodeId, $this->conceptId, $this->subInstituteId, [
            'answer_master_id' => $independent, 'hint_used' => false,
        ]);

        $details = $this->policy->conceptMasteryDetails($this->studentId, $this->conceptId, $this->subInstituteId);

        $this->assertSame(['count' => 1, 'correct' => 1], $details['support_with_hint']);
        $this->assertSame(['count' => 1, 'correct' => 0], $details['support_independent']);
    }

    public function test_diagnostic_and_retrieval_responses_are_both_logged(): void
    {
        $right = $this->makeAnswer(true);
        $this->policy->scoreDiagnostic($this->studentId, $this->conceptId, $this->subInstituteId, [
            ['node_id' => $this->kNodeId, 'answer_master_id' => $right],
        ]);
        $this->assertSame(1, ResponseLog::forStudent($this->studentId)->forConcept($this->conceptId)->count());

        $wrong = $this->makeAnswer(false);
        $this->policy->retrievalCheck($this->studentId, $this->kNodeId, $this->conceptId, $this->subInstituteId, [
            ['answer_master_id' => $wrong],
        ]);
        $this->assertSame(2, ResponseLog::forStudent($this->studentId)->forConcept($this->conceptId)->count());
    }

    public function test_a_misconception_history_entry_flips_to_corrected_after_a_clean_retest(): void
    {
        $misconceptionId = (int) DB::table('pal_misconception_library')->insertGetId([
            'tag' => 'eso_mastery_details_test_' . random_int(1000, 9999),
            'concept_ref_id' => $this->conceptId,
            'sub_institute_id' => $this->subInstituteId,
            'description' => 'Confuses adding a negative with subtracting a positive.',
            'quality_status' => 'approved',
            'priority_level' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $wrongAnswerId = $this->makeAnswer(false, $misconceptionId);
        $rightAnswerId = $this->makeAnswer(true);

        $this->policy->recordAttempt($this->studentId, $this->kNodeId, $this->conceptId, $this->subInstituteId, [
            'answer_master_id' => $wrongAnswerId,
        ]);

        $details = $this->policy->conceptMasteryDetails($this->studentId, $this->conceptId, $this->subInstituteId);
        $this->assertCount(1, $details['misconceptions']);
        $this->assertFalse($details['misconceptions'][0]['corrected']);
        $this->assertSame('Confuses adding a negative with subtracting a positive.', $details['misconceptions'][0]['description']);

        $this->policy->recordAttempt($this->studentId, $this->kNodeId, $this->conceptId, $this->subInstituteId, [
            'answer_master_id' => $rightAnswerId,
        ]);

        $details = $this->policy->conceptMasteryDetails($this->studentId, $this->conceptId, $this->subInstituteId);
        $this->assertCount(1, $details['misconceptions']);
        $this->assertTrue($details['misconceptions'][0]['corrected']);
    }
}
