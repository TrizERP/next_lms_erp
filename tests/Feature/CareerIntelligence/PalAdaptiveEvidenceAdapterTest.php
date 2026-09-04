<?php

namespace Tests\Feature\CareerIntelligence;

use App\CareerIntelligence\Evidence\PalAdaptiveEvidenceAdapter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Career Intelligence's `source_type = 'pal'` slot was advertised in
 * CareerEvidenceService::SOURCE_LABELS ('Adaptive practice') from the day CI
 * shipped, but no adapter ever wrote it — an empty enum value. These tests pin
 * the adapter that fills it, and pin the adapter law it must obey.
 */
class PalAdaptiveEvidenceAdapterTest extends TestCase
{
    use DatabaseTransactions;

    private int $subInstituteId;

    private int $studentId;

    private int $subjectId;

    private int $standardId;

    private int $chapterId;

    private string $academicYear = '2026-2027';

    protected function setUp(): void
    {
        parent::setUp();

        $this->subInstituteId = (int) DB::table('school_setup')->insertGetId([
            'SchoolName' => 'CAI Test School',
            'ShortCode' => 'CAI' . random_int(1000, 9999),
            'ContactPerson' => 'Test Contact',
            'Mobile' => '9999999999',
            'Email' => 'cai-test@example.com',
            'ReceiptHeader' => 'Test',
            'ReceiptAddress' => 'Test',
            'FeeEmail' => 'cai-test@example.com',
            'ReceiptContact' => '9999999999',
            'SortOrder' => '1',
            'Logo' => '',
            'created_at' => now(),
        ]);

        $this->studentId = (int) DB::table('tblstudent')->insertGetId([
            'first_name' => 'Cai',
            'last_name' => 'Learner',
            'sub_institute_id' => $this->subInstituteId,
            'file_size' => '',
            'file_type' => '',
        ]);

        // 'Science' is a real CanonicalSubject label — the normaliser refuses
        // to guess at unmapped ones, so an invented subject name would make
        // every assertion here vacuously pass.
        $this->subjectId = (int) DB::table('subject')->insertGetId([
            'subject_name' => 'Science',
            'sub_institute_id' => $this->subInstituteId,
            'status' => 1,
            'created_at' => now(),
        ]);

        $this->standardId = (int) DB::table('standard')->insertGetId([
            'grade_id' => 1,
            'name' => '9',
            'short_name' => '9',
            'sort_order' => 1,
            'sub_institute_id' => $this->subInstituteId,
        ]);

        $this->chapterId = (int) DB::table('chapter_master')->insertGetId([
            'subject_id' => $this->subjectId,
            'standard_id' => $this->standardId,
            'sub_institute_id' => $this->subInstituteId,
            'chapter_name' => 'CAI Test Chapter',
            'created_at' => now(),
        ]);

        DB::table('tblstudent_enrollment')->insert([
            'student_id' => $this->studentId,
            'standard_id' => $this->standardId,
            'sub_institute_id' => $this->subInstituteId,
            'syear' => 2026,
        ]);
    }

    private function makeConcept(string $name): int
    {
        return (int) DB::table('lms_concept')->insertGetId([
            'name' => $name,
            'subject_id' => $this->subjectId,
            'standard_id' => $this->standardId,
            'chapter_id' => $this->chapterId,
            'sub_institute_id' => $this->subInstituteId,
            'mastery_threshold' => 80,
            'syear' => 2026,
            'created_at' => now(),
        ]);
    }

    /**
     * `$at` matters: the adapter's no-op idempotency check compares the active
     * row's observed_at against the latest outcome, so a batch of outcomes
     * stamped in the same second as the previous run is correctly treated as
     * "nothing new happened". Retention outcomes genuinely occur days or weeks
     * after the mastery they confirm, so tests exercising that must stamp them
     * later rather than relying on sub-second insert ordering.
     */
    private function logEsoDecision(int $conceptId, string $action, ?\Illuminate\Support\Carbon $at = null): void
    {
        DB::table('eso_decision_log')->insert([
            'student_id' => $this->studentId,
            'concept_id' => $conceptId,
            'sub_institute_id' => $this->subInstituteId,
            'state_snapshot' => json_encode([]),
            'rule_fired' => 'test',
            'action' => $action,
            'created_at' => $at ?? now(),
        ]);
    }

    public function test_a_student_with_no_adaptive_learning_history_produces_no_evidence(): void
    {
        $written = app(PalAdaptiveEvidenceAdapter::class)->ingest((string) $this->studentId, $this->academicYear);

        $this->assertSame([], $written, 'No ESO activity must mean no claim — never an empty-but-present row.');
    }

    public function test_a_single_mastered_concept_is_below_the_threshold_and_asserts_nothing(): void
    {
        $this->logEsoDecision($this->makeConcept('Concept One'), 'mastered_stop_practice');

        $written = app(PalAdaptiveEvidenceAdapter::class)->ingest((string) $this->studentId, $this->academicYear);

        $this->assertSame([], $written, 'One concept is too small a sample to bucket a performance level from.');
    }

    public function test_mastered_concepts_roll_up_to_a_subject_level_knowledge_claim(): void
    {
        foreach (['Concept One', 'Concept Two', 'Concept Three'] as $name) {
            $this->logEsoDecision($this->makeConcept($name), 'mastered_stop_practice');
        }

        $written = app(PalAdaptiveEvidenceAdapter::class)->ingest((string) $this->studentId, $this->academicYear);

        $this->assertCount(1, $written, 'Three concepts in one subject roll up to one subject-level row.');

        $row = DB::table('evidence_events')->where('evidence_id', $written[0])->first();

        $this->assertSame('pal', $row->source_type, 'This fills the previously-empty pal slot.');
        $this->assertSame('SCIENCE', $row->competency_id);
        $this->assertSame(9, (int) $row->grade);
        $this->assertTrue((bool) $row->verified);
        $this->assertFalse((bool) $row->contested);

        // Adapter law: a correctness source may never assert behaviour or
        // attitude, however personalised the practice was.
        $this->assertSame('KNOWLEDGE', $row->kasba_dimension);

        $provenance = json_decode((string) $row->provenance, true);
        $this->assertSame('PalAdaptiveEvidenceAdapter', $provenance['ingested_by']);
        $this->assertSame(3, $provenance['concepts_mastered']);
        $this->assertContains('difficulty', $provenance['signals_omitted'], 'Absent signals are declared, never faked.');
    }

    public function test_surviving_spaced_retention_strengthens_the_claim_beyond_mastery_alone(): void
    {
        $concepts = [];
        foreach (['One', 'Two', 'Three', 'Four', 'Five'] as $name) {
            $concepts[] = $id = $this->makeConcept("Concept {$name}");
            $this->logEsoDecision($id, 'mastered_stop_practice', now()->subDays(10));
        }

        $adapter = app(PalAdaptiveEvidenceAdapter::class);
        $beforeId = $adapter->ingest((string) $this->studentId, $this->academicYear)[0];
        $before = DB::table('evidence_events')->where('evidence_id', $beforeId)->first();

        // Days later, three of them survive a spaced check. The gap is real,
        // not incidental: the retention ladder's whole point is that time
        // passes between mastering something and proving it stuck.
        foreach (array_slice($concepts, 0, 3) as $id) {
            $this->logEsoDecision($id, 'retained', now());
        }

        $afterId = $adapter->ingest((string) $this->studentId, $this->academicYear)[0];
        $after = DB::table('evidence_events')->where('evidence_id', $afterId)->first();

        $this->assertNotSame($beforeId, $afterId, 'New evidence supersedes rather than mutating the old row.');
        $this->assertSame('developing', $before->performance_level, 'Mastered but never retested is not yet durable.');
        $this->assertSame('demonstrated', $after->performance_level, 'Mastery that survived a spaced check is.');
        $this->assertGreaterThan((float) $before->strength, (float) $after->strength);

        // Append-only correction chain, exactly as the assessment adapter does.
        $superseded = DB::table('evidence_events')->where('evidence_id', $beforeId)->first();
        $this->assertTrue((bool) $superseded->contested);
        $this->assertSame($afterId, (int) $superseded->superseded_by);
    }

    public function test_re_running_with_no_new_activity_does_not_churn_a_duplicate_version(): void
    {
        foreach (['One', 'Two', 'Three'] as $name) {
            $this->logEsoDecision($this->makeConcept("Concept {$name}"), 'mastered_stop_practice');
        }

        $adapter = app(PalAdaptiveEvidenceAdapter::class);
        $first = $adapter->ingest((string) $this->studentId, $this->academicYear);
        $second = $adapter->ingest((string) $this->studentId, $this->academicYear);

        $this->assertSame($first, $second, 'Nothing new happened — the same active row is returned, not a new version.');
        $this->assertSame(1, DB::table('evidence_events')
            ->where('student_id', $this->studentId)
            ->where('source_type', 'pal')
            ->count());
    }

    public function test_one_students_adaptive_learning_never_becomes_another_students_evidence(): void
    {
        $otherStudentId = (int) DB::table('tblstudent')->insertGetId([
            'first_name' => 'Other',
            'last_name' => 'Learner',
            'sub_institute_id' => $this->subInstituteId,
            'file_size' => '',
            'file_type' => '',
        ]);

        DB::table('tblstudent_enrollment')->insert([
            'student_id' => $otherStudentId,
            'standard_id' => $this->standardId,
            'sub_institute_id' => $this->subInstituteId,
            'syear' => 2026,
        ]);

        foreach (['One', 'Two', 'Three'] as $name) {
            $this->logEsoDecision($this->makeConcept("Concept {$name}"), 'mastered_stop_practice');
        }

        $adapter = app(PalAdaptiveEvidenceAdapter::class);
        $adapter->ingest((string) $this->studentId, $this->academicYear);
        $otherWritten = $adapter->ingest((string) $otherStudentId, $this->academicYear);

        $this->assertSame([], $otherWritten, 'Same tenant, same class, same concepts — and still no evidence of their own.');
        $this->assertSame(0, DB::table('evidence_events')->where('student_id', $otherStudentId)->count());
    }
}
