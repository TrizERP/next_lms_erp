<?php

namespace App\Http\Controllers\api\TalentManagement\Performance;

use App\Http\Controllers\Controller;
use App\Http\Controllers\api\Concerns\RequiresTalentAdmin;
use App\Http\Controllers\api\TalentManagement\Performance\Concerns\ResolvesPerformanceContext;
use App\Models\AuditLog;
use App\Models\TalentManagement\PerformanceReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ported from G2G's `App\Http\Controllers\Api\Performance\PerformanceReviewController`.
 *
 * Employee reviews - the screen's main table, the Review Board kanban and the
 * Employee Overview sidebar, plus the stage ladder and bulk actions.
 *
 * NOTE on `user_id`: on /api/performance/* it is the CONTEXT ACTOR. The employee
 * under review is set at launch time from the participant list and is never
 * writable here; filtering by employee uses `user_id_filter`.
 */
class PerformanceReviewController extends Controller
{
    use ResolvesPerformanceContext;
    use RequiresTalentAdmin;

    private const SORTABLE = [
        'employee', 'department', 'manager', 'stage', 'overall_rating',
        'status', 'due_date', 'created_at', 'updated_at',
    ];

    private const AUDIT_LABELS = [
        'stage'                  => 'Stage',
        'status'                 => 'Status',
        'self_rating'            => 'Self Rating',
        'manager_rating'         => 'Manager Rating',
        'calibrated_rating'      => 'Calibrated Rating',
        'overall_rating'         => 'Overall Rating',
        'potential_rating'       => 'Potential Rating',
        'self_comments'          => 'Self Comments',
        'manager_comments'       => 'Manager Comments',
        'due_date'               => 'Due Date',
        'manager_id'             => 'Manager',
        'is_draft'               => 'Draft',
    ];

    /**
     * GET /api/performance/reviews
     *
     * The Employee List table: server search, department / manager / status /
     * stage filters, the More-Filters dimensions, sortable columns and paging.
     */
    public function index(Request $request)
    {
        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $filters = $this->performanceFilters($request);
        ['page' => $page, 'per_page' => $perPage] = $this->performancePaging($request);
        [$sortBy, $sortDir] = $this->performanceSort($request, self::SORTABLE, 'due_date', 'asc');

        $query = $this->baseQuery($tenant, $filters);

        $total = (clone $query)->count('r.id');

        // Sorting on the joined display columns, not the raw ids.
        $orderColumn = match ($sortBy) {
            'employee'   => DB::raw('emp.first_name'),
            'department' => DB::raw('dept.department'),
            'manager'    => DB::raw('mgr.first_name'),
            default      => 'r.' . $sortBy,
        };

        $rows = $query
            ->orderBy($orderColumn, $sortDir)
            ->orderBy('r.id', 'desc')
            ->forPage($page, $perPage)
            ->get();

        return $this->performanceResponse(
            $rows->map(fn ($row) => $this->presentRow($row))->all(),
            'Success',
            200,
            [
                'pagination' => [
                    'page'      => $page,
                    'per_page'  => $perPage,
                    'total'     => $total,
                    'last_page' => (int) max(1, ceil($total / $perPage)),
                ],
            ]
        );
    }

    /**
     * GET /api/performance/reviews/board
     *
     * The Review Board kanban: the same filtered set, grouped by stage. Each
     * column is capped (default 50) so a 600-employee cycle cannot dump every
     * row into the browser; `column_total` reports the true size.
     */
    public function board(Request $request)
    {
        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $filters = $this->performanceFilters($request);
        $limit = min(200, max(10, (int) ($request->input('column_limit') ?: 50)));

        $totals = $this->baseQuery($tenant, $filters)
            ->select('r.stage', DB::raw('COUNT(*) as total'))
            ->groupBy('r.stage')
            ->pluck('total', 'stage');

        $columns = [];

        foreach (PerformanceReview::STAGES as $stage) {
            $rows = $this->baseQuery($tenant, array_merge($filters, ['stage' => $stage]))
                ->orderBy('r.due_date')
                ->orderByDesc('r.id')
                ->limit($limit)
                ->get();

            $columns[] = [
                'stage'        => $stage,
                'stage_label'  => $this->stageLabel($stage),
                'column_total' => (int) ($totals[$stage] ?? 0),
                'truncated'    => (int) ($totals[$stage] ?? 0) > $rows->count(),
                'cards'        => $rows->map(fn ($row) => $this->presentRow($row))->all(),
            ];
        }

        return $this->performanceResponse($columns);
    }

    /**
     * GET /api/performance/reviews/{id}
     *
     * The Employee Overview sidebar: employee bundle, the 5-step stepper, draft
     * ratings, the details grid and goal/decision counts for its inner tabs.
     */
    public function show(Request $request, $id)
    {
        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];

        $row = $this->baseQuery($tenant, [])->where('r.id', $id)->first();

        if (!$row) {
            return $this->performanceError('Review not found', 404);
        }

        $base = $this->presentRow($row);

        // The stepper: everything before the current stage is complete.
        $currentIndex = array_search($row->stage, PerformanceReview::STAGES, true);
        $currentIndex = $currentIndex === false ? 0 : $currentIndex;

        $stageDueDates = [
            'self_review'    => $row->cycle_self_review_due,
            'manager_review' => $row->cycle_manager_review_due,
            'calibration'    => $row->cycle_calibration_due,
            'final_review'   => $row->cycle_final_review_due,
            'completed'      => $row->cycle_period_end,
        ];

        $steps = [];
        foreach (PerformanceReview::STAGES as $index => $stage) {
            $steps[] = [
                'step'      => $index + 1,
                'key'       => $stage,
                'label'     => $this->stageLabel($stage),
                'status'    => $index < $currentIndex ? 'completed' : ($index === $currentIndex ? 'current' : 'upcoming'),
                'due_date'  => $stageDueDates[$stage] ?? null,
                'due_label' => $this->dateLabel($stageDueDates[$stage] ?? null),
            ];
        }

        // Counts for the sidebar's Goals / Reviews / Compensation / Docs tabs.
        $goalCount = DB::table('s_performance_goals')
            ->where('sub_institute_id', $tenant)->whereNull('deleted_at')
            ->where('review_id', $row->id)->count();

        $compCount = DB::table('s_performance_compensation_revisions')
            ->where('sub_institute_id', $tenant)->whereNull('deleted_at')
            ->where('user_id', $row->user_id)->count();

        $bonusCount = DB::table('s_performance_bonus_awards')
            ->where('sub_institute_id', $tenant)->whereNull('deleted_at')
            ->where('user_id', $row->user_id)->count();

        $docCount = DB::table('s_performance_attachments')
            ->where('sub_institute_id', $tenant)->whereNull('deleted_at')
            ->where('review_id', $row->id)->count();

        $commentCount = DB::table('s_performance_notes')
            ->where('sub_institute_id', $tenant)->whereNull('deleted_at')
            ->where('review_id', $row->id)->where('note_type', 'comment')->count();

        $noteCount = DB::table('s_performance_notes')
            ->where('sub_institute_id', $tenant)->whereNull('deleted_at')
            ->where('review_id', $row->id)->where('note_type', 'note')->count();

        // Every review this employee has ever had - the sidebar's Reviews tab.
        $history = DB::table('s_performance_reviews as r')
            ->leftJoin('s_performance_cycles as c', 'c.id', '=', 'r.cycle_id')
            ->where('r.sub_institute_id', $tenant)
            ->whereNull('r.deleted_at')
            ->where('r.user_id', $row->user_id)
            ->orderByDesc('c.period_start')
            ->orderByDesc('r.id')
            ->get([
                'r.id', 'r.stage', 'r.status', 'r.overall_rating', 'r.overall_rating_label',
                'r.due_date', 'c.name as cycle_name', 'c.period_start',
            ])
            ->map(fn ($item) => [
                'id'                   => (int) $item->id,
                'cycle'                => $item->cycle_name,
                'stage'                => $item->stage,
                'stage_label'          => $this->stageLabel($item->stage),
                'status'               => $item->status,
                'status_label'         => $this->statusLabel($item->status),
                'overall_rating'       => $item->overall_rating !== null ? (float) $item->overall_rating : null,
                'overall_rating_label' => $item->overall_rating_label,
                'due_label'            => $this->dateLabel($item->due_date),
                'is_current'           => (int) $item->id === (int) $row->id,
            ])
            ->all();

        return $this->performanceResponse(array_merge($base, [
            'steps'  => $steps,
            'counts' => [
                'goals'         => $goalCount,
                'compensation'  => $compCount,
                'bonus'         => $bonusCount,
                'documents'     => $docCount,
                'comments'      => $commentCount,
                'notes'         => $noteCount,
                'reviews'       => count($history),
            ],
            'review_history' => $history,
        ]));
    }

    /**
     * PUT /api/performance/reviews/{id}
     *
     * Rating / comment edits. `overall_rating` and its label stay in sync so the
     * table can render "4 - Exceeds" without recomputing it client-side.
     */
    public function update(Request $request, $id)
    {
        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $review = PerformanceReview::where('sub_institute_id', $tenant)->find($id);

        if (!$review) {
            return $this->performanceError('Review not found', 404);
        }

        $validated = $request->validate([
            'self_rating'      => 'nullable|numeric|min:0|max:10',
            'manager_rating'   => 'nullable|numeric|min:0|max:10',
            'overall_rating'   => 'nullable|numeric|min:0|max:10',
            'potential_rating' => 'nullable|numeric|min:0|max:10',
            'self_comments'    => 'nullable|string',
            'manager_comments' => 'nullable|string',
            'due_date'         => 'nullable|date',
            'manager_id'       => 'nullable|integer',
            'status'           => 'nullable|in:pending,in_progress,completed',
            'is_draft'         => 'nullable|boolean',
        ]);

        // Deliberately NOT writable here: user_id (the employee under review).
        // It is set once at launch; accepting it would let the context actor's id
        // silently reassign the review to whoever pressed the button.
        unset($validated['user_id']);

        $before = $review->getOriginal();

        if (array_key_exists('overall_rating', $validated)) {
            $validated['overall_rating_label'] = $this->ratingLabel(
                $validated['overall_rating'] !== null ? (float) $validated['overall_rating'] : null
            );
        }

        if (array_key_exists('potential_rating', $validated)) {
            $validated['potential_rating_label'] = $this->ratingLabel(
                $validated['potential_rating'] !== null ? (float) $validated['potential_rating'] : null
            );
        }

        $changes = $this->diffPerformanceChanges($before, $validated, self::AUDIT_LABELS);

        $review->fill($validated);
        $review->updated_by = $context['user_id'];
        $review->save();

        AuditLog::record([
            'module'      => 'talent_management',
            'action'      => 'review_updated',
            'entity_type' => 'performance_review',
            'entity_id'   => $review->id,
            'new_values'  => $validated,
        ]);

        if (!empty($changes)) {
            $this->logPerformanceActivity(
                $tenant,
                $context['user_id'],
                'updated_review',
                'updated the review for ' . $this->employeeName($tenant, (int) $review->user_id),
                'review',
                $review->id,
                $this->employeeName($tenant, (int) $review->user_id),
                $changes,
                $review->id,
                $review->cycle_id
            );
        }

        $row = $this->baseQuery($tenant, [])->where('r.id', $review->id)->first();

        return $this->performanceResponse($this->presentRow($row), 'Review updated');
    }

    /**
     * POST /api/performance/reviews/{id}/advance
     *
     * Moves a review along the stage ladder. `stage` jumps to a specific stage;
     * omit it to step to the next one. Entering a stage stamps the matching
     * submission timestamp and promotes the working rating to overall_rating.
     */
    public function advance(Request $request, $id)
    {
        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $review = PerformanceReview::where('sub_institute_id', $tenant)->find($id);

        if (!$review) {
            return $this->performanceError('Review not found', 404);
        }

        $request->validate([
            'stage'  => 'nullable|in:self_review,manager_review,calibration,final_review,completed',
            'rating' => 'nullable|numeric|min:0|max:10',
        ]);

        $fromStage = $review->stage;
        $currentIndex = array_search($fromStage, PerformanceReview::STAGES, true);
        $currentIndex = $currentIndex === false ? 0 : $currentIndex;

        $target = $request->input('stage');

        if (!$target) {
            if ($currentIndex >= count(PerformanceReview::STAGES) - 1) {
                return $this->performanceError('This review is already at the final stage', 422);
            }
            $target = PerformanceReview::STAGES[$currentIndex + 1];
        }

        if ($target === $fromStage) {
            return $this->performanceError('The review is already at this stage', 422);
        }

        $rating = $request->input('rating');
        $now = now();

        // Stamp the stage the review is LEAVING, so the timeline reflects reality.
        if ($fromStage === 'self_review' && !$review->self_submitted_at) {
            $review->self_submitted_at = $now;
            if ($rating !== null) {
                $review->self_rating = $rating;
            }
        }

        if ($fromStage === 'manager_review' && !$review->manager_submitted_at) {
            $review->manager_submitted_at = $now;
            if ($rating !== null) {
                $review->manager_rating = $rating;
            }
        }

        if ($fromStage === 'calibration') {
            if ($rating !== null) {
                $review->calibrated_rating = $rating;
            }
            $review->calibrated_by = $context['user_id'];
            $review->calibrated_at = $now;
        }

        // The rating shown in the table: calibrated wins, else manager, else self.
        $effective = $review->calibrated_rating ?? $review->manager_rating ?? $review->self_rating;

        if ($rating !== null && in_array($target, ['final_review', 'completed'], true)) {
            $effective = $rating;
        }

        if ($effective !== null) {
            $review->overall_rating = $effective;
            $review->overall_rating_label = $this->ratingLabel((float) $effective);
        }

        $review->stage = $target;

        if ($target === 'completed') {
            $review->status = 'completed';
            $review->is_draft = false;
            $review->finalized_at = $now;
        } else {
            $review->status = 'in_progress';
        }

        $review->updated_by = $context['user_id'];
        $review->save();

        AuditLog::record([
            'module'      => 'talent_management',
            'action'      => 'review_stage_advanced',
            'entity_type' => 'performance_review',
            'entity_id'   => $review->id,
            'new_values'  => ['from_stage' => $fromStage, 'to_stage' => $target, 'rating' => $rating],
        ]);

        $employeeName = $this->employeeName($tenant, (int) $review->user_id);

        $this->logPerformanceActivity(
            $tenant,
            $context['user_id'],
            'advanced_stage',
            'moved stage from ' . $this->stageLabel($fromStage) . ' to ' . $this->stageLabel($target)
                . ' for ' . $employeeName,
            'review',
            $review->id,
            $employeeName,
            [[
                'field' => 'stage',
                'label' => 'Stage',
                'old'   => $this->stageLabel($fromStage),
                'new'   => $this->stageLabel($target),
            ]],
            $review->id,
            $review->cycle_id
        );

        $row = $this->baseQuery($tenant, [])->where('r.id', $review->id)->first();

        return $this->performanceResponse($this->presentRow($row), 'Review moved to ' . $this->stageLabel($target));
    }

    /**
     * POST /api/performance/reviews/{id}/reminder
     *
     * The sidebar's "Send Reminder". Records the reminder on the review and in
     * the activity feed; there is no mail transport wired to this module yet, so
     * the endpoint deliberately does not claim an email was delivered.
     */
    public function sendReminder(Request $request, $id)
    {
        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $review = PerformanceReview::where('sub_institute_id', $tenant)->find($id);

        if (!$review) {
            return $this->performanceError('Review not found', 404);
        }

        $review->last_reminder_at = now();
        $review->updated_by = $context['user_id'];
        $review->save();

        AuditLog::record([
            'module'      => 'talent_management',
            'action'      => 'review_reminder_sent',
            'entity_type' => 'performance_review',
            'entity_id'   => $review->id,
            'new_values'  => ['last_reminder_at' => optional($review->last_reminder_at)->toDateTimeString()],
        ]);

        $employeeName = $this->employeeName($tenant, (int) $review->user_id);

        $this->logPerformanceActivity(
            $tenant,
            $context['user_id'],
            'sent_reminder',
            $this->stageLabel($review->stage) . ' reminder logged for ' . $employeeName,
            'review',
            $review->id,
            $employeeName,
            null,
            $review->id,
            $review->cycle_id
        );

        return $this->performanceResponse([
            'id'                  => (int) $review->id,
            'last_reminder_at'    => optional($review->last_reminder_at)->toDateTimeString(),
            'last_reminder_label' => $this->dateTimeLabel($review->last_reminder_at),
        ], 'Reminder recorded for ' . $employeeName);
    }

    /**
     * POST /api/performance/reviews/bulk
     *
     * The "Start Action" / "More" dropdowns over the table's selection.
     * action = advance | remind | assign_manager | set_due_date | complete
     */
    public function bulk(Request $request)
    {
        if ($response = $this->assertIsAdmin()) { return $response; }

        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];

        $validated = $request->validate([
            'ids'        => 'required|array|min:1',
            'ids.*'      => 'integer',
            'action'     => 'required|in:advance,remind,assign_manager,set_due_date,complete',
            'stage'      => 'nullable|in:self_review,manager_review,calibration,final_review,completed',
            'manager_id' => 'nullable|integer',
            'due_date'   => 'nullable|date',
        ]);

        $reviews = PerformanceReview::where('sub_institute_id', $tenant)
            ->whereIn('id', $validated['ids'])
            ->get();

        if ($reviews->isEmpty()) {
            return $this->performanceError('No matching reviews found', 404);
        }

        $action = $validated['action'];

        if ($action === 'assign_manager' && empty($validated['manager_id'])) {
            return $this->performanceError('manager_id is required for assign_manager', 422);
        }

        if ($action === 'set_due_date' && empty($validated['due_date'])) {
            return $this->performanceError('due_date is required for set_due_date', 422);
        }

        $now = now();
        $affected = 0;
        $skipped = [];

        foreach ($reviews as $review) {
            switch ($action) {
                case 'advance':
                    $index = array_search($review->stage, PerformanceReview::STAGES, true);
                    $index = $index === false ? 0 : $index;

                    $target = $validated['stage'] ?? (PerformanceReview::STAGES[$index + 1] ?? null);

                    if (!$target || $target === $review->stage) {
                        $skipped[] = (int) $review->id;
                        continue 2;
                    }

                    $review->stage = $target;
                    $review->status = $target === 'completed' ? 'completed' : 'in_progress';

                    if ($target === 'completed') {
                        $review->is_draft = false;
                        $review->finalized_at = $now;

                        $effective = $review->calibrated_rating ?? $review->manager_rating ?? $review->self_rating;
                        if ($effective !== null) {
                            $review->overall_rating = $effective;
                            $review->overall_rating_label = $this->ratingLabel((float) $effective);
                        }
                    }
                    break;

                case 'complete':
                    if ($review->stage === 'completed') {
                        $skipped[] = (int) $review->id;
                        continue 2;
                    }
                    $review->stage = 'completed';
                    $review->status = 'completed';
                    $review->is_draft = false;
                    $review->finalized_at = $now;

                    $effective = $review->calibrated_rating ?? $review->manager_rating ?? $review->self_rating;
                    if ($effective !== null) {
                        $review->overall_rating = $effective;
                        $review->overall_rating_label = $this->ratingLabel((float) $effective);
                    }
                    break;

                case 'remind':
                    $review->last_reminder_at = $now;
                    break;

                case 'assign_manager':
                    $review->manager_id = (int) $validated['manager_id'];
                    break;

                case 'set_due_date':
                    $review->due_date = $validated['due_date'];
                    break;
            }

            $review->updated_by = $context['user_id'];
            $review->save();
            $affected++;

            AuditLog::record([
                'module'      => 'talent_management',
                'action'      => 'review_bulk_' . $action,
                'entity_type' => 'performance_review',
                'entity_id'   => $review->id,
                'new_values'  => ['action' => $action, 'stage' => $review->stage, 'status' => $review->status],
            ]);
        }

        $labels = [
            'advance'        => 'advanced the stage of',
            'complete'       => 'completed',
            'remind'         => 'logged reminders for',
            'assign_manager' => 'reassigned the manager on',
            'set_due_date'   => 'changed the due date on',
        ];

        $this->logPerformanceActivity(
            $tenant,
            $context['user_id'],
            'bulk_' . $action,
            $labels[$action] . ' ' . $affected . ' review(s)',
            'review',
            null,
            $affected . ' review(s)',
            null,
            null,
            $reviews->first()->cycle_id
        );

        return $this->performanceResponse([
            'affected' => $affected,
            'skipped'  => $skipped,
        ], $affected . ' review(s) updated');
    }

    /**
     * DELETE /api/performance/reviews/{id}
     * Removes a participant from a cycle (soft delete).
     */
    public function destroy(Request $request, $id)
    {
        if ($response = $this->assertIsAdmin()) { return $response; }

        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $review = PerformanceReview::where('sub_institute_id', $tenant)->find($id);

        if (!$review) {
            return $this->performanceError('Review not found', 404);
        }

        $employeeName = $this->employeeName($tenant, (int) $review->user_id);
        $cycleId = $review->cycle_id;

        $review->deleted_by = $context['user_id'];
        $review->save();
        $review->delete();

        AuditLog::record([
            'module'      => 'talent_management',
            'action'      => 'review_deleted',
            'entity_type' => 'performance_review',
            'entity_id'   => (int) $id,
            'new_values'  => ['user_id' => (int) $review->user_id, 'cycle_id' => $cycleId],
        ]);

        $this->logPerformanceActivity(
            $tenant,
            $context['user_id'],
            'deleted_review',
            'removed ' . $employeeName . ' from the review cycle',
            'review',
            (int) $id,
            $employeeName,
            null,
            null,
            $cycleId
        );

        return $this->performanceResponse(['id' => (int) $id], 'Review removed');
    }

    /* ------------------------------------------------------------------ *
     * Shared query + presentation
     * ------------------------------------------------------------------ */

    /**
     * One join set for the table, the board and the sidebar so all three render
     * identical employee / department / manager / cycle values.
     */
    private function baseQuery(int $tenant, array $filters)
    {
        $query = DB::table('s_performance_reviews as r')
            ->leftJoin('tbluser as emp', 'emp.id', '=', 'r.user_id')
            ->leftJoin('tbluser as mgr', 'mgr.id', '=', 'r.manager_id')
            ->leftJoin('hrms_departments as dept', 'dept.id', '=', 'r.department_id')
            ->leftJoin('s_performance_cycles as c', 'c.id', '=', 'r.cycle_id')
            // Joined rather than resolved per row: presentRow() runs once per
            // table row, and a lookup there would be an N+1 on every page load.
            ->leftJoin('tbluser as upd', 'upd.id', '=', 'r.updated_by')
            ->where('r.sub_institute_id', $tenant)
            ->whereNull('r.deleted_at')
            ->select([
                'r.*',
                'emp.first_name as emp_first_name',
                'emp.last_name as emp_last_name',
                'emp.user_name as emp_user_name',
                'emp.employee_no as emp_employee_no',
                'emp.joined_date as emp_joined_date',
                'emp.city as emp_city',
                'emp.image as emp_image',
                'mgr.first_name as mgr_first_name',
                'mgr.last_name as mgr_last_name',
                'mgr.user_name as mgr_user_name',
                'dept.department as department_name',
                'upd.first_name as upd_first_name',
                'upd.last_name as upd_last_name',
                'upd.user_name as upd_user_name',
                'c.name as cycle_name',
                'c.code as cycle_code',
                'c.cycle_type as cycle_type',
                'c.status as cycle_status',
                'c.period_start as cycle_period_start',
                'c.period_end as cycle_period_end',
                'c.self_review_due as cycle_self_review_due',
                'c.manager_review_due as cycle_manager_review_due',
                'c.calibration_due as cycle_calibration_due',
                'c.final_review_due as cycle_final_review_due',
            ]);

        if (!empty($filters['cycle_id'])) {
            $query->where('r.cycle_id', $filters['cycle_id']);
        }

        if (!empty($filters['department_id'])) {
            $query->where('r.department_id', $filters['department_id']);
        }

        if (!empty($filters['manager_id'])) {
            $query->where('r.manager_id', $filters['manager_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('r.status', $filters['status']);
        }

        if (!empty($filters['stage'])) {
            $query->where('r.stage', $filters['stage']);
        }

        if (!empty($filters['user_id_filter'])) {
            $query->where('r.user_id', $filters['user_id_filter']);
        }

        if (!empty($filters['jobrole'])) {
            $query->where('r.jobrole', $filters['jobrole']);
        }

        if (!empty($filters['rating_min'])) {
            $query->where('r.overall_rating', '>=', $filters['rating_min']);
        }

        if (!empty($filters['rating_max'])) {
            $query->where('r.overall_rating', '<=', $filters['rating_max']);
        }

        if (!empty($filters['overdue_only'])) {
            $query->whereNotNull('r.due_date')
                ->where('r.due_date', '<', now()->toDateString())
                ->where('r.stage', '!=', 'completed');
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('emp.first_name', 'like', $search)
                    ->orWhere('emp.last_name', 'like', $search)
                    ->orWhere('emp.user_name', 'like', $search)
                    ->orWhere('emp.employee_no', 'like', $search)
                    ->orWhere('r.jobrole', 'like', $search)
                    ->orWhere('dept.department', 'like', $search);
            });
        }

        return $query;
    }

    /** The exact shape the table row, kanban card and sidebar all consume. */
    private function presentRow($row): array
    {
        $employeeName = trim(($row->emp_first_name ?? '') . ' ' . ($row->emp_last_name ?? ''));
        $employeeName = $employeeName !== '' ? $employeeName : ($row->emp_user_name ?? 'Unknown');

        $managerName = trim(($row->mgr_first_name ?? '') . ' ' . ($row->mgr_last_name ?? ''));
        $managerName = $managerName !== '' ? $managerName : ($row->mgr_user_name ?? null);

        $updatedByName = trim(($row->upd_first_name ?? '') . ' ' . ($row->upd_last_name ?? ''));
        $updatedByName = $updatedByName !== '' ? $updatedByName : ($row->upd_user_name ?? null);

        $isOverdue = $row->due_date
            && $row->stage !== 'completed'
            && \Carbon\Carbon::parse($row->due_date)->lt(now()->startOfDay());

        return [
            'id'       => (int) $row->id,
            'employee' => [
                'id'          => (int) $row->user_id,
                'name'        => $employeeName,
                'employee_no' => $row->emp_employee_no,
                'initials'    => $this->initialsOf($employeeName),
                'image'       => $row->emp_image,
                'joined_date' => $row->emp_joined_date,
                'joined_label'=> $this->dateLabel($row->emp_joined_date),
                'location'    => $row->emp_city,
            ],
            'department_id'          => $row->department_id ? (int) $row->department_id : null,
            'department'             => $row->department_name,
            'jobrole'                => $row->jobrole,
            'manager_id'             => $row->manager_id ? (int) $row->manager_id : null,
            'manager'                => $managerName,
            'manager_initials'       => $managerName ? $this->initialsOf($managerName) : null,
            'cycle_id'               => (int) $row->cycle_id,
            'cycle'                  => $row->cycle_name,
            'cycle_type'             => $row->cycle_type,
            'cycle_type_label'       => $row->cycle_type ? ucwords(str_replace('_', ' ', $row->cycle_type)) : null,
            'stage'                  => $row->stage,
            'stage_label'            => $this->stageLabel($row->stage),
            'status'                 => $row->status,
            'status_label'           => $this->statusLabel($row->status),
            'self_rating'            => $row->self_rating !== null ? (float) $row->self_rating : null,
            'manager_rating'         => $row->manager_rating !== null ? (float) $row->manager_rating : null,
            'calibrated_rating'      => $row->calibrated_rating !== null ? (float) $row->calibrated_rating : null,
            'overall_rating'         => $row->overall_rating !== null ? (float) $row->overall_rating : null,
            'overall_rating_label'   => $row->overall_rating_label,
            'potential_rating'       => $row->potential_rating !== null ? (float) $row->potential_rating : null,
            'potential_rating_label' => $row->potential_rating_label,
            'is_draft'               => (bool) $row->is_draft,
            'self_comments'          => $row->self_comments,
            'manager_comments'       => $row->manager_comments,
            'due_date'               => $row->due_date,
            'due_label'              => $this->dateLabel($row->due_date),
            'is_overdue'             => (bool) $isOverdue,
            'self_submitted_label'   => $this->dateTimeLabel($row->self_submitted_at),
            'manager_submitted_label'=> $this->dateTimeLabel($row->manager_submitted_at),
            'calibrated_label'       => $this->dateTimeLabel($row->calibrated_at),
            'finalized_label'        => $this->dateTimeLabel($row->finalized_at),
            'last_reminder_label'    => $this->dateTimeLabel($row->last_reminder_at),
            'updated_at'             => $row->updated_at,
            'updated_label'          => $this->dateTimeLabel($row->updated_at),
            'updated_by_id'          => $row->updated_by ? (int) $row->updated_by : null,
            'updated_by'             => $updatedByName,
            'updated_by_initials'    => $updatedByName ? $this->initialsOf($updatedByName) : null,
        ];
    }

    /** Display name used in activity descriptions. */
    private function employeeName(int $tenant, int $userId): string
    {
        return $this->resolveActorName($userId) ?? 'employee #' . $userId;
    }
}
