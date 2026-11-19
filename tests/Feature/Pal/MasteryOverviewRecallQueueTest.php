<?php

namespace Tests\Feature\Pal;

use App\Models\Eso\LearnerNodeState;
use App\Services\PAL\Coherence\CoherenceMapRepository;
use App\Services\PAL\Plan\MasteryOverviewService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

/**
 * K7 — recall-queue reprioritization by fan-out. `CoherenceMapRepository` is
 * mocked throughout: its real `downstreamFanOut()` reads live Neo4j, which
 * these tests must not depend on for a deterministic ordering assertion —
 * exactly the same reasoning `GraphBrowserTeacherScopingTest` documents.
 * `downstreamFanOut()`'s own correctness against real data (not just that
 * `recallQueue()` uses whatever it returns correctly) is covered separately
 * by `CoherenceMapRepositoryFanOutTest`.
 */
class MasteryOverviewRecallQueueTest extends TestCase
{
    use DatabaseTransactions;

    private MasteryOverviewService $service;

    private $coherenceMap;

    private int $subInstituteId;

    private int $studentId;

    private int $subjectId;

    private int $standardId;

    private int $chapterId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->coherenceMap = Mockery::mock(CoherenceMapRepository::class);
        $this->app->instance(CoherenceMapRepository::class, $this->coherenceMap);
        $this->service = $this->app->make(MasteryOverviewService::class);

        $this->subInstituteId = (int) DB::table('school_setup')->insertGetId([
            'SchoolName' => 'Recall Queue Test School',
            'ShortCode' => 'RCQ' . random_int(1000, 9999),
            'ContactPerson' => 'Test Contact',
            'Mobile' => '9999999999',
            'Email' => 'recall-queue-test@example.com',
            'ReceiptHeader' => 'Test',
            'ReceiptAddress' => 'Test',
            'FeeEmail' => 'recall-queue-test@example.com',
            'ReceiptContact' => '9999999999',
            'SortOrder' => '1',
            'Logo' => '',
            'created_at' => now(),
        ]);

        $this->studentId = (int) DB::table('tblstudent')->insertGetId([
            'first_name' => 'Recall',
            'last_name' => 'Queue Learner',
            'sub_institute_id' => $this->subInstituteId,
            'file_size' => '',
            'file_type' => '',
        ]);

        $this->subjectId = (int) DB::table('subject')->insertGetId([
            'subject_name' => 'Recall Queue Test Subject ' . random_int(1000, 9999),
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
            'chapter_name' => 'Recall Queue Test Chapter',
            'created_at' => now(),
        ]);
    }

    private function makeDueItem(string $name, \DateTimeInterface|string $nextReviewAt, string $status = LearnerNodeState::STATUS_MASTERED): int
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

        $nodeId = (int) DB::table('pal_concept_nodes')->insertGetId([
            'concept_id' => $conceptId,
            'sub_institute_id' => $this->subInstituteId,
            'node_type' => 'K',
            'label' => $name . ' node',
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('learner_node_state')->insert([
            'student_id' => $this->studentId,
            'node_id' => $nodeId,
            'sub_institute_id' => $this->subInstituteId,
            'status' => $status,
            'retention_stage' => 1,
            'mastery_estimate' => 0.9,
            'next_review_at' => $nextReviewAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $conceptId;
    }

    public function test_higher_fan_out_due_concept_is_ranked_first(): void
    {
        $low = $this->makeDueItem('Low fan-out', now()->subDays(2));
        $high = $this->makeDueItem('High fan-out', now()->subDay());

        $this->coherenceMap->shouldReceive('downstreamFanOut')->with($low)->andReturn(1);
        $this->coherenceMap->shouldReceive('downstreamFanOut')->with($high)->andReturn(20);

        $result = $this->service->recallQueue($this->studentId, $this->subInstituteId);

        $this->assertCount(2, $result['due']);
        $this->assertSame($high, $result['due'][0]['concept_id'], 'The higher fan-out concept should be ranked first even though it is less overdue.');
        $this->assertSame($low, $result['due'][1]['concept_id']);
        $this->assertSame(20, $result['due'][0]['fan_out']);
        $this->assertSame(1, $result['due'][1]['fan_out']);
    }

    public function test_equal_fan_out_items_keep_next_review_at_order(): void
    {
        $earlier = $this->makeDueItem('Equal fan-out, earlier due', now()->subDays(3));
        $later = $this->makeDueItem('Equal fan-out, later due', now()->subDay());

        $this->coherenceMap->shouldReceive('downstreamFanOut')->with($earlier)->andReturn(5);
        $this->coherenceMap->shouldReceive('downstreamFanOut')->with($later)->andReturn(5);

        $result = $this->service->recallQueue($this->studentId, $this->subInstituteId);

        $this->assertSame($earlier, $result['due'][0]['concept_id'], 'With equal fan-out, the more-overdue (earlier next_review_at) item should come first.');
        $this->assertSame($later, $result['due'][1]['concept_id']);
    }

    public function test_upcoming_items_are_never_reordered_by_fan_out(): void
    {
        $dueConceptId = $this->makeDueItem('Due item', now()->subDay());
        $upcomingLow = $this->makeDueItem('Upcoming, low fan-out', now()->addDays(2));
        $upcomingHigh = $this->makeDueItem('Upcoming, high fan-out', now()->addDay());

        $this->coherenceMap->shouldReceive('downstreamFanOut')->with($dueConceptId)->andReturn(0);
        // upcoming items must never even be asked for a fan-out — recallQueue()
        // only ranks the due bucket. Mockery's default "no unexpected calls"
        // behaviour (no shouldReceive for these ids) makes an accidental call
        // on an upcoming item fail the test.

        $result = $this->service->recallQueue($this->studentId, $this->subInstituteId);

        $this->assertCount(1, $result['due']);
        $upcomingIds = array_column($result['upcoming'], 'concept_id');
        // Original query order is next_review_at ascending; the sooner
        // upcoming item (upcomingHigh, +1 day) must still precede the later
        // one (upcomingLow, +2 days) — unchanged from the plain date order,
        // despite its name implying it should be reprioritized if the due
        // bucket's logic were (wrongly) applied here too.
        $this->assertSame([$upcomingHigh, $upcomingLow], $upcomingIds);
    }

    public function test_neo4j_failure_falls_back_to_original_next_review_at_order(): void
    {
        $earlier = $this->makeDueItem('Fails over, earlier due', now()->subDays(3));
        $later = $this->makeDueItem('Fails over, later due', now()->subDay());

        $this->coherenceMap->shouldReceive('downstreamFanOut')
            ->andThrow(new \App\Exceptions\GraphUnavailableException('Neo4j unreachable in test'));

        $result = $this->service->recallQueue($this->studentId, $this->subInstituteId);

        $this->assertCount(2, $result['due']);
        $this->assertSame($earlier, $result['due'][0]['concept_id'], 'On a Neo4j failure, the due bucket must degrade to plain next_review_at order, not error or scramble.');
        $this->assertSame($later, $result['due'][1]['concept_id']);
        $this->assertSame(0, $result['due'][0]['fan_out']);
        $this->assertSame(0, $result['due'][1]['fan_out']);
    }

    public function test_empty_due_bucket_never_calls_fan_out_lookup(): void
    {
        $this->makeDueItem('Upcoming only', now()->addDays(5));

        // No shouldReceive() at all for downstreamFanOut — any call fails the test.
        $this->coherenceMap->shouldNotReceive('downstreamFanOut');

        $result = $this->service->recallQueue($this->studentId, $this->subInstituteId);

        $this->assertSame([], $result['due']);
        $this->assertCount(1, $result['upcoming']);
    }
}
