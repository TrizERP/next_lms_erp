<?php

namespace App\Http\Controllers\api\PAL;

use App\Http\Controllers\Controller;
use App\Services\PAL\Reporting\StudentActivityReportDocxService;
use App\Services\PAL\Reporting\StudentActivityReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * PAL Student Activity & Performance Report.
 *
 *   GET /api/pal/eso/reports/activity/{learnerId}            one learner, JSON
 *   GET /api/pal/eso/reports/activity/class                  a whole class, JSON (staff/admin only)
 *   GET /api/pal/eso/reports/activity/{learnerId}/download    the same report, as .docx
 *   GET /api/pal/eso/reports/activity/class/download          the same class report, as .docx
 *
 * OWNERSHIP
 * The two {learnerId} actions carry no authorization logic of their own:
 * every route in this file lives inside the pal.auth middleware group, and
 * PalApiAuth::resolveTargetLearner()/authorizeLearner() already enforce
 * "student=self, staff=teacher-of-student, admin=institute" for ANY route
 * with a {learnerId} segment, the same way every other PAL V4 route gets
 * that check for free. Nothing here re-implements it.
 *
 * The class actions carry NO {learnerId}, so that automatic gate does not
 * apply to them — they are gated explicitly, in requireClassScope() below,
 * the same two-step AttainmentReportController uses for its own cohort
 * report: resolve the caller's own institute from the session (never from
 * the request), then — for a plain subject/class teacher, as opposed to an
 * institute admin — confirm the specific standard/section is one of theirs,
 * via class_teacher (homeroom) or timetable (subject teacher), mirroring
 * PalApiAuth::isStudentInTeacherScope()'s own two sources one level up (a
 * whole class instead of one named student).
 */
class StudentActivityReportController extends Controller
{
    public function __construct(
        protected StudentActivityReportService $report,
        protected StudentActivityReportDocxService $docx
    ) {
    }

    /**
     * GET /api/pal/eso/reports/activity/{learnerId}?syear=&subject_id=&from=&to=
     */
    public function studentReport(Request $request, int $learnerId): JsonResponse
    {
        $subInstituteId = $this->resolveLearnerInstitute($learnerId);
        if ($subInstituteId === null) {
            return $this->fail('Unknown learner.', 404);
        }

        $validated = $this->validateStudentInput($request);

        return $this->ok($this->report->buildStudentReport(
            $subInstituteId,
            (string) $learnerId,
            (string) $validated['syear'],
            isset($validated['subject_id']) ? (string) $validated['subject_id'] : null,
            $validated['from'] ?? null,
            $validated['to'] ?? null
        ));
    }

    /**
     * GET /api/pal/eso/reports/activity/{learnerId}/download?syear=&subject_id=&from=&to=
     */
    public function studentReportDownload(Request $request, int $learnerId)
    {
        $subInstituteId = $this->resolveLearnerInstitute($learnerId);
        if ($subInstituteId === null) {
            return $this->fail('Unknown learner.', 404);
        }

        $validated = $this->validateStudentInput($request);

        $data = $this->report->buildStudentReport(
            $subInstituteId,
            (string) $learnerId,
            (string) $validated['syear'],
            isset($validated['subject_id']) ? (string) $validated['subject_id'] : null,
            $validated['from'] ?? null,
            $validated['to'] ?? null
        );

        $binary = $this->docx->generateStudentReport($data);
        $filename = sprintf('activity-report-%d-%s.docx', $learnerId, now()->format('Ymd-His'));

        return $this->streamDocx($binary, $filename);
    }

    /**
     * GET /api/pal/eso/reports/activity/class?standard_id=&syear=&section_id=&subject_id=&from=&to=
     */
    public function classReport(Request $request): JsonResponse
    {
        $scope = $this->requireClassScope($request);
        if (! is_array($scope)) {
            return $scope;
        }

        return $this->ok($this->report->buildClassReport(
            $scope['sub_institute_id'],
            (string) $scope['standard_id'],
            (string) $scope['syear'],
            isset($scope['section_id']) ? (string) $scope['section_id'] : null,
            isset($scope['subject_id']) ? (string) $scope['subject_id'] : null,
            $scope['from'] ?? null,
            $scope['to'] ?? null
        ));
    }

    /**
     * GET /api/pal/eso/reports/activity/class/download?standard_id=&syear=&section_id=&subject_id=&from=&to=
     */
    public function classReportDownload(Request $request)
    {
        $scope = $this->requireClassScope($request);
        if (! is_array($scope)) {
            return $scope;
        }

        $data = $this->report->buildClassReport(
            $scope['sub_institute_id'],
            (string) $scope['standard_id'],
            (string) $scope['syear'],
            isset($scope['section_id']) ? (string) $scope['section_id'] : null,
            isset($scope['subject_id']) ? (string) $scope['subject_id'] : null,
            $scope['from'] ?? null,
            $scope['to'] ?? null
        );

        $binary = $this->docx->generateClassReport($data);
        $filename = sprintf('class-activity-report-%d-%s.docx', (int) $scope['standard_id'], now()->format('Ymd-His'));

        return $this->streamDocx($binary, $filename);
    }

    /**
     * GET /api/pal/eso/reports/activity/class/roster?standard_id=&syear=&section_id=&subject_id=&from=&to=
     *
     * The named-student counterpart to classReport(): one row per student
     * in the cohort — {student_id, name, mastered_pct, bucket} — instead of
     * that method's class-wide aggregate. Scoped by the exact same
     * requireClassScope() this file already uses for classReport()/
     * classReportDownload(), so a plain subject/class teacher is confirmed
     * against their assigned classes exactly as before; nothing new is
     * invented. `from`/`to` are accepted (requireClassScope() validates
     * them for parity with the sibling route) but unused here: mastery
     * state and the plateau/regression engines are current-state reads,
     * not event-log reads, the same reason buildClassReport()'s own
     * mastery_overview section ignores the date window (see
     * StudentActivityReportService's class docblock, "DATE SCOPING").
     */
    public function classRoster(Request $request): JsonResponse
    {
        $scope = $this->requireClassScope($request);
        if (! is_array($scope)) {
            return $scope;
        }

        return $this->ok($this->report->buildCohortRoster(
            $scope['sub_institute_id'],
            (string) $scope['standard_id'],
            (string) $scope['syear'],
            isset($scope['section_id']) ? (string) $scope['section_id'] : null,
            isset($scope['subject_id']) ? (string) $scope['subject_id'] : null
        ));
    }

    // -------------------------------------------------------------------
    // Shared helpers
    // -------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function validateStudentInput(Request $request): array
    {
        return $request->validate([
            'syear' => 'required|string|max:16',
            'subject_id' => 'nullable|integer',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);
    }

    /**
     * The institute a {learnerId} route may act on. tblstudent first (the
     * normal case), falling back to tbluser — the same two tables and order
     * PalApiAuth::resolveLearnerTenant() already checks for exactly this
     * reason: a learner id is not always a student id.
     */
    private function resolveLearnerInstitute(int $learnerId): ?int
    {
        $value = DB::table('tblstudent')->where('id', $learnerId)->value('sub_institute_id');
        if ($value !== null && $value !== '') {
            return (int) $value;
        }

        $value = DB::table('tbluser')->where('id', $learnerId)->value('sub_institute_id');

        return ($value !== null && $value !== '') ? (int) $value : null;
    }

    /**
     * Validates the class-report input, resolves the caller's own institute,
     * refuses a student caller, and — for a plain staff/subject teacher —
     * refuses a standard/section outside their assigned classes. Returns the
     * resolved scope array on success, or the JsonResponse to send back.
     *
     * @return array<string, mixed>|JsonResponse
     */
    private function requireClassScope(Request $request)
    {
        $auth = $request->attributes->get('pal_auth');
        $role = is_array($auth) ? ($auth['role'] ?? '') : '';

        if ($role === 'student') {
            return $this->fail('This report is staff/admin only.', 403);
        }

        $subInstituteClaim = is_array($auth) ? ($auth['sub_institute_id'] ?? null) : null;
        if ($subInstituteClaim === null || $subInstituteClaim === '') {
            return $this->fail('Your session is missing institute information.', 422);
        }

        // A multi-institute admin's claim can be a CSV list; the first one is
        // the same convention AttainmentReportController::requireStaffInstitute()
        // documents and uses.
        $subInstituteId = (int) trim(explode(',', (string) $subInstituteClaim)[0]);

        $validated = $request->validate([
            'standard_id' => 'required|integer',
            'syear' => 'required|string|max:16',
            'section_id' => 'nullable|integer',
            'subject_id' => 'nullable|integer',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        if ($role === 'staff') {
            $userId = (int) ($auth['user_id'] ?? 0);
            $scoped = $this->isClassInTeacherScope(
                $userId,
                $subInstituteId,
                (int) $validated['standard_id'],
                isset($validated['section_id']) ? (int) $validated['section_id'] : null,
                (string) $validated['syear']
            );

            if (! $scoped) {
                return $this->fail('This class is not one of your assigned classes.', 403);
            }
        }

        return $validated + ['sub_institute_id' => $subInstituteId];
    }

    /**
     * Whether a class (standard, + optional section) in one academic year
     * falls in $teacherId's assigned classes — the class-level analogue of
     * PalApiAuth::isStudentInTeacherScope(), checking the same two sources
     * (homeroom class_teacher, subject timetable) without needing a named
     * student.
     */
    private function isClassInTeacherScope(int $teacherId, int $subInstituteId, int $standardId, ?int $sectionId, string $syear): bool
    {
        $classTeacherMatch = DB::table('class_teacher')
            ->where('teacher_id', $teacherId)
            ->where('sub_institute_id', $subInstituteId)
            ->where('standard_id', $standardId)
            ->where('syear', $syear)
            ->when($sectionId !== null, fn ($q) => $q->where('division_id', $sectionId))
            ->exists();

        if ($classTeacherMatch) {
            return true;
        }

        return DB::table('timetable')
            ->where('teacher_id', $teacherId)
            ->where('sub_institute_id', $subInstituteId)
            ->where('standard_id', $standardId)
            ->where('syear', $syear)
            ->when($sectionId !== null, fn ($q) => $q->where('division_id', $sectionId))
            ->exists();
    }

    private function streamDocx(string $binary, string $filename)
    {
        return response()->streamDownload(function () use ($binary) {
            echo $binary;
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'X-Content-Type-Options' => 'nosniff',
        ]);
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
