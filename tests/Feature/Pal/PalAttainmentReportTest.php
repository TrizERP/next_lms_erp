<?php

namespace Tests\Feature\Pal;

use App\Models\Eso\LearnerNodeState;
use App\Services\PAL\Reporting\AttainmentReportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Curriculum Coverage vs Student Attainment.
 *
 * The gap between the two reports IS the finding — "92% covered, 66%
 * demonstrated" tells a principal where to intervene in a way no single
 * blended score can. So these tests are mostly about keeping the two numbers
 * from contaminating each other.
 */
class PalAttainmentReportTest extends TestCase
{
    use DatabaseTransactions;

    private AttainmentReportService $report;

    private int $subInstituteId;

    private int $standardId;

    private int $subjectId;

    private int $chapterId;

    private array $studentIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->report = app(AttainmentReportService::class);

        $this->subInstituteId = (int) DB::table('school_setup')->insertGetId([
            'SchoolName' => 'Attainment School',
            'ShortCode' => 'AT' . random_int(1000, 9999),
            'ContactPerson' => 'Test Contact',
            'Mobile' => '9999999999',
            'Email' => 'attainment@example.com',
            'ReceiptHeader' => 'Test',
            'ReceiptAddress' => 'Test',
            'FeeEmail' => 'attainment@example.com',
            'ReceiptContact' => '9999999999',
            'SortOrder' => '1',
            'Logo' => '',
            'created_at' => now(),
        ]);

        $this->subjectId = (int) DB::table('subject')->insertGetId([
            'subject_name' => 'Attainment Subject ' . random_int(1000, 9999),
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
            'chapter_name' => 'Attainment Chapter',
            'sort_order' => 1,
            'created_at' => now(),
        ]);
    }

    private function enrolStudent(string $name): int
    {
        $studentId = (int) DB::table('tblstudent')->insertGetId([
            'first_name' => $name,
            'last_name' => 'Learner',
            'sub_institute_id' => $this->subInstituteId,
            'file_size' => '',
            'file_type' => '',
        ]);

        DB::table('tblstudent_enrollment')->insert([
            'syear' => 2026,
            'student_id' => $studentId,
            'grade_id' => 1,
            'standard_id' => $this->standardId,
            'section_id' => 1,
            'sub_institute_id' => $this->subInstituteId,
            'created_on' => now(),
        ]);

        $this->studentIds[] = $studentId;

        return $studentId;
    }

    /** A concept. `withNodes` false makes it un-teachable — present in the curriculum, not ESO-ready. */
    private function makeConcept(string $name, bool $withNodes = true): array
    {
        $conceptId = (int) DB::table('lms_concept')->insertGetId([
            'name' => $name,
            'subject_id' => $this->subjectId,
            'standard_id' => $this->standardId,
            'chapter_id' => $this->chapterId,
            'sub_institute_id' => $this->subInstituteId,
            'mastery_threshold' => 80,
            'syear' => 2026,
            'created_at' => now(),
        ]);

        if (! $withNodes) {
            return [$conceptId, []];
        }

        $nodeIds = [];
        foreach ([['K', 1], ['A', 2]] as [$type, $order]) {
            $nodeIds[] = (int) DB::table('pal_concept_nodes')->insertGetId([
                'concept_id' => $conceptId,
                'sub_institute_id' => $this->subInstituteId,
                'node_type' => $type,
                'label' => $type . ' node',
                'sort_order' => $order,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return [$conceptId, $nodeIds];
    }

    /** Mark nodes mastered, as masteryVerdict() itself does when a concept is cleared. */
    private function master(int $studentId, array $nodeIds): void
    {
        foreach ($nodeIds as $nodeId) {
            LearnerNodeState::updateOrCreate(
                ['student_id' => $studentId, 'node_id' => $nodeId],
                [
                    'sub_institute_id' => $this->subInstituteId,
                    'mastery_estimate' => 1.0,
                    'attempts' => 5,
                    'status' => LearnerNodeState::STATUS_MASTERED,
                    'last_seen_at' => now(),
                ]
            );
        }
    }

    private function logResponse(int $studentId, int $conceptId, int $nodeId): void
    {
        DB::table('eso_response_log')->insert([
            'student_id' => $studentId,
            'concept_id' => $conceptId,
            'node_id' => $nodeId,
            'sub_institute_id' => $this->subInstituteId,
            'question_id' => random_int(100000, 999999),
            'correct' => true,
            'hint_used' => false,
            'mode' => LearnerNodeState::MODE_INDEPENDENT,
            'created_at' => now(),
        ]);
    }

    private function cohortReport(): array
    {
        return $this->report->forCohort($this->subInstituteId, $this->standardId, '2026');
    }

    public function test_coverage_counts_the_whole_curriculum_not_only_what_is_teachable(): void
    {
        $this->makeConcept('Taught One');
        $this->makeConcept('Taught Two');
        $this->makeConcept('No Content Yet', withNodes: false);

        $coverage = $this->cohortReport()['coverage'];

        $this->assertSame(3, $coverage['concepts_total']);
        $this->assertSame(2, $coverage['concepts_taught']);
        $this->assertSame(
            66.7,
            $coverage['coverage_pct'],
            'A concept nobody can be taught still counts against coverage — excluding it from the denominator would hide the content gap being measured.'
        );
    }

    public function test_an_untaught_concept_has_no_attainment_rather_than_zero_percent(): void
    {
        $this->enrolStudent('Ada');
        $this->makeConcept('Taught');
        $this->makeConcept('Never Delivered', withNodes: false);

        $rows = collect($this->cohortReport()['concepts'])->keyBy('name');

        $this->assertNull(
            $rows['Never Delivered']['attainment_pct'],
            'Nobody has failed a concept that was never available to them. A 0% here would read as a teaching failure rather than a content gap.'
        );
        $this->assertFalse($rows['Never Delivered']['taught']);
        $this->assertNotNull($rows['Taught']['attainment_pct']);
    }

    public function test_attainment_is_measured_against_taught_concepts_only(): void
    {
        $ada = $this->enrolStudent('Ada');
        [, $nodes] = $this->makeConcept('Taught And Mastered');
        $this->makeConcept('Not Delivered A', withNodes: false);
        $this->makeConcept('Not Delivered B', withNodes: false);

        $this->master($ada, $nodes);

        $report = $this->cohortReport();

        $this->assertSame(1, $report['attainment']['concepts_measured']);
        $this->assertSame(
            100.0,
            $report['attainment']['mean_attainment_pct'],
            'The one taught concept was mastered by the only student. Folding two undelivered concepts into the denominator would report 33% and blame the school for a content gap.'
        );

        // Coverage tells the other half of the story, and disagrees on purpose.
        $this->assertSame(33.3, $report['coverage']['coverage_pct']);
    }

    public function test_partial_mastery_of_a_concept_does_not_count_as_mastered(): void
    {
        $ada = $this->enrolStudent('Ada');
        [, $nodes] = $this->makeConcept('Half Done');

        // K mastered, A not. masteryVerdict() gates on both.
        $this->master($ada, [$nodes[0]]);

        $rows = collect($this->cohortReport()['concepts'])->keyBy('name');

        $this->assertSame(0, $rows['Half Done']['students_mastered']);
        $this->assertSame(0.0, $rows['Half Done']['attainment_pct']);
    }

    public function test_attainment_is_a_share_of_the_whole_cohort(): void
    {
        $ada = $this->enrolStudent('Ada');
        $this->enrolStudent('Grace');
        $this->enrolStudent('Alan');
        $this->enrolStudent('Katherine');

        [, $nodes] = $this->makeConcept('Shared Concept');
        $this->master($ada, $nodes);

        $rows = collect($this->cohortReport()['concepts'])->keyBy('name');

        $this->assertSame(4, $this->cohortReport()['scope']['student_count']);
        $this->assertSame(1, $rows['Shared Concept']['students_mastered']);
        $this->assertSame(25.0, $rows['Shared Concept']['attainment_pct']);
    }

    public function test_taught_but_untouched_is_reported_separately_from_low_attainment(): void
    {
        $ada = $this->enrolStudent('Ada');

        [$strugglingId, $strugglingNodes] = $this->makeConcept('Attempted But Not Mastered');
        $this->makeConcept('Nobody Has Started This');

        $this->logResponse($ada, $strugglingId, $strugglingNodes[0]);

        $report = $this->cohortReport();
        $rows = collect($report['concepts'])->keyBy('name');

        // Both sit at 0% attainment, and they are completely different problems:
        // one needs teaching support, the other needs the class to get there.
        $this->assertSame(0.0, $rows['Attempted But Not Mastered']['attainment_pct']);
        $this->assertSame(0.0, $rows['Nobody Has Started This']['attainment_pct']);

        $this->assertSame(1, $rows['Attempted But Not Mastered']['students_attempted']);
        $this->assertSame(0, $rows['Nobody Has Started This']['students_attempted']);

        $unevidenced = array_column($report['attainment']['taught_but_unevidenced'], 'name');
        $this->assertSame(['Nobody Has Started This'], $unevidenced);
        $this->assertSame(1, $report['attainment']['concepts_with_any_evidence']);
    }

    public function test_a_cohort_with_no_students_reports_coverage_but_not_attainment(): void
    {
        $this->makeConcept('Taught');

        $report = $this->cohortReport();

        $this->assertSame(0, $report['scope']['student_count']);
        $this->assertSame(100.0, $report['coverage']['coverage_pct'], 'Coverage is a property of the curriculum and does not need a cohort.');
        $this->assertNull(
            $report['attainment']['mean_attainment_pct'],
            'With nobody enrolled there is nothing to attain, which is not the same as attaining nothing.'
        );
    }

    public function test_a_curriculum_with_no_chapters_is_empty_rather_than_an_error(): void
    {
        $report = $this->report->forCohort($this->subInstituteId, $this->standardId, '2099');

        $this->assertSame(0, $report['coverage']['concepts_total']);
        $this->assertSame([], $report['concepts']);
    }
}
