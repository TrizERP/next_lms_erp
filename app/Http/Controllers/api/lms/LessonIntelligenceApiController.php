<?php

namespace App\Http\Controllers\api\lms;

use App\Http\Controllers\Controller;
use App\Services\LessonIntelligence\LessonIntelligenceService;
use App\Services\LessonIntelligence\MacroPlanService;
use App\Services\LessonIntelligence\MesoPlanService;
use App\Services\LessonIntelligence\MicroPlannerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Throwable;

/**
 * Lesson Intelligence API - the four-phase lesson-plan generator.
 *
 *   Phase 0  capacity        how much teaching time the term actually has
 *   Phase 1  macro-plan      chapters spread across the term's weeks
 *   Phase 2  meso-plan       concepts placed into dated period slots
 *   Phase 3  micro-plan      the LLM-written content for a period
 *
 * Phases 0-2 are pure arithmetic and free to re-run. Phase 3 costs one DeepSeek
 * call per period and is therefore never triggered implicitly.
 */
class LessonIntelligenceApiController extends Controller
{
    public function __construct(
        private LessonIntelligenceService $intel,
        private MacroPlanService $macro,
        private MesoPlanService $meso,
        private MicroPlannerService $micro
    ) {
    }

    /* =====================================================================
     * Cascading dropdowns
     * ===================================================================== */

    /**
     * Every institute that has timetable data, for the first dropdown.
     *
     * GET /api/lesson-intelligence/dropdowns
     */
    public function dropdowns(): JsonResponse
    {
        try {
            $institutes = DB::table('school_setup')
                ->orderBy('SchoolName')
                ->get(['Id as id', 'SchoolName as name'])
                ->map(fn ($r) => ['id' => (int) $r->id, 'name' => $r->name])
                ->all();

            return response()->json(['status' => 'success', 'institutes' => $institutes]);
        } catch (Throwable $e) {
            return $this->fail('Failed to fetch institutes', $e);
        }
    }

    /**
     * Narrow the next dropdown to combinations that actually have a timetable.
     * Each level is filtered by the levels already chosen.
     *
     * GET /api/lesson-intelligence/dropdowns/filter
     *     ?sub_institute_id=&standard_id=&division_id=&subject_id=
     */
    public function dropdownFilter(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|integer',
            'standard_id'      => 'nullable|integer',
            'division_id'      => 'nullable|integer',
            'subject_id'       => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return $this->invalid($validator);
        }

        $filters    = $validator->validated();
        $instituteId = (int) $filters['sub_institute_id'];
        $standardId  = $filters['standard_id'] ?? null;
        $divisionId  = $filters['division_id'] ?? null;
        $subjectId   = $filters['subject_id'] ?? null;

        try {
            // The period join is what excludes timetable rows pointing at a
            // period that no longer exists - those cannot be scheduled.
            $base = fn () => DB::table('timetable as t')
                ->join('period as p', 't.period_id', '=', 'p.id')
                ->where('t.sub_institute_id', $instituteId);

            $standards = $base()
                ->join('standard as s', 't.standard_id', '=', 's.id')
                ->distinct()
                ->orderBy('s.name')
                ->get(['t.standard_id as id', 's.name'])
                ->map(fn ($r) => ['id' => (int) $r->id, 'name' => $r->name])
                ->all();

            $divisionQuery = $base()->join('division as d', 't.division_id', '=', 'd.id');
            if ($standardId) {
                $divisionQuery->where('t.standard_id', $standardId);
            }
            $divisions = $divisionQuery
                ->distinct()
                ->orderBy('d.name')
                ->get(['t.division_id as id', 'd.name'])
                ->map(fn ($r) => ['id' => (int) $r->id, 'name' => $r->name])
                ->all();

            $subjectQuery = $base()->join('subject as s', 't.subject_id', '=', 's.id');
            if ($standardId) {
                $subjectQuery->where('t.standard_id', $standardId);
            }
            if ($divisionId) {
                $subjectQuery->where('t.division_id', $divisionId);
            }
            $subjects = $subjectQuery
                ->distinct()
                ->orderBy('s.subject_name')
                ->get(['t.subject_id as id', 's.subject_name as name'])
                ->map(fn ($r) => ['id' => (int) $r->id, 'name' => $r->name])
                ->all();

            $yearQuery = $base();
            if ($standardId) {
                $yearQuery->where('t.standard_id', $standardId);
            }
            if ($divisionId) {
                $yearQuery->where('t.division_id', $divisionId);
            }
            if ($subjectId) {
                $yearQuery->where('t.subject_id', $subjectId);
            }
            $years = $yearQuery
                ->distinct()
                ->orderByDesc('t.syear')
                ->pluck('t.syear')
                ->map(fn ($y) => (int) $y)
                ->all();

            return response()->json([
                'status'    => 'success',
                'standards' => $standards,
                'divisions' => $divisions,
                'subjects'  => $subjects,
                'years'     => $years,
            ]);
        } catch (Throwable $e) {
            return $this->fail('Failed to fetch filtered options', $e);
        }
    }

    /* =====================================================================
     * Phase 0 - Capacity
     * ===================================================================== */

    /**
     * How much teaching time this subject actually has, per term, and how much
     * the syllabus needs. Read-only - writes nothing.
     *
     * GET|POST /api/lesson-intelligence/capacity
     */
    public function capacity(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->selectionRules());
        if ($validator->fails()) {
            return $this->invalid($validator);
        }

        $f = $validator->validated();

        try {
            return response()->json([
                'status' => 'success',
                'data'   => $this->intel->getCapacityAnalysis(
                    (int) $f['sub_institute_id'],
                    (int) $f['standard_id'],
                    (int) $f['subject_id'],
                    (int) $f['syear'],
                    isset($f['division_id']) ? (int) $f['division_id'] : null
                ),
            ]);
        } catch (Throwable $e) {
            return $this->fail('Capacity analysis failed', $e);
        }
    }

    /**
     * Holidays and exam dates, for shading the calendar.
     *
     * GET|POST /api/lesson-intelligence/calendar-events
     */
    public function calendarEvents(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|integer',
            'standard_id'      => 'required|integer',
            'subject_id'       => 'required|integer',
            'syear'            => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->invalid($validator);
        }

        $f = $validator->validated();

        try {
            $holidays = array_map(fn ($h) => [
                'date'  => $h['date'] ? $h['date']->toDateString() : null,
                'title' => $h['title'],
                'type'  => $h['type'],
            ], $this->intel->getHolidays((int) $f['sub_institute_id'], (int) $f['syear']));

            $exams = array_map(fn ($e) => [
                'date'  => $e['date'] ? $e['date']->toDateString() : null,
                'title' => $e['title'],
                'marks' => $e['marks'],
            ], $this->intel->getExamDates(
                (int) $f['sub_institute_id'],
                (int) $f['standard_id'],
                (int) $f['subject_id'],
                (int) $f['syear']
            ));

            return response()->json(['status' => 'success', 'holidays' => $holidays, 'exams' => $exams]);
        } catch (Throwable $e) {
            return $this->fail('Failed to fetch calendar events', $e);
        }
    }

    /* =====================================================================
     * Phase 1 - Macro plan
     * ===================================================================== */

    /**
     * Build the term plans. Pass force=true to overwrite plans that already
     * exist; without it, existing plans are reported back untouched.
     *
     * POST /api/lesson-intelligence/macro-plan
     */
    public function storeMacroPlan(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->selectionRules() + ['force' => 'nullable|boolean']);
        if ($validator->fails()) {
            return $this->invalid($validator);
        }

        $f = $validator->validated();

        try {
            // Build from the year that has a timetable, record under the year
            // the calendar shows - the same pair capacity reports.
            $schedulingYear = $this->intel->resolveSchedulingYear(
                (int) $f['sub_institute_id'],
                (int) $f['standard_id'],
                (int) $f['subject_id'],
                (int) $f['syear'],
                isset($f['division_id']) ? (int) $f['division_id'] : null
            );

            return response()->json($this->macro->generate(
                (int) $f['sub_institute_id'],
                (int) $f['standard_id'],
                (int) $f['subject_id'],
                $schedulingYear,
                isset($f['division_id']) ? (int) $f['division_id'] : null,
                (bool) ($f['force'] ?? false),
                $this->recordedYear($f)
            ));
        } catch (RuntimeException $e) {
            // Missing terms / timetable / chapters is a setup problem the user
            // can fix, not a server fault - report it as such.
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return $this->fail('Macro plan generation failed', $e);
        }
    }

    /**
     * The stored term plans for a selection, newest capacity numbers included.
     *
     * GET|POST /api/lesson-intelligence/macro-plan
     */
    public function showMacroPlan(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->selectionRules());
        if ($validator->fails()) {
            return $this->invalid($validator);
        }

        $f          = $validator->validated();
        $divisionId = isset($f['division_id']) ? (int) $f['division_id'] : null;

        try {
            $rows = DB::table(LessonIntelligenceService::TBL_LESSON_PLANS)
                ->where('sub_institute_id', (int) $f['sub_institute_id'])
                ->where('syear', $this->recordedYear($f))
                ->where('standard_id', (int) $f['standard_id'])
                ->where('subject_id', (int) $f['subject_id'])
                ->where(function ($q) use ($divisionId) {
                    $divisionId === null ? $q->whereNull('division_id') : $q->where('division_id', $divisionId);
                })
                ->orderBy('term_id')
                ->get();

            if ($rows->isEmpty()) {
                return response()->json([
                    'status'  => false,
                    'message' => 'No lesson plan has been generated for this selection yet.',
                    'plans'   => [],
                ], 404);
            }

            // Schedule progress per plan, as two counters rather than making the
            // caller download every period row (each carries a multi-KB plan_json)
            // just to count them.
            $progress = DB::table(LessonIntelligenceService::TBL_PLAN_PERIODS)
                ->whereIn('lms_intelligence_lesson_plans_id', $rows->pluck('id'))
                ->groupBy('lms_intelligence_lesson_plans_id')
                ->get([
                    'lms_intelligence_lesson_plans_id as plan_id',
                    DB::raw('COUNT(*) as periods_total'),
                    DB::raw('SUM(CASE WHEN plan_json IS NULL THEN 0 ELSE 1 END) as periods_with_plan'),
                ])
                ->keyBy('plan_id');

            $plans = $rows->map(function ($row) use ($progress) {
                $plan = (array) $row;
                $plan['macro_plan_json']   = $this->intel->decodeJsonPublic($plan['macro_plan_json'] ?? null);
                $plan['periods_total']     = (int) ($progress[$row->id]->periods_total ?? 0);
                $plan['periods_with_plan'] = (int) ($progress[$row->id]->periods_with_plan ?? 0);

                return $plan;
            })->all();

            return response()->json(['status' => 'success', 'plans' => $plans]);
        } catch (Throwable $e) {
            return $this->fail('Failed to fetch the lesson plan', $e);
        }
    }

    /* =====================================================================
     * Phase 2 - Meso plan
     * ===================================================================== */

    /**
     * Teachers owning slots in this plan plus its chapters, so the UI can offer
     * a manual chapter-to-teacher split before generating.
     *
     * GET /api/lesson-intelligence/meso-plan/{planId}/teachers
     */
    public function mesoPlanTeachers(Request $request, int $planId): JsonResponse
    {
        if (!$this->ownsPlan($request, $planId)) {
            return $this->notOwned();
        }

        try {
            return response()->json($this->meso->getTeacherAssignmentOptions($planId));
        } catch (RuntimeException $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            return $this->fail('Failed to load teachers for this plan', $e);
        }
    }

    /**
     * Create the dated period rows for a plan.
     *
     * Destructive: replaces any periods this plan already has. Optional body
     * `teacher_assignments` maps teacherId => [chapterId, ...]; anything not
     * mapped is balanced automatically.
     *
     * POST /api/lesson-intelligence/meso-plan/{planId}
     */
    public function storeMesoPlan(Request $request, int $planId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'teacher_assignments'   => 'nullable|array',
            'teacher_assignments.*' => 'array',
        ]);

        if ($validator->fails()) {
            return $this->invalid($validator);
        }

        if (!$this->ownsPlan($request, $planId)) {
            return $this->notOwned();
        }

        try {
            return response()->json($this->meso->generate($planId, $request->input('teacher_assignments')));
        } catch (RuntimeException $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return $this->fail('Meso plan generation failed', $e);
        }
    }

    /**
     * The generated schedule: every period with its teacher, timings and the
     * concepts it covers.
     *
     * GET /api/lesson-intelligence/meso-plan/{planId}/periods
     */
    public function mesoPlanPeriods(Request $request, int $planId): JsonResponse
    {
        $subInstituteId = (int) $request->input('sub_institute_id');
        if (!$this->ownsPlan($request, $planId)) {
            return $this->notOwned();
        }

        try {
            $rows = DB::table(LessonIntelligenceService::TBL_PLAN_PERIODS . ' as p')
                ->leftJoin('tbluser as u', 'p.teacher_id', '=', 'u.id')
                ->leftJoin('period as pm', 'p.period_id', '=', 'pm.id')
                ->where('p.lms_intelligence_lesson_plans_id', $planId)
                // Belt and braces: the plan is already known to be this
                // institute's, but filtering the row itself means a period that
                // somehow carries another owner stays invisible rather than
                // leaking through its parent.
                ->where('p.sub_institute_id', $subInstituteId)
                ->orderBy('p.scheduled_date')
                ->orderBy('p.period_slot')
                ->get([
                    'p.*',
                    DB::raw("CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')) AS teacher_name"),
                    DB::raw("TIME_FORMAT(pm.start_time, '%h:%i %p') AS start_time"),
                    DB::raw("TIME_FORMAT(pm.end_time, '%h:%i %p') AS end_time"),
                ]);

            if ($rows->isEmpty()) {
                return response()->json(['status' => 'success', 'periods' => []]);
            }

            $periods = [];
            foreach ($rows as $row) {
                $period = (array) $row;
                $period['plan_json']           = $this->intel->decodeJsonPublic($period['plan_json'] ?? null);
                $period['learning_objectives'] = $this->intel->decodeJsonPublic($period['learning_objectives'] ?? null);
                $period['teacher_name']        = trim((string) $period['teacher_name']) ?: null;
                $period['concepts']            = [];
                $periods[(int) $row->id]       = $period;
            }

            // One extra query for every period's concepts, grouped in PHP.
            $conceptRows = DB::table(LessonIntelligenceService::TBL_PLAN_CONCEPTS)
                ->whereIn('lms_lesson_plan_periods_id', array_keys($periods))
                ->where('sub_institute_id', $subInstituteId)
                ->orderBy('lms_lesson_plan_periods_id')
                ->orderBy('id')
                ->get();

            foreach ($conceptRows as $c) {
                $periods[(int) $c->lms_lesson_plan_periods_id]['concepts'][] = (array) $c;
            }

            return response()->json(['status' => 'success', 'periods' => array_values($periods)]);
        } catch (Throwable $e) {
            return $this->fail('Failed to fetch periods for this plan', $e);
        }
    }

    /* =====================================================================
     * Phase 3 - Micro plan (LLM)
     * ===================================================================== */

    /**
     * Write the lesson content for one period. Costs one DeepSeek call.
     * Pass force=true to overwrite a plan that already exists.
     *
     * POST /api/lesson-intelligence/micro-plan/period/{periodId}
     */
    public function storeMicroPlan(Request $request, int $periodId): JsonResponse
    {
        if (!$this->ownsPeriod($request, $periodId)) {
            return $this->notOwned();
        }

        try {
            return response()->json($this->micro->generateForPeriod($periodId, $request->boolean('force')));
        } catch (RuntimeException $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return $this->fail('Micro plan generation failed', $e);
        }
    }

    /**
     * Write lesson content for up to `limit` still-unplanned periods (max 50).
     * Runs sequentially; one failing period does not stop the rest.
     *
     * POST /api/lesson-intelligence/micro-plan/plan/{planId}/batch
     */
    public function storeMicroPlanBatch(Request $request, int $planId): JsonResponse
    {
        $limit = (int) $request->input('limit', 10);

        if (!$this->ownsPlan($request, $planId)) {
            return $this->notOwned();
        }

        try {
            return response()->json($this->micro->generateBatch($planId, $limit));
        } catch (RuntimeException $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return $this->fail('Batch micro plan generation failed', $e);
        }
    }

    /* =====================================================================
     * Helpers
     * ===================================================================== */

    /**
     * The year a plan is recorded under, which is not always the year its source
     * data lives in.
     *
     * Institute 1 keeps its terms, timetable and holidays under earlier years,
     * but every lesson-plan reader pins it to 2026, so plans generated from that
     * earlier data must still be stamped 2026 or they exist without ever being
     * displayable. Reads and writes both go through here so they cannot drift.
     */
    private function recordedYear(array $filters): int
    {
        return (int) $filters['sub_institute_id'] === 1 ? 2026 : (int) $filters['syear'];
    }

    /** The standard/subject/year selection every phase-0/1 endpoint takes. */
    private function selectionRules(): array
    {
        return [
            'sub_institute_id' => 'required|integer',
            'standard_id'      => 'required|integer',
            'subject_id'       => 'required|integer',
            'syear'            => 'required|integer',
            'division_id'      => 'nullable|integer',
        ];
    }

    /**
     * Tenancy guard for the id-addressed endpoints.
     *
     * A bare plan or period id says nothing about who owns it, so every one of
     * these routes has to prove the caller's institute owns the row before it
     * reads or writes. Missing sub_institute_id fails closed.
     */
    private function ownsPlan(Request $request, int $planId): bool
    {
        $subInstituteId = (int) $request->input('sub_institute_id');
        if ($subInstituteId <= 0) {
            return false;
        }

        return DB::table(LessonIntelligenceService::TBL_LESSON_PLANS)
            ->where('id', $planId)
            ->where('sub_institute_id', $subInstituteId)
            ->exists();
    }

    private function ownsPeriod(Request $request, int $periodId): bool
    {
        $subInstituteId = (int) $request->input('sub_institute_id');
        if ($subInstituteId <= 0) {
            return false;
        }

        return DB::table(LessonIntelligenceService::TBL_PLAN_PERIODS)
            ->where('id', $periodId)
            ->where('sub_institute_id', $subInstituteId)
            ->exists();
    }

    /** Deliberately 404, not 403 - another institute's row simply does not exist here. */
    private function notOwned(): JsonResponse
    {
        return response()->json([
            'status'  => false,
            'message' => 'Not found for this institute.',
        ], 404);
    }

    private function invalid($validator): JsonResponse
    {
        return response()->json([
            'status'  => false,
            'message' => 'Validation failed',
            'errors'  => $validator->errors(),
        ], 422);
    }

    private function fail(string $message, Throwable $e): JsonResponse
    {
        Log::error("LessonIntelligence: {$message} - " . $e->getMessage(), ['exception' => $e]);

        return response()->json(['status' => false, 'message' => $message . ': ' . $e->getMessage()], 500);
    }
}
