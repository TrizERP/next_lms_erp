<?php

namespace App\Http\Controllers\api\TalentManagement\Competency;

use App\Http\Controllers\api\TalentManagement\Competency\Concerns\ResolvesCompetencyContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Ported from G2G's `App\Http\Controllers\Api\Competency\RoleMappingController`.
 *
 * Confirmed in scope by reading the G2G frontend's `services/competency/studio.ts`
 * (the Competency Framework screen's service layer): `getRoles`/`getMatrix`/
 * `saveCell`/`clearCell` are called from the Role Mapping Matrix tab, so this
 * controller is ported alongside Framework/Studio rather than skipped.
 *
 * The Role Mapping Matrix: competencies (rows) x job roles (columns), each cell
 * carrying the required proficiency level for that (competency, role) pair.
 *
 * Cells live in the existing tenant-scoped s_user_skill_jobrole table
 * (jobrole, skill, proficiency_level) - the same table the rest of this port's
 * Competency Management controllers already read as "roles mapped". As-is
 * port: same logic, field names and response shapes as the source - only
 * tenant/actor context resolution was adapted, per `ResolvesCompetencyContext`.
 *
 * DEVIATION FROM AS-IS: `s_user_skill_jobrole.jobrole` is NOT a role name in
 * this target's data, despite the column name - it stores `s_user_jobrole.id`
 * (confirmed: 100% of 50k+ rows for this tenant are purely numeric). The
 * source assumed this column held display names directly. Rather than
 * silently showing raw ids in the Role Mapping Matrix's column headers (the
 * bug reported), `roles()` and `matrix()` below resolve the real name via
 * `s_user_jobrole` and return it ALONGSIDE the raw id - the id stays the key
 * used for `cells`/`saveCell`/`clearCell` (unchanged write contract, and it's
 * what actually uniquely identifies a role here), the resolved name is only
 * for display.
 */
class RoleMappingController extends Controller
{
    use ResolvesCompetencyContext;

    /* ----------------------------------------------------------------- *
     * Column source: job roles ranked by how many skills they have mapped.
     * ----------------------------------------------------------------- */
    public function roles(Request $request)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        $perPage = min(max((int) $request->input('per_page', 8), 1), 50);
        $page = max((int) $request->input('page', 1), 1);
        $search = $this->activeFilter($request->input('search'));
        $category = $this->activeFilter($request->input('category'));

        $query = DB::table('s_user_skill_jobrole as m')
            ->where('m.sub_institute_id', $sid)
            ->whereNull('m.deleted_at')
            ->whereNotNull('m.jobrole')
            ->where('m.jobrole', '!=', '');

        // Rank only by mappings whose skill is in the chosen category.
        if ($category !== null) {
            $titles = $this->categorySkillTitles($sid, $category);
            $query->whereIn('m.skill', empty($titles) ? ['\0__none__'] : $titles);
        }
        if ($search !== null) {
            $query->where('m.jobrole', 'like', "%{$search}%");
        }

        $grouped = $query
            ->select('m.jobrole', DB::raw('COUNT(DISTINCT m.skill) as mapped_count'))
            ->groupBy('m.jobrole')
            ->orderByDesc('mapped_count')
            ->orderBy('m.jobrole');

        $all = $grouped->get();
        $total = $all->count();
        $rows = $all->forPage($page, $perPage)->values();

        // `m.jobrole` is s_user_jobrole.id, not a name - resolve the real
        // name + department by id (see class doc-block).
        $roleIds = $rows->pluck('jobrole')->filter(fn ($v) => is_numeric($v))->map(fn ($v) => (int) $v)->all();
        $roleMasters = [];
        if (!empty($roleIds)) {
            $roleMasters = DB::table('s_user_jobrole')
                ->where('sub_institute_id', $sid)
                ->whereNull('deleted_at')
                ->whereIn('id', $roleIds)
                ->select('id', 'jobrole', 'department')
                ->get()
                ->keyBy('id');
        }

        $data = $rows->map(function ($r) use ($roleMasters) {
            $master = is_numeric($r->jobrole) ? ($roleMasters[(int) $r->jobrole] ?? null) : null;
            return [
                'jobrole'      => $r->jobrole,
                // The resolved display name - falls back to the raw id only
                // when it truly cannot be resolved, so the UI never shows a
                // blank column header.
                'jobrole_name' => $master->jobrole ?? $r->jobrole,
                'department'   => $master->department ?? null,
                'mapped_count' => (int) $r->mapped_count,
            ];
        })->all();

        return response()->json([
            'status'     => 1,
            'message'    => 'Job roles fetched successfully',
            'data'       => $data,
            'pagination' => [
                'page'      => $page,
                'per_page'  => $perPage,
                'total'     => $total,
                'last_page' => (int) max(ceil($total / $perPage), 1),
            ],
        ]);
    }

    /* ----------------------------------------------------------------- *
     * The matrix for one category across a set of selected roles.
     * ----------------------------------------------------------------- */
    public function matrix(Request $request)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        $category = $this->activeFilter($request->input('category'));
        $roles = $this->rolesInput($request->input('jobroles'));

        // Rows: competencies in the selected category (fallback: all approved).
        $compQuery = DB::table('s_users_skills')
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at')
            ->where('approve_status', 'Approved');
        if ($category !== null) {
            $compQuery->where('category', $category);
        }
        $competencies = $compQuery
            ->orderBy('title')
            ->limit(300)
            ->get(['id', 'title', 'description', 'category', 'sub_category', 'competency_type', 'proficiency_level'])
            ->map(fn ($c) => [
                'id'              => (int) $c->id,
                'title'           => $c->title,
                'description'     => $c->description,
                'category'        => $c->category,
                'sub_category'    => $c->sub_category,
                'competency_type' => $c->competency_type,
                'proficiency_level' => $c->proficiency_level,
            ])
            ->values();

        $titles = $competencies->pluck('title')->all();

        // Cells: existing mappings for these (role, skill) pairs.
        $cells = [];
        if (!empty($roles) && !empty($titles)) {
            $rows = DB::table('s_user_skill_jobrole')
                ->where('sub_institute_id', $sid)
                ->whereNull('deleted_at')
                ->whereIn('jobrole', $roles)
                ->whereIn('skill', $titles)
                ->get(['id', 'jobrole', 'skill', 'proficiency_level']);

            foreach ($rows as $row) {
                // Last write wins if duplicates exist for the same pair.
                $cells[$row->jobrole][$row->skill] = [
                    'id'    => (int) $row->id,
                    'level' => $this->normalizeLevel($row->proficiency_level),
                    'raw'   => $row->proficiency_level,
                ];
            }
        }

        // Resolve display names for the column headers - see class doc-block
        // on why `$roles` (the raw s_user_skill_jobrole.jobrole values) are
        // ids here, not names.
        $roleIds = collect($roles)->filter(fn ($v) => is_numeric($v))->map(fn ($v) => (int) $v)->all();
        $roleNames = [];
        if (!empty($roleIds)) {
            $roleNames = DB::table('s_user_jobrole')
                ->where('sub_institute_id', $sid)
                ->whereNull('deleted_at')
                ->whereIn('id', $roleIds)
                ->pluck('jobrole', 'id')
                ->all();
        }
        $resolvedNames = [];
        foreach ($roles as $role) {
            $resolvedNames[$role] = $roleNames[(int) $role] ?? $role;
        }

        return response()->json([
            'status'  => 1,
            'message' => 'Matrix fetched successfully',
            'data'    => [
                'category'     => $category,
                'roles'        => array_values($roles),
                // id -> display name, so the frontend never has to render a
                // raw s_user_jobrole id as a column header.
                'role_names'   => $resolvedNames,
                'competencies' => $competencies,
                'cells'        => $cells,
            ],
        ]);
    }

    /* ----------------------------------------------------------------- *
     * Set / update one cell's required proficiency.
     * ----------------------------------------------------------------- */
    public function upsertCell(Request $request)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        $validator = Validator::make($request->all(), [
            'jobrole'           => 'required|string|max:191',
            'skill'             => 'required_without:competency_id|string|max:191',
            'competency_id'     => 'required_without:skill|integer',
            'proficiency_level' => 'required|string|max:20',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status'  => 0,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $jobrole = $request->input('jobrole');
        $skill = $this->resolveSkillTitle($sid, $request->input('skill'), $request->input('competency_id'));
        if ($skill === null) {
            return response()->json(['status' => 0, 'message' => 'Competency not found'], 404);
        }
        $level = (string) $request->input('proficiency_level');

        $existing = DB::table('s_user_skill_jobrole')
            ->where('sub_institute_id', $sid)
            ->where('jobrole', $jobrole)
            ->where('skill', $skill)
            ->whereNull('deleted_at')
            ->first();

        if ($existing) {
            DB::table('s_user_skill_jobrole')->where('id', $existing->id)->update([
                'proficiency_level' => $level,
                'updated_by'        => $context['user_id'],
                'updated_at'        => now(),
            ]);
            $id = (int) $existing->id;
        } else {
            // Carry over the skill's catalog metadata where available.
            $skillRow = DB::table('s_users_skills')
                ->where('sub_institute_id', $sid)
                ->where('title', $skill)
                ->whereNull('deleted_at')
                ->first();

            $id = DB::table('s_user_skill_jobrole')->insertGetId([
                'jobrole'           => $jobrole,
                'skill'             => $skill,
                'type'              => 'S',
                'proficiency_level' => $level,
                'skill_code'        => $skillRow->skill_code ?? null,
                'sub_institute_id'  => $sid,
                'created_by'        => $context['user_id'],
                'updated_by'        => $context['user_id'],
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        }

        $this->logCompetencyActivity(
            $sid,
            $context['user_id'],
            'mapped_role_competency',
            'Set "' . $skill . '" to level ' . $level . ' for "' . $jobrole . '"',
            'role_mapping',
            $id,
            $jobrole . ' - ' . $skill,
            $existing
                ? $this->diffChanges($existing, ['proficiency_level' => $level], ['proficiency_level' => 'Required Proficiency'])
                : null
        );

        return response()->json([
            'status'  => 1,
            'message' => 'Mapping saved successfully',
            'data'    => ['id' => $id, 'jobrole' => $jobrole, 'skill' => $skill, 'proficiency_level' => $level],
        ]);
    }

    /* ----------------------------------------------------------------- *
     * Clear a cell (soft delete the mapping row).
     * ----------------------------------------------------------------- */
    public function deleteCell(Request $request)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        $validator = Validator::make($request->all(), [
            'jobrole'       => 'required|string|max:191',
            'skill'         => 'required_without:competency_id|string|max:191',
            'competency_id' => 'required_without:skill|integer',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status'  => 0,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $jobrole = $request->input('jobrole');
        $skill = $this->resolveSkillTitle($sid, $request->input('skill'), $request->input('competency_id'));
        if ($skill === null) {
            return response()->json(['status' => 0, 'message' => 'Competency not found'], 404);
        }

        $affected = DB::table('s_user_skill_jobrole')
            ->where('sub_institute_id', $sid)
            ->where('jobrole', $jobrole)
            ->where('skill', $skill)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => now(),
                'deleted_by' => $context['user_id'],
            ]);

        if ($affected === 0) {
            return response()->json(['status' => 0, 'message' => 'Mapping not found'], 404);
        }

        $this->logCompetencyActivity(
            $sid,
            $context['user_id'],
            'unmapped_role_competency',
            'Cleared "' . $skill . '" from "' . $jobrole . '"',
            'role_mapping',
            null,
            $jobrole . ' - ' . $skill
        );

        return response()->json(['status' => 1, 'message' => 'Mapping cleared successfully']);
    }

    /* ----------------------------------------------------------------- *
     * Helpers
     * ----------------------------------------------------------------- */

    /** Distinct skill titles belonging to a category (for scoping). */
    private function categorySkillTitles(int $sid, string $category): array
    {
        return DB::table('s_users_skills')
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at')
            ->where('approve_status', 'Approved')
            ->where('category', $category)
            ->distinct()
            ->pluck('title')
            ->all();
    }

    /** Normalise a mixed proficiency value ("4", "Advanced") to an int 1..6 or null. */
    private function normalizeLevel($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }
        $map = [
            'awareness' => 1, 'basic' => 2, 'foundational' => 2, 'working knowledge' => 2,
            'intermediate' => 3, 'applied' => 3, 'applied expertise' => 3,
            'advanced' => 4, 'advanced practice' => 4,
            'expert' => 5, 'strategic' => 5, 'strategic leadership' => 5,
        ];
        return $map[strtolower(trim((string) $value))] ?? null;
    }

    /** Resolve the canonical skill title from an explicit title or a competency_id. */
    private function resolveSkillTitle(int $sid, $skill, $competencyId): ?string
    {
        if (is_string($skill) && trim($skill) !== '') {
            return trim($skill);
        }
        if (is_numeric($competencyId)) {
            $row = DB::table('s_users_skills')
                ->where('sub_institute_id', $sid)
                ->where('id', (int) $competencyId)
                ->whereNull('deleted_at')
                ->first();
            return $row->title ?? null;
        }
        return null;
    }

    /** Accept jobroles as an array or comma-separated string; drop blanks. */
    private function rolesInput($input): array
    {
        if (is_array($input)) {
            $roles = $input;
        } elseif (is_string($input) && $input !== '') {
            $roles = explode(',', $input);
        } else {
            return [];
        }

        return collect($roles)
            ->map(fn ($r) => trim((string) $r))
            ->filter(fn ($r) => $r !== '' && $r !== 'all')
            ->unique()
            ->values()
            ->all();
    }
}
