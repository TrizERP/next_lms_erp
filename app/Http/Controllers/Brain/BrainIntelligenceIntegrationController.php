<?php

namespace App\Http\Controllers\Brain;

use App\Brain\Intelligence\ModuleIntegrationService;
use App\Brain\Intelligence\ModuleWorkflowService;
use App\Brain\Support\AcademicYear;
use App\Http\Controllers\Controller;
use App\Services\Platform\PlatformRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Real Cross-Module Integration and Cross-Module Workflow controller for all Intelligence modules.
 *
 * Scoped by authenticated tenant (`sub_institute_id`) and active academic year (`syear`).
 */
class BrainIntelligenceIntegrationController extends Controller
{
    private ModuleIntegrationService $integrationService;
    private ModuleWorkflowService $workflowService;

    public function __construct(
        ModuleIntegrationService $integrationService,
        ModuleWorkflowService $workflowService
    ) {
        $this->integrationService = $integrationService;
        $this->workflowService = $workflowService;
    }

    /**
     * GET /api/brain/{tenantId}/modules/{module}/integration
     * Also handles GET /api/brain/{tenantId}/{module}/integration
     */
    public function getIntegration(Request $request, string $tenantId, string $module): JsonResponse
    {
        $resolvedTenantId = (string) $request->attributes->get(
            'tenantId',
            $request->attributes->get('auth.tenantId', $tenantId)
        );

        $syear = AcademicYear::resolve($resolvedTenantId, $request->query('syear'));

        $data = $this->integrationService->getModuleIntegrations($module, $resolvedTenantId, $syear);

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * GET /api/brain/{tenantId}/modules/{module}/workflows
     * Also handles GET /api/brain/{tenantId}/{module}/workflows
     */
    public function getWorkflows(Request $request, string $tenantId, string $module): JsonResponse
    {
        $resolvedTenantId = (string) $request->attributes->get(
            'tenantId',
            $request->attributes->get('auth.tenantId', $tenantId)
        );

        $data = $this->workflowService->getModuleWorkflows($module, $resolvedTenantId);

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * POST /api/brain/{tenantId}/modules/{module}/workflows/{flowKey}/trigger
     * Also handles POST /api/brain/{tenantId}/{module}/workflows/{flowKey}/trigger
     */
    public function triggerWorkflow(Request $request, string $tenantId, string $module, string $flowKey): JsonResponse
    {
        $resolvedTenantId = (string) $request->attributes->get(
            'tenantId',
            $request->attributes->get('auth.tenantId', $tenantId)
        );

        $userId = (string) $request->attributes->get('auth.userId', $request->input('user_id', ''));
        $userName = (string) $request->attributes->get('auth.role', $request->input('user_name', 'Staff Operator'));

        $input = (array) $request->input('input', []);
        if ($request->has('note')) {
            $input['note'] = $request->input('note');
        }

        try {
            $result = $this->workflowService->triggerWorkflow(
                $flowKey,
                $resolvedTenantId,
                $userId,
                $input,
                $userName
            );

            return response()->json([
                'status' => 'success',
                'data' => $result,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 404);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unable to trigger workflow: ' . $e->getMessage(),
            ], 500);
        }
    }
}

