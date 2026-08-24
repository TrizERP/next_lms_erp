<?php

namespace App\Http\Controllers\api\TalentManagement\Competency;

use App\Http\Controllers\api\TalentManagement\Competency\Concerns\ResolvesCompetencyContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Ported from G2G's `App\Http\Controllers\Api\Competency\RoleCompetencyMapController`.
 *
 * What a job role REQUIRES — syncs `jobrole_competency_map` (job role,
 * competency, required proficiency, mandatory or not). Used by a sub-feature
 * inside the already-ported Competency Framework screen. Distinct from
 * `RoleMappingController` (`/competency/role-mapping/*`, the cell-matrix
 * editor over `s_user_skill_jobrole`) — a different table and a different
 * concept, kept apart in the source too.
 *
 * `jobrole_competency_map` already exists in this target, created by
 * `2026_08_21_130000_add_kasba_rating_support_columns.php` for the ported
 * `KasbaRatingController`, with the exact schema this controller needs
 * (`sub_institute_id, jobrole_id, competency_id, required_proficiency,
 * is_mandatory, created_by, timestamps`, unique on
 * `[sub_institute_id, jobrole_id, competency_id]`) — no new migration
 * required.
 *
 * EVERYTHING BY KEY: no name is ever written into this table, only ids — the
 * mapping survives a job-role or competency rename because it never held a
 * name to begin with. `store()` is a SYNC, not an append: competencies absent
 * from the payload are removed from that role, unchanged from the source.
 *
 * The source's `profile:admin,hr` write-route middleware has no direct
 * equivalent in this target's session-authenticated routing (see
 * `routes/competency_management.php`'s header note) and, like the other
 * non-per-employee Competency Management controllers already ported this
 * session (`CompetencyFrameworkController`, `CapabilityLibraryController`),
 * this controller operates at the organisation level rather than a specific
 * employee's profile, so `ResolvesCompetencyContext::competencySubject()`
 * does not apply here either — tenant scoping via `competencyContext()` is
 * the full authorization boundary, matching the source's other non-role-
 * gated majority.
 */
class CompetencyRoleMapController extends Controller
{
    use ResolvesCompetencyContext;

    /** GET /api/competency/role-map — what this job role requires, names resolved for display only. */
    public function index(Request $request)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $validator = Validator::make($request->all(), ['jobrole_id' => 'required|integer']);
        if ($validator->fails()) {
            return response()->json(['status' => 0, 'message' => $validator->errors()->first()], 422);
        }

        $sid = $context['sub_institute_id'];

        $rows = DB::table('jobrole_competency_map as m')
            ->join('competency as c', 'c.id', '=', 'm.competency_id')
            ->where('m.sub_institute_id', $sid)
            ->where('m.jobrole_id', $request->integer('jobrole_id'))
            ->orderBy('c.name')
            ->get([
                'm.id', 'm.competency_id', 'm.required_proficiency', 'm.is_mandatory',
                'c.name as competency_name', 'c.code as competency_code',
            ]);

        return response()->json([
            'status' => 1,
            'data'   => $rows->map(fn ($r) => [
                'id'                   => (int) $r->id,
                'competency_id'        => (int) $r->competency_id,
                // Display only. The mapping is keyed on competency_id; this name
                // is read through the join every time and never stored here.
                'competency_name'      => $r->competency_name,
                'competency_code'      => $r->competency_code,
                'required_proficiency' => (int) $r->required_proficiency,
                'is_mandatory'         => (bool) $r->is_mandatory,
            ])->values(),
        ]);
    }

    /**
     * POST /api/competency/role-map
     *
     * Bulk upsert the requirements for ONE job role. Upsert rather than
     * insert: re-saving a role's requirements is the ordinary case, and
     * making the caller delete first would lose the mapping on any failure
     * between the two calls.
     */
    public function store(Request $request)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $validator = Validator::make($request->all(), [
            'jobrole_id'                    => 'required|integer',
            'items'                         => 'required|array|min:1',
            'items.*.competency_id'         => 'required|integer',
            'items.*.required_proficiency'  => 'required|integer|min:1|max:5',
            'items.*.is_mandatory'          => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 0, 'message' => $validator->errors()->first()], 422);
        }

        $sid       = $context['sub_institute_id'];
        $actor     = $context['user_id'];
        $jobroleId = $request->integer('jobrole_id');

        // The job role must be the caller's own. Without this a mapping could be
        // hung off another tenant's role id.
        $roleOk = DB::table('s_user_jobrole')
            ->where('id', $jobroleId)
            ->where('sub_institute_id', $sid)
            ->exists();

        if (!$roleOk) {
            return response()->json(['status' => 0, 'message' => 'Job role not found.'], 404);
        }

        // uq_jcm is user-trippable: the same competency listed twice.
        $seen = [];
        foreach ($request->input('items') as $i => $item) {
            $cid = (int) $item['competency_id'];
            if (isset($seen[$cid])) {
                return response()->json([
                    'status'  => 0,
                    'message' => 'Item ' . ($i + 1) . ' repeats a competency already in this list.',
                ], 422);
            }
            $seen[$cid] = true;
        }

        // Every competency must exist inside the caller's own tenant. Reported as
        // one message rather than failing on the first, so the user fixes the
        // whole list in one pass.
        $valid = DB::table('competency')
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at')
            ->whereIn('id', array_keys($seen))
            ->pluck('id')
            ->all();

        $unknown = array_diff(array_keys($seen), $valid);
        if ($unknown) {
            return response()->json([
                'status'  => 0,
                'message' => 'These competencies do not exist in this organisation: ' . implode(', ', $unknown),
            ], 422);
        }

        $result = DB::transaction(function () use ($request, $sid, $actor, $jobroleId, $seen) {
            // SYNC SEMANTICS, not append.
            //
            // Rows absent from the payload are removed - scoped to THIS role
            // and THIS tenant, never wider.
            $removed = DB::table('jobrole_competency_map')
                ->where('sub_institute_id', $sid)
                ->where('jobrole_id', $jobroleId)
                ->whereNotIn('competency_id', array_keys($seen))
                ->delete();

            $n = 0;
            foreach ($request->input('items') as $item) {
                DB::table('jobrole_competency_map')->updateOrInsert(
                    [
                        'sub_institute_id' => $sid,
                        'jobrole_id'       => $jobroleId,
                        'competency_id'    => (int) $item['competency_id'],
                    ],
                    [
                        'required_proficiency' => (int) $item['required_proficiency'],
                        'is_mandatory'         => !empty($item['is_mandatory']) ? 1 : 0,
                        'created_by'           => $actor,
                        'updated_at'           => now(),
                    ]
                );
                $n++;
            }

            return ['written' => $n, 'removed' => $removed];
        });

        $this->logCompetencyActivity(
            $sid,
            $actor,
            'synced_role_competency_requirements',
            'Saved competency requirements for job role #' . $jobroleId,
            'jobrole_competency_map',
            $jobroleId,
            null
        );

        return response()->json([
            'status'  => 1,
            'message' => 'Requirements saved.',
            // `removed` is reported because a silent deletion is worse than none:
            // the caller can see that dropping an item from the list took effect.
            'data'    => ['jobrole_id' => $jobroleId] + $result,
        ], 201);
    }

    /** DELETE /api/competency/role-map/{id} — remove one requirement, scoped to the caller's tenant. */
    public function destroy(Request $request, $id)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $deleted = DB::table('jobrole_competency_map')
            ->where('id', $id)
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->delete();

        return response()->json(
            $deleted
                ? ['status' => 1, 'message' => 'Requirement removed.']
                : ['status' => 0, 'message' => 'Requirement not found.'],
            $deleted ? 200 : 404
        );
    }
}
