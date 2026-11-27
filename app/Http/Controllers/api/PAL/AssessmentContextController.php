<?php

namespace App\Http\Controllers\api\PAL;

use App\Domain\K12\TeacherClassScopeService;
use App\Http\Controllers\Controller;
use App\Services\PAL\ContextGraph\AssessmentContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/pal/assessment-context/{assessmentId}
 *
 * Per-assessment context: related course & students, participants & results,
 * related activity history. $assessmentId is a `question_paper.id`.
 * Class-roster/teacher tool, not self-service — students are denied outright,
 * same rationale as CourseContextController.
 */
class AssessmentContextController extends Controller
{
    public function __construct(
        private readonly AssessmentContextService $assessmentContextService,
        private readonly TeacherClassScopeService $classScope,
    ) {
    }

    public function show(Request $request, int $assessmentId): JsonResponse
    {
        $auth = $this->auth($request);

        if (($auth['role'] ?? '') === 'student') {
            return $this->fail('Assessment context is not available to students.', 403);
        }

        $tenantId = isset($auth['sub_institute_id']) ? (int) $auth['sub_institute_id'] : null;

        if ($tenantId === null) {
            return $this->fail('Unable to resolve your institute.', 403);
        }

        $paper = DB::table('question_paper')
            ->where('id', $assessmentId)
            ->where('sub_institute_id', $tenantId)
            ->first(['standard_id', 'subject_id']);

        if ($paper === null) {
            return $this->fail("Assessment {$assessmentId} could not be resolved.", 404);
        }

        if (($auth['role'] ?? '') === 'staff'
            && (int) ($auth['is_admin'] ?? 0) < 1
            && $paper->standard_id !== null
            && $paper->subject_id !== null
        ) {
            $syear = DB::table('tblstudent_enrollment')
                ->where('standard_id', $paper->standard_id)
                ->where('sub_institute_id', $tenantId)
                ->orderByDesc('syear')
                ->value('syear');

            $inScope = $syear !== null && $this->classScope->isCourseInTeacherScope(
                (int) $auth['user_id'],
                $tenantId,
                (int) $paper->standard_id,
                (int) $paper->subject_id,
                (string) $syear
            );

            if (! $inScope) {
                return $this->fail('This assessment is not in one of your assigned classes.', 403);
            }
        }

        $result = $this->assessmentContextService->build($assessmentId, $tenantId);

        if (! ($result['available'] ?? false)) {
            return $this->fail($result['message'] ?? 'Assessment context unavailable.', 404);
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
