<?php

namespace Tests\Feature\AI\Fees;

use App\Http\Controllers\AI\FeesNextBestActionController;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FeesNextBestActionControllerTest extends TestCase
{
    use DatabaseTransactions;

    private int $studentId;

    private int $subInstituteId;

    protected function setUp(): void
    {
        parent::setUp();

        $student = DB::table('tblstudent')->select('id', 'sub_institute_id')->whereNotNull('sub_institute_id')->first();

        if ($student === null) {
            $this->markTestSkipped('No students in this database to test against.');
        }

        $this->studentId = (int) $student->id;
        $this->subInstituteId = (int) $student->sub_institute_id;
    }

    private function seedStaffRecommendation(): void
    {
        $now = now();

        DB::table('ai_recommendations')->insert([
            'recommendation_reference' => 'TEST-REC-'.uniqid('', true),
            'domain' => 'k12',
            'action_type' => 'start_fees_collection_review',
            'title' => 'Review the unpaid fees',
            'subject_entity_key' => 'student',
            'subject_id' => $this->studentId,
            'confidence' => 0.8,
            'risk_level' => 'medium',
            'is_consequential' => 1,
            'requires_approval' => 1,
            'verb' => 'recommend',
            'evidence_ids' => json_encode([1]),
            'governance_passed' => 1,
            'workflow_key' => 'fees_collection',
            'status' => 'pending_approval',
            'sub_institute_id' => $this->subInstituteId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function callController(array $query): array
    {
        $request = Request::create('/api/ai/fees/recommendation-intelligence/next-action', 'GET', $query);
        $request->attributes->set('mcp_context', new McpRequestContext(
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
        ));

        $response = app(FeesNextBestActionController::class)->index($request);

        return json_decode($response->getContent(), true);
    }

    public function test_staff_audience_sees_the_staff_only_workflow(): void
    {
        $this->seedStaffRecommendation();

        $payload = $this->callController(['subject_entity_key' => 'student', 'subject_id' => $this->studentId]);

        $this->assertTrue($payload['data']['available']);
        $this->assertSame('fees_collection', $payload['data']['top_pick']['workflow_key']);
    }

    public function test_parent_audience_does_not_see_the_staff_only_workflow(): void
    {
        $this->seedStaffRecommendation();

        $payload = $this->callController([
            'subject_entity_key' => 'student',
            'subject_id' => $this->studentId,
            'audience' => 'parent',
        ]);

        $this->assertFalse($payload['data']['available'], 'A staff-only action must be hidden from the parent audience, not relabelled.');
        $this->assertNull($payload['data']['top_pick']);
    }

    public function test_parent_audience_with_nothing_found_is_also_honest(): void
    {
        $payload = $this->callController([
            'subject_entity_key' => 'student',
            'subject_id' => $this->studentId,
            'audience' => 'parent',
        ]);

        $this->assertFalse($payload['data']['available']);
    }
}
