<?php

namespace App\Http\Controllers\api\PAL;

use App\Domain\K12\TeacherClassScopeService;
use App\Http\Controllers\Controller;
use App\Services\PAL\ContextGraph\CourseContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/pal/course-context/{standardId}/{subjectId}
 *
 * Course-grain context: enrolled students, content & assessments,
 * attendance/timetable, exam & historical performance for one
 * (standard, subject) pair. Class-roster/teacher tool, not self-service —
 * students are denied outright.
 *
 * PalApiAuth's learner-scoping (`resolveTargetLearner()`) only resolves a
 * `{learnerId}`-shaped route, so it does nothing for this route beyond
 * central JWT authentication — the course-grain scope check below is this
 * controller's own responsibility.
 */
class CourseContextController extends Controller
{
    public function __construct(
        private readonly CourseContextService $courseContextService,
        private readonly TeacherClassScopeService $classScope,
    ) {
    }

    public function show(Request $request, int $standardId, int $subjectId): JsonResponse
    {
        $auth = $this->auth($request);

        if (($auth['role'] ?? '') === 'student') {
            return $this->fail('Course context is not available to students.', 403);
        }

        $tenantId = isset($auth['sub_institute_id']) ? (int) $auth['sub_institute_id'] : null;

        if ($tenantId === null) {
            return $this->fail('Unable to resolve your institute.', 403);
        }

        if (($auth['role'] ?? '') === 'staff' && (int) ($auth['is_admin'] ?? 0) < 1) {
            $syear = DB::table('tblstudent_enrollment')
                ->where('standard_id', $standardId)
                ->where('sub_institute_id', $tenantId)
                ->orderByDesc('syear')
                ->value('syear');

            $inScope = $syear !== null && $this->classScope->isCourseInTeacherScope(
                (int) $auth['user_id'],
                $tenantId,
                $standardId,
                $subjectId,
                (string) $syear
            );

            if (! $inScope) {
                return $this->fail('This course is not in one of your assigned classes.', 403);
            }
        }

        $result = $this->courseContextService->build($standardId, $subjectId, $tenantId);

        if (! ($result['available'] ?? false)) {
            return $this->fail($result['message'] ?? 'Course context unavailable.', 404);
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
