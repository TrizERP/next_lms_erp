<?php

namespace App\Http\Controllers\api\PAL;

use App\Domain\AI\Support\ModelClient;
use App\Http\Controllers\Controller;
use App\Services\PAL\Examination\ExamBlueprintService;
use App\Services\PAL\Reporting\AttainmentReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

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
        protected ExamBlueprintService $blueprint,
        protected ModelClient $modelClient
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

    /**
     * GET /api/pal/eso/reports/attainment/narrative?standardId=&syear=&subjectId=
     *
     * One short paragraph for a teacher, grounded only in attainment()'s own
     * numbers — reuses forCohort() rather than re-aggregating, then narrates
     * it the same way CoherenceMapController::explain() narrates graph
     * context: retrieval (here, the SQL cohort aggregate) and generation are
     * separate steps, and a missing model or empty scope is a clean failure,
     * never a fabricated paragraph.
     */
    public function attainmentNarrative(Request $request): JsonResponse
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

        $data = $this->report->forCohort(
            $staff,
            (int) $validated['standardId'],
            (string) $validated['syear'],
            isset($validated['subjectId']) ? (int) $validated['subjectId'] : null
        );

        if ($data['coverage']['concepts_total'] === 0) {
            return response()->json([
                'success' => false,
                'message' => 'No curriculum is mapped for this class and subject yet, so there is nothing to narrate.',
            ], 404);
        }

        $client = $this->modelClient->forInstitute($staff);

        if (! $client->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'No AI model is configured for this institute.',
            ], 503);
        }

        try {
            $narrative = $client->chat(
                [
                    ['role' => 'system', 'content' => $this->narrativeSystemPrompt()],
                    ['role' => 'user', 'content' => $this->narrativeUserPrompt($data)],
                ],
                model: null,
                maxTokens: 1024,
                temperature: 0.3,
            );
        } catch (Throwable $exception) {
            return response()->json([
                'success' => false,
                'message' => 'The narrative could not be generated.',
            ], 502);
        }

        return response()->json([
            'success' => true,
            'message' => 'Success',
            'data' => $data + ['narrative' => trim((string) $narrative)],
        ]);
    }

    private function narrativeSystemPrompt(): string
    {
        return 'You are summarising a class attainment report for a teacher. Use only the facts given '
            . 'in the message — never invent a concept name, percentage, or count that is not present in '
            . 'the input. Call out the single biggest gap (lowest coverage, lowest attainment, or the '
            . 'largest taught-but-unevidenced list) rather than listing everything. Keep the answer to '
            . '3-5 sentences of plain language, addressed to the teacher.';
    }

    /**
     * @param  array{scope:array,coverage:array,attainment:array,concepts:array}  $data
     */
    private function narrativeUserPrompt(array $data): string
    {
        $scope = $data['scope'];
        $coverage = $data['coverage'];
        $attainment = $data['attainment'];

        $lines = [
            sprintf(
                'Class: standard %d, subject %s, year %s, %d student(s) enrolled.',
                $scope['standard_id'],
                $scope['subject_id'] ?? 'all',
                $scope['syear'],
                $scope['student_count']
            ),
            sprintf(
                'Curriculum coverage: %d of %d concepts taught (%.1f%%) across %d chapter(s).',
                $coverage['concepts_taught'],
                $coverage['concepts_total'],
                $coverage['coverage_pct'],
                $coverage['chapters_total']
            ),
        ];

        if ($attainment['mean_attainment_pct'] === null) {
            $lines[] = 'Attainment: no student has attempted any taught concept yet.';
        } else {
            $lines[] = sprintf(
                'Attainment: mean %.1f%% across %d measured concept(s); %d of those have at least one attempt.',
                $attainment['mean_attainment_pct'],
                $attainment['concepts_measured'],
                $attainment['concepts_with_any_evidence']
            );
        }

        $unevidenced = $attainment['taught_but_unevidenced'];
        if ($unevidenced !== []) {
            $names = array_slice(array_column($unevidenced, 'name'), 0, 10);
            $lines[] = sprintf(
                'Taught but never attempted by any student (%d total): %s%s',
                count($unevidenced),
                implode(', ', $names),
                count($unevidenced) > count($names) ? ', ...' : ''
            );
        }

        return implode("\n", $lines);
    }
}
