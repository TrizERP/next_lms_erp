<?php

namespace App\Http\Controllers\api\TalentManagement\Competency;

use App\Http\Controllers\api\TalentManagement\Competency\Concerns\ResolvesCompetencyContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Ported from G2G's `App\Http\Controllers\Api\Competency\FrameworkController`.
 *
 * CRUD for competency frameworks (`s_competency_frameworks` /
 * `s_competency_framework_items` / `s_competency_framework_weights`, all
 * created by this port's `2026_08_24_090000_create_competency_framework_studio_tables.php`
 * migration). Backs the "Create Framework" quick action and the Competency
 * Framework screen's Framework Mapping tab. As-is port: same logic, field
 * names and response shapes as the source - only tenant/actor context
 * resolution was adapted to the hydrated session, per `ResolvesCompetencyContext`.
 */
class CompetencyFrameworkController extends Controller
{
    use ResolvesCompetencyContext;

    public function index(Request $request)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 200);
        $page = max((int) $request->input('page', 1), 1);

        $query = DB::table('s_competency_frameworks')
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->whereNull('deleted_at');

        if ($status = $this->activeFilter($request->input('status'))) {
            $query->where('status', $status);
        }
        if ($search = $this->activeFilter($request->input('search'))) {
            $query->where('name', 'like', "%{$search}%");
        }

        $total = (clone $query)->count();
        $rows = $query->orderByDesc('id')->forPage($page, $perPage)->get();

        return response()->json([
            'status'     => 1,
            'message'    => 'Frameworks fetched successfully',
            'data'       => $rows,
            'pagination' => [
                'page'      => $page,
                'per_page'  => $perPage,
                'total'     => $total,
                'last_page' => (int) max(ceil($total / $perPage), 1),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $validator = Validator::make($request->all(), [
            'name'          => 'required|string|max:191',
            'description'   => 'nullable|string',
            'version'       => 'nullable|string|max:30',
            'status'        => 'nullable|in:draft,active,archived',
            'department_id' => 'nullable|integer',
            'jobrole'       => 'nullable|string|max:191',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 0,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $id = DB::table('s_competency_frameworks')->insertGetId([
            'sub_institute_id' => $context['sub_institute_id'],
            'name'             => $request->input('name'),
            'description'      => $request->input('description'),
            'version'          => $request->input('version', 'v1.0'),
            'status'           => $request->input('status', 'draft'),
            'department_id'    => $request->input('department_id'),
            'jobrole'          => $request->input('jobrole'),
            'created_by'       => $context['user_id'],
            'updated_by'       => $context['user_id'],
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $this->logCompetencyActivity(
            $context['sub_institute_id'],
            $context['user_id'],
            'created_framework',
            'Created framework "' . $request->input('name') . '"',
            'framework',
            $id,
            $request->input('name')
        );

        return response()->json([
            'status'  => 1,
            'message' => 'Framework created successfully',
            'data'    => ['id' => $id],
        ], 201);
    }

    public function destroy(Request $request, $id)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $existing = DB::table('s_competency_frameworks')
            ->where('id', $id)
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->whereNull('deleted_at')
            ->first();

        if (!$existing) {
            return response()->json(['status' => 0, 'message' => 'Framework not found'], 404);
        }

        DB::table('s_competency_frameworks')->where('id', $id)->update([
            'deleted_at' => now(),
            'deleted_by' => $context['user_id'],
        ]);

        $this->logCompetencyActivity(
            $context['sub_institute_id'],
            $context['user_id'],
            'deleted_framework',
            'Deleted framework "' . $existing->name . '"',
            'framework',
            (int) $id,
            $existing->name
        );

        return response()->json(['status' => 1, 'message' => 'Framework deleted successfully']);
    }

    /* ----------------------------------------------------------------- *
     * Show one framework with its competency items.
     * ----------------------------------------------------------------- */
    public function show(Request $request, $id)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $framework = DB::table('s_competency_frameworks')
            ->where('id', $id)
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->whereNull('deleted_at')
            ->first();

        if (!$framework) {
            return response()->json(['status' => 0, 'message' => 'Framework not found'], 404);
        }

        $framework->items = $this->frameworkItems($context['sub_institute_id'], (int) $id);

        return response()->json([
            'status'  => 1,
            'message' => 'Framework fetched successfully',
            'data'    => $framework,
        ]);
    }

    /* ----------------------------------------------------------------- *
     * Update framework metadata.
     * ----------------------------------------------------------------- */
    public function update(Request $request, $id)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $framework = DB::table('s_competency_frameworks')
            ->where('id', $id)
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->whereNull('deleted_at')
            ->first();

        if (!$framework) {
            return response()->json(['status' => 0, 'message' => 'Framework not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'          => 'required|string|max:191',
            'description'   => 'nullable|string',
            'version'       => 'nullable|string|max:30',
            'status'        => 'nullable|in:draft,active,archived',
            'department_id' => 'nullable|integer',
            'jobrole'       => 'nullable|string|max:191',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 0,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $update = [
            'name'          => $request->input('name'),
            'description'   => $request->input('description'),
            'version'       => $request->input('version', $framework->version),
            'status'        => $request->input('status', $framework->status),
            'department_id' => $request->input('department_id'),
            'jobrole'       => $request->input('jobrole'),
        ];

        DB::table('s_competency_frameworks')->where('id', $id)->update($update + [
            'updated_by'    => $context['user_id'],
            'updated_at'    => now(),
        ]);

        $this->logCompetencyActivity(
            $context['sub_institute_id'],
            $context['user_id'],
            'updated_framework',
            'Updated framework "' . $request->input('name') . '"',
            'framework',
            (int) $id,
            $request->input('name'),
            $this->diffChanges($framework, $update, [
                'name'          => 'Framework Name',
                'description'   => 'Description',
                'version'       => 'Version',
                'status'        => 'Status',
                'department_id' => 'Department',
                'jobrole'       => 'Job Role',
            ])
        );

        return response()->json(['status' => 1, 'message' => 'Framework updated successfully', 'data' => ['id' => (int) $id]]);
    }

    /* ----------------------------------------------------------------- *
     * Clone a framework (metadata + items) as a fresh draft.
     * ----------------------------------------------------------------- */
    public function clone(Request $request, $id)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        $source = DB::table('s_competency_frameworks')
            ->where('id', $id)
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at')
            ->first();

        if (!$source) {
            return response()->json(['status' => 0, 'message' => 'Framework not found'], 404);
        }

        $name = $request->input('name') ?: ($source->name . ' (Copy)');

        $newId = DB::table('s_competency_frameworks')->insertGetId([
            'sub_institute_id' => $sid,
            'name'             => $name,
            'description'      => $source->description,
            'version'          => 'v1.0',
            'status'           => 'draft',
            'department_id'    => $source->department_id,
            'jobrole'          => $source->jobrole,
            'created_by'       => $context['user_id'],
            'updated_by'       => $context['user_id'],
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $items = DB::table('s_competency_framework_items')
            ->where('framework_id', $id)
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at')
            ->get();

        foreach ($items as $item) {
            DB::table('s_competency_framework_items')->insert([
                'sub_institute_id'     => $sid,
                'framework_id'         => $newId,
                'competency_id'        => $item->competency_id,
                'required_proficiency' => $item->required_proficiency,
                'created_by'           => $context['user_id'],
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);
        }

        $this->logCompetencyActivity(
            $sid,
            $context['user_id'],
            'cloned_framework',
            'Cloned framework "' . $source->name . '" as "' . $name . '"',
            'framework',
            (int) $newId,
            $name
        );

        return response()->json([
            'status'  => 1,
            'message' => 'Framework cloned successfully',
            'data'    => ['id' => (int) $newId, 'name' => $name],
        ], 201);
    }

    /* ----------------------------------------------------------------- *
     * Framework items (competency -> required proficiency).
     * ----------------------------------------------------------------- */
    public function items(Request $request, $id)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }

        return response()->json([
            'status'  => 1,
            'message' => 'Framework items fetched successfully',
            'data'    => $this->frameworkItems($context['sub_institute_id'], (int) $id),
        ]);
    }

    public function storeItem(Request $request, $id)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        $validator = Validator::make($request->all(), [
            'competency_id'        => 'required|integer',
            'required_proficiency' => 'nullable|string|max:50',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status'  => 0,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $framework = DB::table('s_competency_frameworks')
            ->where('id', $id)->where('sub_institute_id', $sid)->whereNull('deleted_at')->first();
        if (!$framework) {
            return response()->json(['status' => 0, 'message' => 'Framework not found'], 404);
        }

        $competencyId = (int) $request->input('competency_id');

        $existing = DB::table('s_competency_framework_items')
            ->where('framework_id', $id)
            ->where('competency_id', $competencyId)
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at')
            ->first();

        if ($existing) {
            DB::table('s_competency_framework_items')->where('id', $existing->id)->update([
                'required_proficiency' => $request->input('required_proficiency'),
                'updated_at'           => now(),
            ]);
            $itemId = (int) $existing->id;
        } else {
            $itemId = DB::table('s_competency_framework_items')->insertGetId([
                'sub_institute_id'     => $sid,
                'framework_id'         => (int) $id,
                'competency_id'        => $competencyId,
                'required_proficiency' => $request->input('required_proficiency'),
                'created_by'           => $context['user_id'],
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);
        }

        $competencyName = DB::table('s_users_skills')->where('id', $competencyId)->value('title');

        $this->logCompetencyActivity(
            $sid,
            $context['user_id'],
            $existing ? 'updated_framework_item' : 'added_framework_item',
            ($existing ? 'Updated' : 'Added') . ' competency "' . ($competencyName ?: ('#' . $competencyId))
                . '" in framework "' . $framework->name . '"',
            'framework_item',
            $itemId,
            $competencyName ?: ('Competency #' . $competencyId),
            $existing
                ? $this->diffChanges(
                    $existing,
                    ['required_proficiency' => $request->input('required_proficiency')],
                    ['required_proficiency' => 'Required Proficiency']
                )
                : null
        );

        return response()->json([
            'status'  => 1,
            'message' => 'Framework item saved successfully',
            'data'    => ['id' => $itemId],
        ], 201);
    }

    public function destroyItem(Request $request, $id, $itemId)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $item = DB::table('s_competency_framework_items')
            ->where('id', $itemId)
            ->where('framework_id', $id)
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->whereNull('deleted_at')
            ->first();

        if (!$item) {
            return response()->json(['status' => 0, 'message' => 'Framework item not found'], 404);
        }

        DB::table('s_competency_framework_items')->where('id', $itemId)->update([
            'deleted_at' => now(),
        ]);

        $competencyName = DB::table('s_users_skills')->where('id', $item->competency_id)->value('title');
        $frameworkName = DB::table('s_competency_frameworks')->where('id', $id)->value('name');

        $this->logCompetencyActivity(
            $context['sub_institute_id'],
            $context['user_id'],
            'deleted_framework_item',
            'Removed competency "' . ($competencyName ?: ('#' . $item->competency_id))
                . '" from framework "' . ($frameworkName ?: ('#' . $id)) . '"',
            'framework_item',
            (int) $itemId,
            $competencyName ?: ('Competency #' . $item->competency_id)
        );

        return response()->json(['status' => 1, 'message' => 'Framework item removed successfully']);
    }

    /* ----------------------------------------------------------------- *
     * Per-category weighting (s_competency_framework_weights).
     * ----------------------------------------------------------------- */
    public function weights(Request $request, $id)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        // All approved categories in the catalog, so every category can be weighted.
        $categories = DB::table('s_users_skills')
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at')
            ->where('approve_status', 'Approved')
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->select('category', DB::raw('COUNT(*) as c'))
            ->groupBy('category')
            ->orderByDesc('c')
            ->pluck('category')
            ->all();

        $saved = DB::table('s_competency_framework_weights')
            ->where('sub_institute_id', $sid)
            ->where('framework_id', (int) $id)
            ->whereNull('deleted_at')
            ->pluck('weight', 'category')
            ->all();

        $rows = [];
        foreach ($categories as $category) {
            $rows[] = [
                'category' => $category,
                'weight'   => isset($saved[$category]) ? (float) $saved[$category] : 0.0,
            ];
        }

        return response()->json([
            'status'  => 1,
            'message' => 'Weighting fetched successfully',
            'data'    => $rows,
        ]);
    }

    public function saveWeights(Request $request, $id)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        $validator = Validator::make($request->all(), [
            'weights'            => 'required|array',
            'weights.*.category' => 'required|string|max:191',
            'weights.*.weight'   => 'required|numeric|min:0|max:100',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status'  => 0,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Each category that actually moved becomes one line of the audit
        // entry's Change Summary, so a re-weighting reads as a single event.
        $changes = [];

        foreach ($request->input('weights') as $row) {
            $category = $row['category'];
            $weight = (float) $row['weight'];

            $existing = DB::table('s_competency_framework_weights')
                ->where('sub_institute_id', $sid)
                ->where('framework_id', (int) $id)
                ->where('category', $category)
                ->whereNull('deleted_at')
                ->first();

            if ($existing) {
                if ((float) $existing->weight !== $weight) {
                    $changes[] = [
                        'field' => 'weight:' . $category,
                        'label' => $category . ' Weight',
                        'old'   => $existing->weight . '%',
                        'new'   => $weight . '%',
                    ];
                }

                DB::table('s_competency_framework_weights')->where('id', $existing->id)->update([
                    'weight'     => $weight,
                    'updated_by' => $context['user_id'],
                    'updated_at' => now(),
                ]);
            } else {
                $changes[] = [
                    'field' => 'weight:' . $category,
                    'label' => $category . ' Weight',
                    'old'   => null,
                    'new'   => $weight . '%',
                ];

                DB::table('s_competency_framework_weights')->insert([
                    'sub_institute_id' => $sid,
                    'framework_id'     => (int) $id,
                    'category'         => $category,
                    'weight'           => $weight,
                    'created_by'       => $context['user_id'],
                    'updated_by'       => $context['user_id'],
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
            }
        }

        $frameworkName = DB::table('s_competency_frameworks')->where('id', $id)->value('name');

        $this->logCompetencyActivity(
            $sid,
            $context['user_id'],
            'updated_framework_weights',
            'Updated category weighting for "' . ($frameworkName ?: ('framework #' . $id)) . '"',
            'framework_weight',
            (int) $id,
            $frameworkName ?: ('Framework #' . $id),
            $changes
        );

        return response()->json(['status' => 1, 'message' => 'Weighting saved successfully']);
    }

    /* ----------------------------------------------------------------- *
     * Shared: framework items joined to the skill catalog.
     * ----------------------------------------------------------------- */
    private function frameworkItems(int $sid, int $frameworkId): array
    {
        return DB::table('s_competency_framework_items as fi')
            ->leftJoin('s_users_skills as s', 's.id', '=', 'fi.competency_id')
            ->where('fi.framework_id', $frameworkId)
            ->where('fi.sub_institute_id', $sid)
            ->whereNull('fi.deleted_at')
            ->orderBy('s.category')
            ->orderBy('s.title')
            ->get([
                'fi.id',
                'fi.competency_id',
                'fi.required_proficiency',
                's.title as competency_name',
                's.category',
                's.sub_category',
                's.competency_type',
            ])
            ->map(fn ($r) => [
                'id'                   => (int) $r->id,
                'competency_id'        => (int) $r->competency_id,
                'competency_name'      => $r->competency_name,
                'category'             => $r->category,
                'sub_category'         => $r->sub_category,
                'competency_type'      => $r->competency_type,
                'required_proficiency' => $r->required_proficiency,
            ])
            ->all();
    }
}
