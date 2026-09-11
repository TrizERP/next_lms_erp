<?php

namespace App\Http\Controllers\api\PAL;

use App\Http\Controllers\Controller;
use App\Services\PAL\Examination\ExamBlueprintService;
use App\Services\PAL\Reporting\AttainmentReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Curriculum Coverage and Student Attainment — the two reports PAL loop
 * reporting has been missing.
 *
 * Staff-only, and scoped to the caller's own institute. A cohort report names
 * how a whole class is performing; it is not something a student may pull, and
 * the institute is never taken from the request.
 */
class AttainmentReportController extends Controller
{
    public function __construct(
        protected AttainmentReportService $report,
        protected ExamBlueprintService $blueprint
    ) {
    }

    /**
     * GET /api/pal/eso/reports/blueprint-feasibility
     *     ?board=CBSE&standardId=&subjectId=&blueprint=
     *
     * Whether the tagged item pool can fill a board's paper pattern, and where
     * it falls short. Reports counts only — it does not select or return
     * questions, because the PAL-vs-Examination item isolation rule is still
     * an open decision (tracker #6).
     */
    public function blueprintFeasibility(Request $request): JsonResponse
    {
        $staff = $this->requireStaffInstitute($request);
        if (! is_int($staff)) {
            return $staff;
        }

        $validated = $request->validate([
            'board' => 'required|string|max:64',
            'standardId' => 'required|integer',
            'subjectId' => 'nullable|integer',
            'blueprint' => 'nullable|string|max:64',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Success',
            'data' => $this->blueprint->feasibility(
                $staff,
                (string) $validated['board'],
                (int) $validated['standardId'],
                isset($validated['subjectId']) ? (int) $validated['subjectId'] : null,
                $validated['blueprint'] ?? 'standard_theory_80'
            ),
        ]);
    }

    /**
     * The caller's institute, or the JSON response explaining why they cannot
     * read a cohort- or estate-level report.
     *
     * The institute is always resolved from the session; a client-supplied one
     * would read another school's data.
     *
     * @return int|JsonResponse
     */
    private function requireStaffInstitute(Request $request)
    {
        $auth = $request->attributes->get('pal_auth');
        $role = is_array($auth) ? ($auth['role'] ?? '') : '';

        if ($role === 'student') {
            return response()->json([
                'success' => false,
                'message' => 'This report is staff/admin only.',
            ], 403);
        }

        $subInstituteId = is_array($auth) ? ($auth['sub_institute_id'] ?? null) : null;

        if ($subInstituteId === null || $subInstituteId === '') {
            return response()->json([
                'success' => false,
                'message' => 'Your session is missing institute information.',
            ], 422);
        }

        // The claim can be a comma-separated list for an admin over several
        // institutes. Taking the first is the convention already used by
        // CoherenceMapController and NewPalContentModelController — done
        // explicitly here rather than by an (int) cast that happens to do the
        // same thing, so the limitation is visible: a multi-institute admin
        // gets a report for their first institute only.
        return (int) trim(explode(',', (string) $subInstituteId)[0]);
    }

    /**
     * GET /api/pal/eso/reports/attainment?standardId=&syear=&subjectId=
     */
    public function attainment(Request $request): JsonResponse
    {
        $staff = $this->requireStaffInstitute($request);
        if (! is_int($staff)) {
            return $staff;
        }

        $validated = $request->validate([
            'standardId' => 'required|integer',
            'syear' => 'required|string|max:16',
            'subjectId' => 'nullable|integer',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Success',
            'data' => $this->report->forCohort(
                $staff,
                (int) $validated['standardId'],
                (string) $validated['syear'],
                isset($validated['subjectId']) ? (int) $validated['subjectId'] : null
            ),
        ]);
    }
}
