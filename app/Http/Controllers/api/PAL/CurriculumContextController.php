<?php

namespace App\Http\Controllers\api\PAL;

use App\Domain\K12\TeacherClassScopeService;
use App\Http\Controllers\Controller;
use App\Services\PAL\ContextGraph\CurriculumContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/pal/curriculum-context/{curriculumId}
 *
 * Curriculum-grain context: structure & coverage, lesson-pacing status,
 * learning outcomes, and any governed pacing-gap signals for one
 * `lms_curriculum` row. Same auth shape as CourseContextController/
 * AssessmentContextController -- students denied outright, staff scoped via
 * the curriculum's own (standard, subject), admins unrestricted.
 */
class CurriculumContextController extends Controller
{
    public function __construct(
        private readonly CurriculumContextService $curriculumContextService,
        private readonly TeacherClassScopeService $classScope,
    ) {
    }

    public function show(Request $request, int $curriculumId): JsonResponse
    {
        $auth = $this->auth($request);

        if (($auth['role'] ?? '') === 'student') {
            return $this->fail('Curriculum context is not available to students.', 403);
        }

        $tenantId = isset($auth['sub_institute_id']) ? (int) $auth['sub_institute_id'] : null;

        if ($tenantId === null) {
            return $this->fail('Unable to resolve your institute.', 403);
        }

        $curriculum = DB::table('lms_curriculum')
            ->where('id', $curriculumId)
            ->where('sub_institute_id', $tenantId)
            ->first(['standard_id', 'subject_id', 'syear']);

        if ($curriculum === null) {
            return $this->fail("Curriculum {$curriculumId} could not be resolved.", 404);
        }

        if (($auth['role'] ?? '') === 'staff'
            && (int) ($auth['is_admin'] ?? 0) < 1
            && $curriculum->standard_id !== null
            && $curriculum->subject_id !== null
        ) {
            $syear = $curriculum->syear ?? DB::table('tblstudent_enrollment')
                ->where('standard_id', $curriculum->standard_id)
                ->where('sub_institute_id', $tenantId)
                ->orderByDesc('syear')
                ->value('syear');

            $inScope = $syear !== null && $this->classScope->isCourseInTeacherScope(
                (int) $auth['user_id'],
                $tenantId,
                (int) $curriculum->standard_id,
                (int) $curriculum->subject_id,
                (string) $syear
            );

            if (! $inScope) {
                return $this->fail('This curriculum is not in one of your assigned classes.', 403);
            }
        }

        $result = $this->curriculumContextService->build($curriculumId, $tenantId);

        if (! ($result['available'] ?? false)) {
            return $this->fail($result['message'] ?? 'Curriculum context unavailable.', 404);
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
