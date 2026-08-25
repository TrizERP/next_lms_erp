<?php

namespace App\Http\Controllers\api\TalentManagement\Performance;

use App\Http\Controllers\Controller;
use App\Http\Controllers\api\Concerns\RequiresTalentAdmin;
use App\Http\Controllers\api\TalentManagement\Performance\Concerns\ResolvesPerformanceContext;
use App\Models\AuditLog;
use App\Models\TalentManagement\PerformanceCalibrationSession;
use App\Models\TalentManagement\PerformanceReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ported from G2G's `App\Http\Controllers\Api\Performance\PerformanceCalibrationController`.
 *
 * The Calibration tab: sessions, the per-session rating grid (proposed vs
 * calibrated) and the distribution guardrail.
 *
 * The calibrated rating itself is written onto s_performance_reviews so the main
 * table, the stepper and the KPIs all read one value.
 */
class PerformanceCalibrationController extends Controller
{
    use ResolvesPerformanceContext;
    use RequiresTalentAdmin;

    private const SORTABLE = ['name', 'scheduled_at', 'status', 'participant_count', 'created_at'];

    private const AUDIT_LABELS = [
        'name'                => 'Session Name',
        'department_id'       => 'Department',
        'facilitator_id'      => 'Facilitator',
        'scheduled_at'        => 'Scheduled At',
        'status'              => 'Status',
        'distribution_target' => 'Distribution Target',
        'notes'               => 'Notes',
    ];

    /** GET /api/performance/calibration-sessions */
    public function index(Request $request)
    {
        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $filters = $this->performanceFilters($request);
        ['page' => $page, 'per_page' => $perPage] = $this->performancePaging($request);
        [$sortBy, $sortDir] = $this->performanceSort($request, self::SORTABLE, 'scheduled_at', 'asc');

        $query = $this->baseQuery($tenant, $filters);
        $total = (clone $query)->count('s.id');

        $rows = $query
            ->orderBy('s.' . $sortBy, $sortDir)
            ->orderByDesc('s.id')
            ->forPage($page, $perPage)
            ->get();

        // Live participant / average-rating figures per session, in one query.
        $sessionIds = $rows->pluck('id')->all();

        $stats = empty($sessionIds) ? collect() : DB::table('s_performance_reviews')
            ->where('sub_institute_id', $tenant)
            ->whereNull('deleted_at')
            ->whereIn('calibration_session_id', $sessionIds)
            ->select(
                'calibration_session_id',
                DB::raw('COUNT(*) as participants'),
                DB::raw('AVG(overall_rating) as avg_rating'),
                DB::raw('SUM(calibrated_rating IS NOT NULL) as calibrated')
            )
            ->groupBy('calibration_session_id')
            ->get()
            ->keyBy('calibration_session_id');

        return $this->performanceResponse(
            $rows->map(fn ($row) => $this->present($row, $stats[$row->id] ?? null))->all(),
            'Success',
            200,
            [
                'pagination' => [
                    'page'      => $page,
                    'per_page'  => $perPage,
                    'total'     => $total,
                    'last_page' => (int) max(1, ceil($total / $perPage)),
                ],
                'summary' => [
                    'total'       => $total,
                    'scheduled'   => (clone $query)->where('s.status', 'scheduled')->count('s.id'),
                    'in_progress' => (clone $query)->where('s.status', 'in_progress')->count('s.id'),
                    'completed'   => (clone $query)->where('s.status', 'completed')->count('s.id'),
                    'locked'      => (clone $query)->where('s.status', 'locked')->count('s.id'),
                ],
            ]
        );
    }

    /** POST /api/performance/calibration-sessions */
    public function store(Request $request)
    {
        if ($response = $this->assertIsAdmin()) { return $response; }

        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];

        $validated = $request->validate([
            'name'                => 'required|string|max:191',
            'cycle_id'            => 'required|integer',
            'department_id'       => 'nullable|integer',
            'facilitator_id'      => 'nullable|integer',
            'scheduled_at'        => 'nullable|date',
            'status'              => 'nullable|in:scheduled,in_progress,completed,locked',
            'distribution_target' => 'nullable|array',
            'notes'               => 'nullable|string',
            // When true, every review in the cycle (optionally the department) is
            // attached to the new session so the grid opens populated.
            'attach_reviews'      => 'nullable|boolean',
        ]);

        $cycleExists = DB::table('s_performance_cycles')
            ->where('sub_institute_id', $tenant)
            ->whereNull('deleted_at')
            ->where('id', $validated['cycle_id'])
            ->exists();

        if (!$cycleExists) {
            return $this->performanceError('Cycle not found', 422);
        }

        $attach = (bool) ($validated['attach_reviews'] ?? true);
        unset($validated['attach_reviews']);

        $session = PerformanceCalibrationSession::create(array_merge($validated, [
            'sub_institute_id' => $tenant,
            'status'           => $validated['status'] ?? 'scheduled',
            'created_by'       => $context['user_id'],
            'updated_by'       => $context['user_id'],
        ]));

        $attached = 0;

        if ($attach) {
            $attached = DB::table('s_performance_reviews')
                ->where('sub_institute_id', $tenant)
                ->whereNull('deleted_at')
                ->where('cycle_id', $session->cycle_id)
                ->when($session->department_id, fn ($q) => $q->where('department_id', $session->department_id))
                ->whereNull('calibration_session_id')
                ->update([
                    'calibration_session_id' => $session->id,
                    'updated_at'             => now(),
                ]);
        }

        $session->participant_count = $attached;
        $session->save();

        $this->logPerformanceActivity(
            $tenant,
            $context['user_id'],
            'created_calibration_session',
            'created calibration session ' . $session->name . ' with ' . $attached . ' participant(s)',
            'calibration',
            $session->id,
            $session->name,
            null,
            null,
            (int) $session->cycle_id
        );

        AuditLog::record([
            'module'      => 'talent_management',
            'action'      => 'calibration_session_created',
            'entity_type' => 'performance_calibration_session',
            'entity_id'   => $session->id,
            'new_values'  => array_merge($validated, ['attached_reviews' => $attached]),
        ]);

        return $this->performanceResponse($this->presentModel($session), 'Calibration session created', 201);
    }

    /** PUT /api/performance/calibration-sessions/{id} */
    public function update(Request $request, $id)
    {
        if ($response = $this->assertIsAdmin()) { return $response; }

        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $session = PerformanceCalibrationSession::where('sub_institute_id', $tenant)->find($id);

        if (!$session) {
            return $this->performanceError('Calibration session not found', 404);
        }

        if ($session->status === 'locked' && !$request->boolean('force')) {
            return $this->performanceError('A locked session cannot be edited', 422);
        }

        $validated = $request->validate([
            'name'                => 'sometimes|required|string|max:191',
            'department_id'       => 'nullable|integer',
            'facilitator_id'      => 'nullable|integer',
            'scheduled_at'        => 'nullable|date',
            'status'              => 'nullable|in:scheduled,in_progress,completed,locked',
            'distribution_target' => 'nullable|array',
            'notes'               => 'nullable|string',
        ]);

        $changes = $this->diffPerformanceChanges($session->getOriginal(), $validated, self::AUDIT_LABELS);

        $session->fill($validated);
        $session->updated_by = $context['user_id'];
        $session->save();

        AuditLog::record([
            'module'      => 'talent_management',
            'action'      => 'calibration_session_updated',
            'entity_type' => 'performance_calibration_session',
            'entity_id'   => $session->id,
            'new_values'  => $validated,
        ]);

        if (!empty($changes)) {
            $this->logPerformanceActivity(
                $tenant,
                $context['user_id'],
                'updated_calibration_session',
                'updated calibration session ' . $session->name,
                'calibration',
                $session->id,
                $session->name,
                $changes,
                null,
                (int) $session->cycle_id
            );
        }

        return $this->performanceResponse($this->presentModel($session), 'Calibration session updated');
    }

    /**
     * GET /api/performance/calibration-sessions/{id}/grid
     *
     * The calibration grid: every attached review with its proposed rating
     * (manager/self) next to its calibrated rating, plus the live distribution
     * against the session's target.
     */
    public function grid(Request $request, $id)
    {
        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $session = PerformanceCalibrationSession::where('sub_institute_id', $tenant)->find($id);

        if (!$session) {
            return $this->performanceError('Calibration session not found', 404);
        }

        $rows = DB::table('s_performance_reviews as r')
            ->leftJoin('tbluser as emp', 'emp.id', '=', 'r.user_id')
            ->leftJoin('tbluser as mgr', 'mgr.id', '=', 'r.manager_id')
            ->leftJoin('hrms_departments as dept', 'dept.id', '=', 'r.department_id')
            ->where('r.sub_institute_id', $tenant)
            ->whereNull('r.deleted_at')
            ->where('r.calibration_session_id', $session->id)
            ->orderByDesc('r.overall_rating')
            ->orderBy('emp.first_name')
            ->get([
                'r.id', 'r.user_id', 'r.jobrole', 'r.stage', 'r.status',
                'r.self_rating', 'r.manager_rating', 'r.calibrated_rating',
                'r.overall_rating', 'r.overall_rating_label', 'r.potential_rating',
                'emp.first_name', 'emp.last_name', 'emp.user_name', 'emp.employee_no',
                'mgr.first_name as mgr_first_name', 'mgr.last_name as mgr_last_name',
                'dept.department as department_name',
            ]);

        $participants = $rows->map(function ($row) {
            $name = trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? ''));
            $name = $name !== '' ? $name : ($row->user_name ?? 'Unknown');

            $managerName = trim(($row->mgr_first_name ?? '') . ' ' . ($row->mgr_last_name ?? ''));

            $proposed = $row->manager_rating ?? $row->self_rating;

            return [
                'review_id'            => (int) $row->id,
                'user_id'              => (int) $row->user_id,
                'employee_name'        => $name,
                'employee_initials'    => $this->initialsOf($name),
                'employee_no'          => $row->employee_no,
                'department'           => $row->department_name,
                'jobrole'              => $row->jobrole,
                'manager'              => $managerName !== '' ? $managerName : null,
                'stage'                => $row->stage,
                'stage_label'          => $this->stageLabel($row->stage),
                'self_rating'          => $row->self_rating !== null ? (float) $row->self_rating : null,
                'manager_rating'       => $row->manager_rating !== null ? (float) $row->manager_rating : null,
                'proposed_rating'      => $proposed !== null ? (float) $proposed : null,
                'proposed_label'       => $this->ratingLabel($proposed !== null ? (float) $proposed : null),
                'calibrated_rating'    => $row->calibrated_rating !== null ? (float) $row->calibrated_rating : null,
                'calibrated_label'     => $this->ratingLabel($row->calibrated_rating !== null ? (float) $row->calibrated_rating : null),
                'overall_rating'       => $row->overall_rating !== null ? (float) $row->overall_rating : null,
                'overall_rating_label' => $row->overall_rating_label,
                'potential_rating'     => $row->potential_rating !== null ? (float) $row->potential_rating : null,
                // The delta the grid highlights: how far calibration moved the rating.
                'delta'                => ($row->calibrated_rating !== null && $proposed !== null)
                    ? round((float) $row->calibrated_rating - (float) $proposed, 2)
                    : null,
            ];
        })->all();

        // Live distribution vs the session's target, per rating band.
        $target = $session->distribution_target ?? [];
        $rated = collect($participants)->filter(fn ($p) => $p['overall_rating'] !== null);
        $ratedCount = $rated->count();

        $distribution = [];
        foreach ([5, 4, 3, 2, 1] as $band) {
            $count = $rated->filter(
                fn ($p) => round($p['overall_rating']) == $band
            )->count();

            $actualPct = $ratedCount > 0 ? round($count / $ratedCount * 100, 1) : 0.0;
            $targetPct = isset($target[(string) $band]) ? (float) $target[(string) $band] : null;

            $distribution[] = [
                'band'       => $band,
                'label'      => $this->ratingLabel((float) $band),
                'count'      => $count,
                'actual_pct' => $actualPct,
                'target_pct' => $targetPct,
                'variance'   => $targetPct !== null ? round($actualPct - $targetPct, 1) : null,
            ];
        }

        return $this->performanceResponse([
            'session'      => $this->presentModel($session),
            'participants' => $participants,
            'distribution' => $distribution,
            'rated_count'  => $ratedCount,
            'total_count'  => count($participants),
        ]);
    }

    /**
     * PUT /api/performance/calibration-sessions/{id}/calibrate
     *
     * Writes calibrated ratings onto the reviews in this session. Accepts one
     * {review_id, rating} pair or a `ratings` array for the whole grid.
     */
    public function calibrate(Request $request, $id)
    {
        if ($response = $this->assertIsAdmin()) { return $response; }

        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $session = PerformanceCalibrationSession::where('sub_institute_id', $tenant)->find($id);

        if (!$session) {
            return $this->performanceError('Calibration session not found', 404);
        }

        if ($session->status === 'locked') {
            return $this->performanceError('This session is locked; ratings can no longer be changed', 422);
        }

        $validated = $request->validate([
            'review_id'            => 'nullable|integer',
            'rating'               => 'nullable|numeric|min:0|max:10',
            'potential_rating'     => 'nullable|numeric|min:0|max:10',
            'ratings'              => 'nullable|array',
            'ratings.*.review_id'  => 'required_with:ratings|integer',
            'ratings.*.rating'     => 'required_with:ratings|numeric|min:0|max:10',
        ]);

        $pairs = $validated['ratings'] ?? null;

        if (!$pairs) {
            if (empty($validated['review_id']) || !isset($validated['rating'])) {
                return $this->performanceError('Provide review_id + rating, or a ratings array', 422);
            }

            $pairs = [[
                'review_id'        => $validated['review_id'],
                'rating'           => $validated['rating'],
                'potential_rating' => $validated['potential_rating'] ?? null,
            ]];
        }

        $reviewIds = array_map(fn ($pair) => (int) $pair['review_id'], $pairs);

        $reviews = PerformanceReview::where('sub_institute_id', $tenant)
            ->where('calibration_session_id', $session->id)
            ->whereIn('id', $reviewIds)
            ->get()
            ->keyBy('id');

        $updated = 0;
        $skipped = [];
        $now = now();

        foreach ($pairs as $pair) {
            $review = $reviews[(int) $pair['review_id']] ?? null;

            if (!$review) {
                $skipped[] = (int) $pair['review_id'];
                continue;
            }

            $rating = (float) $pair['rating'];
            $previous = $review->calibrated_rating;

            $review->calibrated_rating = $rating;
            $review->calibrated_by = $context['user_id'];
            $review->calibrated_at = $now;

            // The calibrated value becomes the rating the whole screen displays.
            $review->overall_rating = $rating;
            $review->overall_rating_label = $this->ratingLabel($rating);

            if (isset($pair['potential_rating']) && $pair['potential_rating'] !== null) {
                $review->potential_rating = (float) $pair['potential_rating'];
                $review->potential_rating_label = $this->ratingLabel((float) $pair['potential_rating']);
            }

            $review->updated_by = $context['user_id'];
            $review->save();
            $updated++;

            AuditLog::record([
                'module'      => 'talent_management',
                'action'      => 'rating_calibrated',
                'entity_type' => 'performance_review',
                'entity_id'   => $review->id,
                'new_values'  => [
                    'calibration_session_id' => $session->id,
                    'calibrated_rating'      => $rating,
                    'previous_rating'        => $previous,
                    'potential_rating'       => $pair['potential_rating'] ?? null,
                ],
            ]);

            $employeeName = $this->resolveActorName((int) $review->user_id) ?? 'an employee';

            $this->logPerformanceActivity(
                $tenant,
                $context['user_id'],
                'calibrated_rating',
                'calibrated the rating for ' . $employeeName . ' in ' . $session->name,
                'calibration',
                $session->id,
                $employeeName,
                [[
                    'field' => 'calibrated_rating',
                    'label' => 'Calibrated Rating',
                    'old'   => $previous,
                    'new'   => $rating,
                ]],
                (int) $review->id,
                (int) $review->cycle_id
            );
        }

        if ($session->status === 'scheduled' && $updated > 0) {
            $session->status = 'in_progress';
            $session->updated_by = $context['user_id'];
            $session->save();
        }

        return $this->performanceResponse([
            'updated' => $updated,
            'skipped' => $skipped,
        ], $updated . ' rating(s) calibrated');
    }

    /**
     * POST /api/performance/calibration-sessions/{id}/lock
     *
     * Freezes the session and, unless told otherwise, advances every calibrated
     * review to Final Review - the step the tab's "Lock Session" action performs.
     */
    public function lock(Request $request, $id)
    {
        if ($response = $this->assertIsAdmin()) { return $response; }

        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $session = PerformanceCalibrationSession::where('sub_institute_id', $tenant)->find($id);

        if (!$session) {
            return $this->performanceError('Calibration session not found', 404);
        }

        if ($session->status === 'locked') {
            return $this->performanceError('This session is already locked', 422);
        }

        $uncalibrated = DB::table('s_performance_reviews')
            ->where('sub_institute_id', $tenant)
            ->whereNull('deleted_at')
            ->where('calibration_session_id', $session->id)
            ->whereNull('calibrated_rating')
            ->count();

        if ($uncalibrated > 0 && !$request->boolean('force')) {
            return $this->performanceError(
                $uncalibrated . ' participant(s) have no calibrated rating. Re-send with force=1 to lock anyway.',
                422,
                ['uncalibrated' => $uncalibrated]
            );
        }

        $advanced = 0;

        if ($request->boolean('advance', true)) {
            $advanced = DB::table('s_performance_reviews')
                ->where('sub_institute_id', $tenant)
                ->whereNull('deleted_at')
                ->where('calibration_session_id', $session->id)
                ->whereNotNull('calibrated_rating')
                ->where('stage', 'calibration')
                ->update([
                    'stage'      => 'final_review',
                    'status'     => 'in_progress',
                    'updated_by' => $context['user_id'],
                    'updated_at' => now(),
                ]);
        }

        $session->status = 'locked';
        $session->locked_at = now();
        $session->updated_by = $context['user_id'];
        $session->save();

        AuditLog::record([
            'module'      => 'talent_management',
            'action'      => 'calibration_session_locked',
            'entity_type' => 'performance_calibration_session',
            'entity_id'   => $session->id,
            'new_values'  => ['status' => 'locked', 'advanced_reviews' => $advanced],
        ]);

        $this->logPerformanceActivity(
            $tenant,
            $context['user_id'],
            'locked_calibration_session',
            'locked calibration session ' . $session->name
                . ($advanced ? ' and advanced ' . $advanced . ' review(s) to Final Review' : ''),
            'calibration',
            $session->id,
            $session->name,
            [['field' => 'status', 'label' => 'Status', 'old' => 'in_progress', 'new' => 'locked']],
            null,
            (int) $session->cycle_id
        );

        return $this->performanceResponse([
            'session'           => $this->presentModel($session),
            'advanced_reviews'  => $advanced,
        ], 'Calibration session locked');
    }

    /** DELETE /api/performance/calibration-sessions/{id} */
    public function destroy(Request $request, $id)
    {
        if ($response = $this->assertIsAdmin()) { return $response; }

        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $session = PerformanceCalibrationSession::where('sub_institute_id', $tenant)->find($id);

        if (!$session) {
            return $this->performanceError('Calibration session not found', 404);
        }

        if ($session->status === 'locked') {
            return $this->performanceError('A locked session cannot be deleted', 422);
        }

        // Detach participants, otherwise their reviews keep pointing at a session
        // that no longer exists and the grid count silently drifts.
        DB::table('s_performance_reviews')
            ->where('sub_institute_id', $tenant)
            ->where('calibration_session_id', $session->id)
            ->update(['calibration_session_id' => null, 'updated_at' => now()]);

        $name = $session->name;
        $cycleId = $session->cycle_id;

        $session->deleted_by = $context['user_id'];
        $session->save();
        $session->delete();

        AuditLog::record([
            'module'      => 'talent_management',
            'action'      => 'calibration_session_deleted',
            'entity_type' => 'performance_calibration_session',
            'entity_id'   => (int) $id,
            'new_values'  => ['name' => $name],
        ]);

        $this->logPerformanceActivity(
            $tenant,
            $context['user_id'],
            'deleted_calibration_session',
            'deleted calibration session ' . $name,
            'calibration',
            (int) $id,
            $name,
            null,
            null,
            $cycleId ? (int) $cycleId : null
        );

        return $this->performanceResponse(['id' => (int) $id], 'Calibration session deleted');
    }

    /* ------------------------------------------------------------------ */

    private function baseQuery(int $tenant, array $filters)
    {
        $query = DB::table('s_performance_calibration_sessions as s')
            ->leftJoin('hrms_departments as dept', 'dept.id', '=', 's.department_id')
            ->leftJoin('tbluser as fac', 'fac.id', '=', 's.facilitator_id')
            ->leftJoin('s_performance_cycles as c', 'c.id', '=', 's.cycle_id')
            ->where('s.sub_institute_id', $tenant)
            ->whereNull('s.deleted_at')
            ->select([
                's.*',
                'dept.department as department_name',
                'fac.first_name as fac_first_name',
                'fac.last_name as fac_last_name',
                'fac.user_name as fac_user_name',
                'c.name as cycle_name',
            ]);

        if (!empty($filters['cycle_id'])) {
            $query->where('s.cycle_id', $filters['cycle_id']);
        }

        if (!empty($filters['department_id'])) {
            $query->where('s.department_id', $filters['department_id']);
        }

        if (!empty($filters['status'])
            && in_array($filters['status'], ['scheduled', 'in_progress', 'completed', 'locked'], true)) {
            $query->where('s.status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('s.name', 'like', $search)
                    ->orWhere('dept.department', 'like', $search);
            });
        }

        return $query;
    }

    private function present($row, $stats = null): array
    {
        $facilitator = trim(($row->fac_first_name ?? '') . ' ' . ($row->fac_last_name ?? ''));
        $facilitator = $facilitator !== '' ? $facilitator : ($row->fac_user_name ?? null);

        $participants = $stats ? (int) $stats->participants : (int) $row->participant_count;
        $calibrated = $stats ? (int) $stats->calibrated : 0;

        return [
            'id'                    => (int) $row->id,
            'name'                  => $row->name,
            'cycle_id'              => (int) $row->cycle_id,
            'cycle'                 => $row->cycle_name,
            'department_id'         => $row->department_id ? (int) $row->department_id : null,
            'department'            => $row->department_name,
            'facilitator_id'        => $row->facilitator_id ? (int) $row->facilitator_id : null,
            'facilitator'           => $facilitator,
            'facilitator_initials'  => $facilitator ? $this->initialsOf($facilitator) : null,
            'scheduled_at'          => $row->scheduled_at,
            'scheduled_label'       => $this->dateTimeLabel($row->scheduled_at),
            'status'                => $row->status,
            'status_label'          => $this->statusLabel($row->status),
            'participant_count'     => $participants,
            'calibrated_count'      => $calibrated,
            'calibrated_pct'        => $participants > 0 ? (int) round($calibrated / $participants * 100) : 0,
            'average_rating'        => ($stats && $stats->avg_rating !== null) ? round((float) $stats->avg_rating, 2) : null,
            'distribution_target'   => is_string($row->distribution_target)
                ? json_decode($row->distribution_target, true)
                : $row->distribution_target,
            'notes'                 => $row->notes,
            'locked_at'             => $row->locked_at,
            'locked_label'          => $this->dateTimeLabel($row->locked_at),
            'is_locked'             => $row->status === 'locked',
        ];
    }

    private function presentModel(PerformanceCalibrationSession $session): array
    {
        $row = $this->baseQuery((int) $session->sub_institute_id, [])->where('s.id', $session->id)->first();

        $stats = DB::table('s_performance_reviews')
            ->where('sub_institute_id', $session->sub_institute_id)
            ->whereNull('deleted_at')
            ->where('calibration_session_id', $session->id)
            ->select(
                DB::raw('COUNT(*) as participants'),
                DB::raw('AVG(overall_rating) as avg_rating'),
                DB::raw('SUM(calibrated_rating IS NOT NULL) as calibrated')
            )
            ->first();

        return $this->present($row, $stats);
    }
}
