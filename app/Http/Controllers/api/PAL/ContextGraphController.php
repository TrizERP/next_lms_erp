<?php

namespace App\Http\Controllers\api\PAL;

use App\Http\Controllers\Controller;
use App\Services\PAL\ContextGraph\LearnerContextGraphService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Serves the Unified 4-Layer Context Graph for Learners.
 *
 * Combines User Profile, Learning Activities, Knowledge Graph Prerequisite Closures,
 * and Historical Learning Trajectory into a single graph payload.
 */
class ContextGraphController extends Controller
{
    public function __construct(
        private readonly LearnerContextGraphService $contextGraphService
    ) {
    }

    /**
     * GET /api/pal/context-graph/{learnerId}
     */
    public function show(Request $request, int $learnerId): JsonResponse
    {
        $auth = $this->auth($request);

        // Ownership enforcement: a student can only view their own context graph
        if (($auth['role'] ?? '') === 'student' && (int) ($auth['user_id'] ?? 0) !== $learnerId) {
            return $this->fail('You may only view your own context graph.', 403);
        }

        $tenantId = isset($auth['sub_institute_id']) ? (int) $auth['sub_institute_id'] : null;
        $subjectId = $request->filled('subject_id') ? (int) $request->input('subject_id') : null;

        $result = $this->contextGraphService->build($learnerId, $subjectId, $tenantId);

        if (! ($result['available'] ?? false)) {
            return $this->fail($result['message'] ?? 'Context graph unavailable for this learner.', 404);
        }

        return $this->ok($result);
    }

    private function auth(Request $request): array
    {
        $auth = $request->attributes->get('pal_auth');

        return is_array($auth) ? $auth : [];
    }

    private function ok($data, string $message = 'Success'): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data]);
    }

    private function fail(string $message, int $status = 422): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}

