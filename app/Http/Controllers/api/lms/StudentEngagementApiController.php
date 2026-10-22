<?php

namespace App\Http\Controllers\api\lms;

use App\Services\Mcp\EngagementReportService;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * LMS Engagement -> Student Activity REST API (K12 frontend).
 *
 *   GET /api/lms/student-engagement             the caller's own activity/summary
 *   GET /api/lms/student-engagement/{userId}     one learner's activity/summary
 *
 * Added 2026-10-10 alongside the real `lms_student_engagement` writers
 * (`palController::incrementContentVisit`, `assessmentQuestionController::
 * submitPractice`, `HomeworkSubmissionApiController::submit`) and the graph
 * projection that turns those rows into `(:StuDetail)-[:ENGAGED_WITH]->
 * (:Concept|:TeachingResource)` edges (`EngagementGraphProjection`).
 *
 * Business logic lives in `App\Services\Mcp\EngagementReportService` — the
 * SAME service the `engagement.student_summary` MCP tool already calls, now
 * extended to also read the real per-event log (see that class's docblock).
 * This controller is a thin HTTP wrapper over it: an MCP tool is invoked by an
 * agent, not fetched by a browser, so the K12 Next.js frontend needs this
 * plain JSON endpoint to show the same data on a real page. No business logic
 * is duplicated between the two.
 *
 * Authorization mirrors `LmsLeaderboardApiController` exactly: a student may
 * only ever read their OWN activity; staff/admin are confined to their own
 * institute and, for `{userId}`, to learners actually enrolled in it.
 */
class StudentEngagementApiController extends BaseLmsEngagementApiController
{
    public function __construct(private readonly EngagementReportService $engagement)
    {
    }

    /** GET /api/lms/student-engagement */
    public function index(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $ctx = $this->context($request);

            return $this->success(
                $this->summaryFor($ctx['user_id'], $ctx['sub_institute_id'], $request),
                'Engagement activity fetched successfully.'
            );
        });
    }

    /** GET /api/lms/student-engagement/{userId} */
    public function show(Request $request, $userId): JsonResponse
    {
        return $this->run(function () use ($request, $userId) {
            $ctx = $this->context($request);
            $userId = (int) $userId;

            if ($userId <= 0) {
                return $this->error('A valid learner id is required.', 422);
            }

            if ($ctx['is_student'] && $userId !== $ctx['user_id']) {
                return $this->error('You can only view your own engagement activity.', 403);
            }

            if ($userId !== $ctx['user_id'] && ! $this->learnerInInstitute($userId, $ctx['sub_institute_id'])) {
                return $this->error('This learner is not part of your institute.', 404);
            }

            return $this->success(
                $this->summaryFor($userId, $ctx['sub_institute_id'], $request),
                'Engagement activity fetched successfully.'
            );
        });
    }

    // -----------------------------------------------------------------------

    private function summaryFor(int $studentId, int $subInstituteId, Request $request): array
    {
        $daysBack = max(1, min(180, (int) $request->input('days_back', 30)));
        $limit = max(1, min(100, (int) $request->input('limit', 50)));

        $context = new McpRequestContext(
            userId: $studentId,
            role: 'student',
            selectedInstituteId: $subInstituteId,
            allowedInstituteIds: [$subInstituteId],
            userProfileId: null,
            clientId: null,
            academicYear: null,
            termId: null,
            isAdmin: false,
            isStudent: true,
        );

        $summary = $this->engagement->studentSummary($context, [
            'student_id' => $studentId,
            'days_back' => $daysBack,
        ]);

        // The MCP summary's `activity_log` already honours `days_back`; a
        // distinct `limit` (how many recent events to list, independent of
        // the window they are drawn from) is an HTTP-API-only refinement, so
        // it is applied here rather than threaded through the shared method.
        if ($limit !== 50 && isset($summary['activity_log']['recent'])) {
            $summary['activity_log']['recent'] = $this->engagement
                ->activityLog($subInstituteId, $studentId, $daysBack, $limit)['recent'];
        }

        return $summary;
    }

    private function learnerInInstitute(int $userId, int $subInstituteId): bool
    {
        return DB::table('tblstudent')
            ->where('id', $userId)
            ->where('sub_institute_id', $subInstituteId)
            ->exists();
    }
}
