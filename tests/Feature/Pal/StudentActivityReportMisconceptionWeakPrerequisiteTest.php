<?php

namespace Tests\Feature\Pal;

use App\Services\PAL\Coherence\CoherenceMapRepository;
use App\Services\PAL\Reporting\StudentActivityReportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

/**
 * K8 — misconception -> weak-prerequisite linkage.
 * `StudentActivityReportService::misconceptionAnalysis()` is private;
 * reflection is used to call it directly, the same pattern 30+ other test
 * files in this suite already use for private-method coverage.
 *
 * `CoherenceMapRepository` is mocked for determinism (same reasoning as the
 * other two test files in this batch) — `rootBlockers()`'s own correctness
 * against real data is proven elsewhere (K4's existing coverage and the
 * manual real-data check in this session's own verification: concept 7139,
 * learner 97926 -> "Friction as opposing force").
 */
class StudentActivityReportMisconceptionWeakPrerequisiteTest extends TestCase
{
    use DatabaseTransactions;

    private StudentActivityReportService $service;

    private $coherenceMap;

    private ReflectionMethod $misconceptionAnalysis;

    private int $subInstituteId;

    private int $studentAId;

    private int $studentBId;

    private int $subjectId;

    private int $standardId;

    private int $chapterId;

    private int $conceptId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->coherenceMap = Mockery::mock(CoherenceMapRepository::class);
        $this->app->instance(CoherenceMapRepository::class, $this->coherenceMap);
        $this->service = $this->app->make(StudentActivityReportService::class);

        $this->misconceptionAnalysis = new ReflectionMethod($this->service, 'misconceptionAnalysis');
        $this->misconceptionAnalysis->setAccessible(true);

        $this->subInstituteId = (int) DB::table('school_setup')->insertGetId([
            'SchoolName' => 'Misconception Weak-Prereq Test School',
            'ShortCode' => 'MWP' . random_int(1000, 9999),
            'ContactPerson' => 'Test Contact',
            'Mobile' => '9999999999',
            'Email' => 'mwp-test@example.com',
            'ReceiptHeader' => 'Test',
            'ReceiptAddress' => 'Test',
            'FeeEmail' => 'mwp-test@example.com',
            'ReceiptContact' => '9999999999',
            'SortOrder' => '1',
            'Logo' => '',
            'created_at' => now(),
        ]);

        $this->studentAId = (int) DB::table('tblstudent')->insertGetId([
            'first_name' => 'Weak', 'last_name' => 'Prereq A',
            'sub_institute_id' => $this->subInstituteId, 'file_size' => '', 'file_type' => '',
        ]);
        $this->studentBId = (int) DB::table('tblstudent')->insertGetId([
            'first_name' => 'Weak', 'last_name' => 'Prereq B',
            'sub_institute_id' => $this->subInstituteId, 'file_size' => '', 'file_type' => '',
        ]);

        $this->subjectId = (int) DB::table('subject')->insertGetId([
            'subject_name' => 'Weak Prereq Test Subject ' . random_int(1000, 9999),
            'sub_institute_id' => $this->subInstituteId, 'status' => 1, 'created_at' => now(),
        ]);
        $this->standardId = (int) DB::table('standard')->insertGetId([
            'grade_id' => 1, 'name' => '9', 'short_name' => '9', 'sort_order' => 1,
            'sub_institute_id' => $this->subInstituteId,
        ]);
        $this->chapterId = (int) DB::table('chapter_master')->insertGetId([
            'subject_id' => $this->subjectId, 'standard_id' => $this->standardId,
            'sub_institute_id' => $this->subInstituteId, 'chapter_name' => 'Weak Prereq Test Chapter',
            'created_at' => now(),
        ]);
        $this->conceptId = (int) DB::table('lms_concept')->insertGetId([
            'name' => 'Weak Prereq Test Concept', 'subject_id' => $this->subjectId,
            'standard_id' => $this->standardId, 'chapter_id' => $this->chapterId,
            'sub_institute_id' => $this->subInstituteId, 'mastery_threshold' => 80,
            'syear' => 2026, 'created_at' => now(),
        ]);
    }

    /** @return int misconception library row id */
    private function makeMisconception(?int $conceptRefId): int
    {
        return (int) DB::table('pal_misconception_library')->insertGetId([
            'tag' => 'weak_prereq_test_' . random_int(100000, 999999),
            'sub_institute_id' => $this->subInstituteId,
            'description' => 'A test misconception.',
            'concept_ref_id' => $conceptRefId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function flagActive(int $studentId, int $misconceptionId): void
    {
        $nodeId = (int) DB::table('pal_concept_nodes')->insertGetId([
            'concept_id' => $this->conceptId, 'sub_institute_id' => $this->subInstituteId,
            'node_type' => 'K', 'label' => 'Weak prereq node', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('learner_node_state')->insert([
            'student_id' => $studentId, 'node_id' => $nodeId, 'sub_institute_id' => $this->subInstituteId,
            'status' => 'misconception_flagged', 'retention_stage' => 0, 'mastery_estimate' => 0.2,
            'active_misconception_id' => $misconceptionId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_single_learner_gets_weak_prerequisite_from_root_blockers(): void
    {
        $misconceptionId = $this->makeMisconception($this->conceptId);
        $this->flagActive($this->studentAId, $misconceptionId);

        $this->coherenceMap->shouldReceive('rootBlockers')
            ->once()
            ->with($this->conceptId, $this->studentAId)
            ->andReturn([['id' => 555, 'name' => 'A Weak Prerequisite', 'mastery' => 0.1, 'gate' => 0.8, 'depth' => 2]]);

        $result = $this->misconceptionAnalysis->invoke($this->service, [$this->studentAId], null);

        $this->assertSame(1, $result['active_count']);
        $this->assertSame('A Weak Prerequisite', $result['top_misconceptions'][0]['weak_prerequisite']);
    }

    public function test_multi_student_class_report_never_resolves_weak_prerequisite(): void
    {
        $misconceptionId = $this->makeMisconception($this->conceptId);
        $this->flagActive($this->studentAId, $misconceptionId);
        $this->flagActive($this->studentBId, $misconceptionId);

        // Ambiguous for an aggregate — must not even be attempted.
        $this->coherenceMap->shouldNotReceive('rootBlockers');

        $result = $this->misconceptionAnalysis->invoke($this->service, [$this->studentAId, $this->studentBId], null);

        $this->assertSame(2, $result['active_count']);
        $this->assertNull($result['top_misconceptions'][0]['weak_prerequisite']);
    }

    public function test_null_concept_ref_id_never_calls_root_blockers(): void
    {
        $misconceptionId = $this->makeMisconception(null);
        $this->flagActive($this->studentAId, $misconceptionId);

        $this->coherenceMap->shouldNotReceive('rootBlockers');

        $result = $this->misconceptionAnalysis->invoke($this->service, [$this->studentAId], null);

        $this->assertNull($result['top_misconceptions'][0]['weak_prerequisite']);
    }

    public function test_neo4j_failure_returns_null_without_breaking_the_report(): void
    {
        $misconceptionId = $this->makeMisconception($this->conceptId);
        $this->flagActive($this->studentAId, $misconceptionId);

        $this->coherenceMap->shouldReceive('rootBlockers')
            ->once()
            ->andThrow(new \App\Exceptions\GraphUnavailableException('Neo4j unreachable in test'));

        $result = $this->misconceptionAnalysis->invoke($this->service, [$this->studentAId], null);

        $this->assertSame(1, $result['active_count'], 'The report itself must still return the real active_count even when the graph lookup fails.');
        $this->assertNull($result['top_misconceptions'][0]['weak_prerequisite']);
    }

    public function test_no_root_blockers_found_returns_null_not_the_misconceptions_own_concept(): void
    {
        $misconceptionId = $this->makeMisconception($this->conceptId);
        $this->flagActive($this->studentAId, $misconceptionId);

        // A fully-mastered prerequisite chain: nothing unmastered to report.
        $this->coherenceMap->shouldReceive('rootBlockers')
            ->once()
            ->with($this->conceptId, $this->studentAId)
            ->andReturn([]);

        $result = $this->misconceptionAnalysis->invoke($this->service, [$this->studentAId], null);

        $this->assertNull($result['top_misconceptions'][0]['weak_prerequisite']);
    }
}
