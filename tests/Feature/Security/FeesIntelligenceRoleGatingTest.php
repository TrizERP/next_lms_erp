<?php

namespace Tests\Feature\Security;

use App\Http\Controllers\AI\FeesAgentController;
use App\Http\Controllers\AI\FeesCrossModuleInsightController;
use App\Http\Controllers\AI\FeesGraphReasoningController;
use App\Http\Controllers\AI\FeesGraphRecommendationController;
use App\Http\Controllers\AI\FeesNextBestActionController;
use App\Http\Controllers\AI\FeesPredictiveInsightController;
use App\Http\Controllers\AI\FeesRecommendationIntelligenceController;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * `/api/ai/*` is reachable by any valid JWT, including students
 * (McpAuth derives only 'student'/'admin'/'staff' — see its own role
 * derivation). This is a regression guard that every Fees Intelligence
 * read endpoint refuses a student-role caller, and that the Fees agent's
 * operational controls (start/stop/interval/run-now/pipeline) refuse
 * anyone who is not a true administrator.
 *
 * KNOWN GAP (documented, not fixed here): this platform's MCP auth model
 * has no distinct "parent" role, so a parent token that does not set
 * `is_student=true` would be bucketed as 'staff' by McpAuth and pass this
 * check — see RequiresFeesStaffAccess's own docblock.
 */
class FeesIntelligenceRoleGatingTest extends TestCase
{
    private function scopeFor(string $role, bool $isStudent, bool $isAdmin): McpRequestContext
    {
        return new McpRequestContext(
            userId: 1,
            role: $role,
            selectedInstituteId: 1,
            allowedInstituteIds: [1],
            userProfileId: $isAdmin ? 1 : 99,
            clientId: null,
            academicYear: null,
            termId: null,
            isAdmin: $isAdmin,
            isStudent: $isStudent,
        );
    }

    private function request(array $query = []): Request
    {
        return Request::create('/api/ai/fees/test', 'GET', $query);
    }

    private function asStudent(): McpRequestContext
    {
        return $this->scopeFor('student', true, false);
    }

    private function asStaff(): McpRequestContext
    {
        return $this->scopeFor('staff', false, false);
    }

    /** @dataProvider readEndpoints */
    public function test_student_role_is_refused_on_every_read_endpoint(string $controllerClass, string $method, array $query): void
    {
        $request = $this->request($query);
        $request->attributes->set('mcp_context', $this->asStudent());

        $response = app($controllerClass)->{$method}($request);
        $payload = json_decode($response->getContent(), true);

        $this->assertSame(403, $response->getStatusCode(), "$controllerClass::$method must refuse a student token.");
        $this->assertFalse($payload['success']);
    }

    public static function readEndpoints(): array
    {
        return [
            'cross-module' => [FeesCrossModuleInsightController::class, 'index', ['syear' => '2024']],
            'graph-reasoning' => [FeesGraphReasoningController::class, 'index', ['syear' => '2024']],
            'predictive' => [FeesPredictiveInsightController::class, 'index', []],
            'legacy graph-recommendations' => [FeesGraphRecommendationController::class, 'index', ['syear' => '2024']],
            'next-best-action' => [FeesNextBestActionController::class, 'index', ['subject_entity_key' => 'student', 'subject_id' => 1]],
            'aggregator' => [FeesRecommendationIntelligenceController::class, 'index', ['syear' => '2024']],
            'agent state' => [FeesAgentController::class, 'state', []],
            'agent eso' => [FeesAgentController::class, 'eso', []],
        ];
    }

    /** @dataProvider operationalEndpoints */
    public function test_non_admin_staff_is_refused_on_every_operational_agent_control(string $method): void
    {
        $request = $this->request(['interval_seconds' => 300]);
        $request->attributes->set('mcp_context', $this->asStaff());

        $response = app(FeesAgentController::class)->{$method}($request);
        $payload = json_decode($response->getContent(), true);

        $this->assertSame(403, $response->getStatusCode(), "FeesAgentController::$method must refuse a non-admin staff token.");
        $this->assertFalse($payload['success']);
    }

    public static function operationalEndpoints(): array
    {
        return [
            ['start'],
            ['stop'],
            ['runNow'],
            ['setInterval'],
            ['pipeline'],
        ];
    }

    public function test_staff_role_can_read_agent_state(): void
    {
        $request = $this->request();
        $request->attributes->set('mcp_context', $this->asStaff());

        $response = app(FeesAgentController::class)->state($request);
        $payload = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($payload['success']);
    }

    public function test_true_administrator_is_not_refused_by_the_operational_gate(): void
    {
        // isAdmin:true is the branch AdministratorCheck::isAdministrator()
        // accepts immediately, with no DB profile-chain lookup needed — a
        // reliable, data-independent way to construct a context a true
        // administrator would present, rather than depending on a specific
        // tenant's tbluserprofilemaster hierarchy existing in this database.
        $admin = $this->scopeFor('admin', false, true);

        $request = $this->request(['interval_seconds' => 300]);
        $request->attributes->set('mcp_context', $admin);

        $response = app(FeesAgentController::class)->setInterval($request);
        $payload = json_decode($response->getContent(), true);

        $this->assertNotSame(403, $response->getStatusCode(), 'A true administrator must not be refused by the operational gate.');
        $this->assertTrue($payload['success'] ?? false);
    }
}
