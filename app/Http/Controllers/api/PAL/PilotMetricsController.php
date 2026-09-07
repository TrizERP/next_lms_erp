<?php

namespace App\Http\Controllers\api\PAL;

use App\Http\Controllers\Controller;
use App\Services\Pilot\PilotMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only pilot metrics — the report the Developer Brief's §7/§8 needs,
 * not a dashboard. See docs/CHAPTER_1014_PILOT_MEASUREMENT_PLAN.md.
 *
 * Staff/admin only — this is aggregate cohort data, not a single learner's
 * own record, so it does not go through PalApiAuth's per-learner ownership
 * check (no {learnerId} in the route); it is explicitly gated here instead.
 */
class PilotMetricsController extends Controller
{
    public function __construct(protected PilotMetricsService $metrics)
    {
    }

    /**
     * GET /api/pal/eso/pilot/metrics?chapterId=1014&cohortLabel=2026-pilot-1
     */
    public function summary(Request $request): JsonResponse
    {
        $auth = $request->attributes->get('pal_auth');
        $role = is_array($auth) ? ($auth['role'] ?? '') : '';
        if ($role === 'student') {
            return response()->json(['success' => false, 'message' => 'Pilot metrics are staff/admin only.'], 403);
        }

        $validated = $request->validate([
            'chapterId' => 'required|integer',
            'cohortLabel' => 'nullable|string|max:64',
        ]);

        $summary = $this->metrics->summary(
            (int) $validated['chapterId'],
            $validated['cohortLabel'] ?? null
        );

        return response()->json(['success' => true, 'message' => 'Success', 'data' => $summary]);
    }
}
