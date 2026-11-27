<?php

namespace Tests\Feature\AI\Fees;

use App\Domain\AI\Recommendations\FeesNextBestActionResolver;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * FeesNextBestActionResolver is a ranking read over already-governed
 * ai_recommendations/ai_cases rows. These tests write synthetic rows for
 * a real student inside a transaction (rolled back automatically, same
 * convention as KnowledgeGraphMenuCategoryTest) rather than depending on
 * whatever recommendations a live installation happens to already carry.
 */
class FeesNextBestActionResolverTest extends TestCase
{
    use DatabaseTransactions;

    private int $studentId;

    private int $subInstituteId;

    private McpRequestContext $scope;

    private FeesNextBestActionResolver $resolver;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $student = DB::table('tblstudent')->select('id', 'sub_institute_id')->whereNotNull('sub_institute_id')->first();

        if ($student === null) {
            $this->markTestSkipped('No students in this database to test against.');
        }

        $this->studentId = (int) $student->id;
        $this->subInstituteId = (int) $student->sub_institute_id;

        $this->scope = new McpRequestContext(
            userId: 1,
            role: 'admin',
            selectedInstituteId: $this->subInstituteId,
            allowedInstituteIds: [$this->subInstituteId],
            userProfileId: null,
            clientId: null,
            academicYear: null,
            termId: null,
            isAdmin: true,
            isStudent: false,
        );

        $this->resolver = app(FeesNextBestActionResolver::class);
    }

    private function seedRecommendation(array $overrides = []): int
    {
        $this->sequence++;
        $now = now()->addSeconds($this->sequence);

        return DB::table('ai_recommendations')->insertGetId(array_merge([
            'recommendation_reference' => 'TEST-REC-'.uniqid('', true),
            'case_id' => null,
            'domain' => 'k12',
            'action_type' => 'start_fees_collection_review',
            'title' => 'Test recommendation',
            'body' => 'Test body',
            'rationale' => 'Test rationale',
            'subject_entity_key' => 'student',
            'subject_id' => $this->studentId,
            'confidence' => 0.8,
            'risk_level' => 'medium',
            'is_consequential' => 1,
            'requires_approval' => 1,
            'verb' => 'recommend',
            'evidence_ids' => json_encode([1, 2]),
            'governance_passed' => 1,
            'workflow_key' => 'fees_collection',
            'status' => 'pending_approval',
            'sub_institute_id' => $this->subInstituteId,
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides));
    }

    private function seedEvidence(string $summary, bool $verified = true): int
    {
        $now = now();

        return DB::table('ai_evidence')->insertGetId([
            'kind' => 'fee_outstanding_total',
            'subject_entity_key' => 'student',
            'subject_id' => $this->studentId,
            'summary' => $summary,
            'source_service' => 'FeesArrearsService',
            'is_generated' => false,
            'verified' => $verified,
            'sub_institute_id' => $this->subInstituteId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function seedCase(array $overrides = []): int
    {
        $now = now();

        return DB::table('ai_cases')->insertGetId(array_merge([
            'case_reference' => 'TEST-CASE-'.uniqid('', true),
            'case_type' => 'fee_collection',
            'domain' => 'k12',
            'title' => 'Test case',
            'subject_entity_key' => 'student',
            'subject_id' => $this->studentId,
            'severity' => 'moderate',
            'priority_score' => 50,
            'status' => 'open',
            'opened_at' => $now,
            'sub_institute_id' => $this->subInstituteId,
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides));
    }

    public function test_nothing_found_is_reported_honestly(): void
    {
        $result = $this->resolver->topPickFor('student', $this->studentId, $this->scope);

        $this->assertFalse($result['available']);
        $this->assertNull($result['top_pick']);
        $this->assertStringContainsString((string) $this->studentId, $result['reason']);
    }

    public function test_a_single_qualifying_recommendation_is_picked(): void
    {
        $id = $this->seedRecommendation();

        $result = $this->resolver->topPickFor('student', $this->studentId, $this->scope);

        $this->assertTrue($result['available']);
        $this->assertSame('recommendation', $result['top_pick']['source']);
        $this->assertSame($id, $result['top_pick']['id']);
        $this->assertSame('fees_collection', $result['top_pick']['workflow_key']);
    }

    public function test_evidence_records_are_resolved_to_their_real_summary_text(): void
    {
        $evidenceId = $this->seedEvidence('Student X has ₹5,000 outstanding across 2 fee heads.');
        $this->seedRecommendation(['evidence_ids' => json_encode([$evidenceId])]);

        $result = $this->resolver->topPickFor('student', $this->studentId, $this->scope);

        $this->assertTrue($result['available']);
        $this->assertCount(1, $result['top_pick']['evidence_records']);
        $this->assertSame($evidenceId, $result['top_pick']['evidence_records'][0]['id']);
        $this->assertSame('Student X has ₹5,000 outstanding across 2 fee heads.', $result['top_pick']['evidence_records'][0]['summary']);
        $this->assertTrue($result['top_pick']['evidence_records'][0]['verified']);
    }

    public function test_evidence_from_another_tenant_is_never_leaked_in(): void
    {
        // A different sub_institute_id than this test's own student — the
        // resolver must not resolve an evidence id into another tenant's row.
        $foreignEvidenceId = DB::table('ai_evidence')->insertGetId([
            'kind' => 'fee_outstanding_total',
            'subject_entity_key' => 'student',
            'subject_id' => $this->studentId,
            'summary' => 'This belongs to a different tenant.',
            'is_generated' => false,
            'verified' => true,
            'sub_institute_id' => $this->subInstituteId + 999999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->seedRecommendation(['evidence_ids' => json_encode([$foreignEvidenceId])]);

        $result = $this->resolver->topPickFor('student', $this->studentId, $this->scope);

        $this->assertSame([], $result['top_pick']['evidence_records']);
    }

    public function test_higher_risk_level_outranks_higher_confidence(): void
    {
        $this->seedRecommendation(['risk_level' => 'low', 'confidence' => 0.99]);
        $highId = $this->seedRecommendation(['risk_level' => 'high', 'confidence' => 0.41]);

        $result = $this->resolver->topPickFor('student', $this->studentId, $this->scope);

        $this->assertSame($highId, $result['top_pick']['id'], 'Risk level must outrank confidence.');
    }

    public function test_among_equal_risk_higher_confidence_wins(): void
    {
        $this->seedRecommendation(['risk_level' => 'medium', 'confidence' => 0.5]);
        $strongerId = $this->seedRecommendation(['risk_level' => 'medium', 'confidence' => 0.9]);

        $result = $this->resolver->topPickFor('student', $this->studentId, $this->scope);

        $this->assertSame($strongerId, $result['top_pick']['id']);
    }

    public function test_a_recommendation_that_failed_governance_is_never_picked(): void
    {
        $this->seedRecommendation(['governance_passed' => 0, 'risk_level' => 'critical', 'confidence' => 1.0]);
        $passedId = $this->seedRecommendation(['governance_passed' => 1, 'risk_level' => 'low', 'confidence' => 0.4]);

        $result = $this->resolver->topPickFor('student', $this->studentId, $this->scope);

        $this->assertSame($passedId, $result['top_pick']['id'], 'A governance-failed draft must never outrank a passed one, regardless of claimed risk.');
    }

    public function test_an_approved_recommendation_is_not_reconsidered(): void
    {
        $this->seedRecommendation(['status' => 'approved']);

        $result = $this->resolver->topPickFor('student', $this->studentId, $this->scope);

        $this->assertFalse($result['available'], 'Only pending_approval/draft recommendations are live candidates.');
    }

    public function test_falls_back_to_an_open_case_when_no_recommendation_exists(): void
    {
        $caseId = $this->seedCase();

        $result = $this->resolver->topPickFor('student', $this->studentId, $this->scope);

        $this->assertTrue($result['available']);
        $this->assertSame('case', $result['top_pick']['source']);
        $this->assertSame($caseId, $result['top_pick']['id']);
    }

    public function test_a_recommendation_always_outranks_a_bare_case(): void
    {
        $this->seedCase();
        $recId = $this->seedRecommendation();

        $result = $this->resolver->topPickFor('student', $this->studentId, $this->scope);

        $this->assertSame('recommendation', $result['top_pick']['source']);
        $this->assertSame($recId, $result['top_pick']['id']);
    }

    public function test_a_closed_case_is_not_surfaced(): void
    {
        $this->seedCase(['status' => 'closed']);

        $result = $this->resolver->topPickFor('student', $this->studentId, $this->scope);

        $this->assertFalse($result['available']);
    }

    /**
     * A tenant-wide query, unlike every per-subject one above, has no
     * (student_id, action_type) narrowing — so running it against
     * $this->subInstituteId (whichever real tenant setUp() happened to pick)
     * would see that tenant's real, unrelated recommendations too. These
     * three tests use a synthetic tenant id instead, guaranteed to carry
     * nothing but what each test seeds.
     */
    private function syntheticTenantScope(): McpRequestContext
    {
        return new McpRequestContext(
            userId: 1, role: 'admin', selectedInstituteId: 999888777, allowedInstituteIds: [999888777],
            userProfileId: null, clientId: null, academicYear: 2026, termId: null,
            isAdmin: true, isStudent: false
        );
    }

    public function test_tenant_wide_pick_finds_the_same_recommendation_without_naming_a_subject(): void
    {
        $scope = $this->syntheticTenantScope();
        $recId = $this->seedRecommendation(['sub_institute_id' => $scope->selectedInstituteId]);

        $result = $this->resolver->topPickForTenant($scope);

        $this->assertTrue($result['available']);
        $this->assertSame($recId, $result['top_pick']['id']);
        $this->assertSame('student', $result['top_pick']['subject_entity_key'], 'the tenant-wide pick must say which subject it is actually about');
        $this->assertSame($this->studentId, $result['top_pick']['subject_id']);
    }

    public function test_tenant_wide_pick_ranks_across_subjects_the_same_way_as_per_subject(): void
    {
        $scope = $this->syntheticTenantScope();
        $this->seedRecommendation(['sub_institute_id' => $scope->selectedInstituteId, 'risk_level' => 'low', 'confidence' => 0.4]);

        $higherRiskId = $this->seedRecommendation([
            'sub_institute_id' => $scope->selectedInstituteId,
            'risk_level' => 'critical',
            'confidence' => 0.9,
            'subject_id' => $this->studentId + 1,
        ]);

        $result = $this->resolver->topPickForTenant($scope);

        $this->assertSame($higherRiskId, $result['top_pick']['id'], 'the tenant-wide pick must still rank by risk/confidence, not just find the first subject with anything');
    }

    public function test_tenant_wide_pick_is_honestly_unavailable_when_nothing_qualifies_anywhere(): void
    {
        $result = $this->resolver->topPickForTenant($this->syntheticTenantScope());

        $this->assertFalse($result['available']);
        $this->assertStringContainsString('this school', $result['reason']);
    }
}
