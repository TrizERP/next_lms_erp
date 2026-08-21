<?php

namespace App\Http\Controllers\api\TaskManagement;

use App\Http\Controllers\Controller;
use App\Http\Controllers\api\TaskManagement\Concerns\ResolvesTaskManagementContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Task Management > "What this task builds" (competency mapping).
 *
 * Ported from hp_erp's `App\Http\Controllers\Api\Competency\JobroleTaskCompetencyMapController`,
 * faithfully, but adapted from Sanctum-token identity
 * (`ResolvesCompetencyContext::competencyContext`) to this target's
 * session-based identity (`ResolvesTaskManagementContext::taskManagementContext`,
 * hydrated by the `api.session` middleware) - the same adaptation this port
 * already made everywhere else in Task Management.
 *
 * Only `forTask()` and `store()` are ported: they are the only two methods
 * `TaskCompetencyInlinePanel` (the create-task-modal's "What this task
 * builds" section) actually calls. The source's `index()`, `destroy()`,
 * `roles()` and `tasks()` back a separate matrix screen this port's frontend
 * does not have, so they are left unported rather than shipped unused.
 *
 * ── ⚠ 0 ROWS IS THE EXPECTED STATE, NOT AN UNFINISHED BUILD ────────────────
 *
 * `jobrole_task_competency_map` holds 0 rows and should. This is the
 * CATALOGUE half of the mapping, and the catalogue must be AUTHORED BY A
 * CUSTOMER, NOT DERIVED. Shipping this controller does not fill the table -
 * it stays empty until a customer maps their first task, and an empty table
 * here is the product working correctly. Do not read 0 rows as work
 * remaining.
 *
 * ── ⚠ DECLARED REFERENT: `jobrole_task_id` -> `s_jobrole_task` ─────────────
 *
 * `s_jobrole_task` is a GLOBAL seed library shared by every tenant and has NO
 * `sub_institute_id`. Two consequences carried over from the source:
 *
 *   1. A task id CANNOT be validated against the caller's tenant, because the
 *      library has no tenant. The check below confirms the task EXISTS; it
 *      cannot confirm it is "theirs", because nobody owns it.
 *   2. Tenant scoping for anything aggregated over this map comes ONLY from
 *      `sub_institute_id` on `jobrole_task_competency_map` itself.
 *
 * The competency side IS tenant-checked, because `competency` is tenant-owned.
 */
class JobroleTaskCompetencyMapController extends Controller
{
    use ResolvesTaskManagementContext;

    /**
     * GET /competency/task-map/for-task - WHAT THIS TASK EXERCISES, AND WHERE
     * THE PERSON BEING ASSIGNED IT STANDS.
     *
     * Built for the assign-task modal. THE MAPPING IS PER-TENANT EVEN THOUGH
     * THE TASK IS NOT: `s_jobrole_task` has no `sub_institute_id` - it is the
     * shared catalogue - but `jobrole_task_competency_map` does. So two
     * organisations can hold the same standard task and decide it demands
     * entirely different capabilities, and neither sees the other.
     *
     * `user_id` is OPTIONAL and names the SUBJECT - the person being assigned
     * the task. Their rating is rolled up from the KASBA items beneath each
     * competency. Absent user_id means "just show me the mapping".
     */
    public function forTask(Request $request)
    {
        $context = $this->taskManagementContext($request);
        $sid = $context['sub_institute_id'];

        $validator = Validator::make($request->all(), [
            'jobrole_task_id' => 'required|integer',
            'user_id' => 'nullable|integer',
        ]);
        if ($validator->fails()) {
            return $this->taskManagementError($validator->errors()->first(), 422);
        }

        $taskId = $request->integer('jobrole_task_id');
        $subject = $request->input('user_id') !== null ? (int) $request->input('user_id') : null;

        // ACCEPTS EITHER ID, AND RESOLVES. The assign modal may hold an
        // s_user_jobrole_task id (the tenant's own task row); the mapping is
        // keyed on s_jobrole_task (the shared catalogue). Requiring the
        // caller to know which is which pushes a schema detail into every
        // screen, so this resolves it here instead.
        //
        // Try the catalogue directly, then via catalogue_task_id - the
        // bridge column s_user_jobrole_task carries for precisely this.
        $task = DB::table('s_jobrole_task')->where('id', $taskId)->first(['id', 'task', 'jobrole']);
        $resolvedFrom = 'catalogue';

        if (! $task) {
            $bridged = DB::table('s_user_jobrole_task')
                ->where('id', $taskId)->value('catalogue_task_id');

            if ($bridged) {
                $task = DB::table('s_jobrole_task')->where('id', $bridged)->first(['id', 'task', 'jobrole']);
                $taskId = (int) $bridged;
                $resolvedFrom = 'tenant_task';
            }
        }

        if (! $task) {
            // TWO DIFFERENT ABSENCES, AND THE CALLER NEEDS TO TELL THEM APART.
            $own = DB::table('s_user_jobrole_task')->where('id', $taskId)->exists();

            return response()->json([
                'status' => 0,
                'message' => $own
                    ? 'This task is not linked to the shared task catalogue, so competencies cannot be mapped to it yet.'
                    : 'Job role task not found.',
                'reason' => $own ? 'no_catalogue_bridge' : 'not_found',
            ], 404);
        }

        // Mapped competencies for THIS tenant.
        $mapped = DB::table('jobrole_task_competency_map as m')
            ->join('competency as c', 'c.id', '=', 'm.competency_id')
            ->where('m.sub_institute_id', $sid)->where('m.jobrole_task_id', $taskId)
            ->whereNull('c.deleted_at')
            ->get(['c.id', 'c.name', 'c.code', 'c.criticality']);

        // The subject's rating per competency, rolled up from its KASBA
        // items. ROUNDED, NEVER INVENTED: a competency with no rated items
        // returns null, not zero - unrated and rated-badly are different
        // facts.
        $ratings = [];
        if ($subject && $mapped->isNotEmpty()) {
            $ratings = DB::table('competency_kasba_item as k')
                ->leftJoin('competency_kasba_rating as r', function ($j) use ($subject) {
                    $j->on('r.kasba_item_id', '=', 'k.id')->where('r.user_id', '=', $subject);
                })
                ->where('k.sub_institute_id', $sid)
                ->whereIn('k.competency_id', $mapped->pluck('id'))
                ->selectRaw('k.competency_id, COUNT(k.id) items, COUNT(r.rating) rated, AVG(r.rating) avg_rating')
                ->groupBy('k.competency_id')
                ->get()->keyBy('competency_id');
        }

        $competencies = $mapped->map(function ($c) use ($ratings) {
            $r = $ratings[$c->id] ?? null;

            return [
                'id' => (int) $c->id,
                'name' => $c->name,
                'code' => $c->code,
                'criticality' => $c->criticality,
                'items' => $r ? (int) $r->items : 0,
                'items_rated' => $r ? (int) $r->rated : 0,
                // null means UNRATED. A zero here would read as "scored nothing".
                'rating' => ($r && $r->rated > 0) ? round((float) $r->avg_rating, 1) : null,
            ];
        })->values();

        // Everything this tenant could map, so the picker has options
        // without a second request.
        $available = DB::table('competency')
            ->where('sub_institute_id', $sid)->whereNull('deleted_at')
            ->orderBy('name')->get(['id', 'name', 'code']);

        return response()->json([
            'status' => 1,
            'data' => [
                // The CATALOGUE id, after resolution - so the caller can save
                // with it and never has to know which kind it started with.
                'jobrole_task_id' => $taskId,
                'resolved_from' => $resolvedFrom,
                'task' => $task->task,
                'jobrole' => $task->jobrole,
                'user_id' => $subject,
                'competencies' => $competencies,
                'available' => $available,
            ],
            'empty_is_expected' => $competencies->isEmpty(),
            'empty_reason' => $competencies->isEmpty()
                ? 'No competencies are mapped to this task yet. Add them here so this work counts towards capability.'
                : null,
        ]);
    }

    /**
     * POST /competency/task-map - REPLACE (sync) this task's competencies
     * with exactly the submitted set, scoped to the caller's tenant.
     */
    public function store(Request $request)
    {
        $context = $this->taskManagementContext($request);
        $sid = $context['sub_institute_id'];

        $validator = Validator::make($request->all(), [
            'jobrole_task_id' => 'required|integer',
            'items' => 'required|array|min:1',
            'items.*.competency_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->taskManagementError($validator->errors()->first(), 422);
        }

        $taskId = $request->integer('jobrole_task_id');

        // EXISTS, not OWNS. s_jobrole_task is global - see the class doc.
        // Checking tenancy here would be checking a column that is not there.
        if (! DB::table('s_jobrole_task')->where('id', $taskId)->exists()) {
            return $this->taskManagementError('Job role task not found.', 404);
        }

        // A competency repeated in one request is user-trippable, so it is
        // caught before the write rather than surfacing as a constraint error.
        $seen = [];
        foreach ($request->input('items') as $i => $item) {
            $cid = (int) $item['competency_id'];
            if (isset($seen[$cid])) {
                return $this->taskManagementError('Item '.($i + 1).' repeats a competency already in this list.', 422);
            }
            $seen[$cid] = true;
        }

        // Every competency must exist in the CALLER'S OWN tenant. Reported
        // as one message rather than failing on the first, so the whole
        // list is fixable in one pass.
        $valid = DB::table('competency')
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at')
            ->whereIn('id', array_keys($seen))
            ->pluck('id')
            ->all();

        $missing = array_diff(array_keys($seen), $valid);
        if ($missing) {
            return $this->taskManagementError('Competency not found in this organisation: '.implode(', ', $missing), 422);
        }

        // SYNC, NOT APPEND: a competency removed from a task must stop
        // counting. Rows absent from `items` are deleted for THIS task and
        // THIS tenant, never wider.
        $now = now();
        $removed = DB::transaction(function () use ($seen, $sid, $taskId, $now) {
            $removed = DB::table('jobrole_task_competency_map')
                ->where('sub_institute_id', $sid)
                ->where('jobrole_task_id', $taskId)
                ->whereNotIn('competency_id', array_keys($seen))
                ->delete();

            foreach (array_keys($seen) as $cid) {
                DB::table('jobrole_task_competency_map')->updateOrInsert(
                    ['sub_institute_id' => $sid, 'jobrole_task_id' => $taskId, 'competency_id' => $cid],
                    ['updated_at' => $now, 'created_at' => $now]
                );
            }

            return $removed;
        });

        $count = DB::table('jobrole_task_competency_map')
            ->where('sub_institute_id', $sid)->where('jobrole_task_id', $taskId)->count();

        return response()->json([
            'status' => 1,
            'message' => 'Saved.',
            'mapped' => $count,
            'removed' => $removed,
        ]);
    }
}
