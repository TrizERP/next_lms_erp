<?php

namespace App\Http\Controllers\api\TalentManagement\Competency;

use App\Http\Controllers\api\TalentManagement\Competency\Concerns\ManagesCompetencySettings;
use App\Http\Controllers\api\TalentManagement\Competency\Concerns\ResolvesCompetencyContext;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Ported from G2G's `App\Http\Controllers\Api\Competency\StudioController`.
 *
 * Read models for the Framework & Role Mapping Studio that don't belong to a
 * single CRUD resource: the five summary cards, the Framework Structure
 * category tree, and the Proficiency Scale (+ KASA behavioural indicators).
 * As-is port: same logic, field names and response shapes as the source -
 * only tenant/actor context resolution was adapted, per
 * `ResolvesCompetencyContext`.
 *
 * Table note - `s_user_jobrole` and `s_jobrole_skills` (the role master and its
 * master-baseline skill counts, read by `summary()`) already exist in this
 * target: both are used directly, unadapted, by other already-ported
 * Competency Management controllers (e.g. `CertificationController`,
 * `CareerPathController` for the former; `s_jobrole_skills` backs
 * `app/Models/lms/library/jobroleSkillModel.php`, confirmed via a codebase
 * grep before this port). Everything else this controller reads
 * (`s_users_skills`, `s_user_skill_jobrole`, `s_competency_frameworks`,
 * `s_competency_framework_weights`) was already present or created earlier in
 * this port. `s_proficiency_levels` and `s_proficiency_{knowledge,ability,
 * attitude,behaviour}` (the Proficiency Scale tab) and `s_competency_settings`
 * (the Weighting Configuration panel, via `ManagesCompetencySettings`) did not
 * exist and were created by this port's
 * `2026_08_24_090100_create_competency_studio_support_tables.php` migration.
 */
class CompetencyStudioController extends Controller
{
    use ResolvesCompetencyContext;
    use ManagesCompetencySettings;

    /**
     * The "Weighting Configuration" panel's scoring rules, with the defaults a
     * tenant that has never saved them gets. Keys are the allowed set: anything
     * else in the request body is ignored.
     */
    private const WEIGHTING_DEFAULTS = [
        // weighted | simple - how a competency score rolls up across categories
        'scoring_model'     => 'weighted',
        // none | half | whole - rounding applied to the rolled-up score
        'rounding'          => 'half',
        // the score a role is expected to reach, as a percentage
        'target_threshold'  => 80,
        // zero | exclude - how a competency with no mapping counts
        'unmapped_handling' => 'exclude',
        // which surfaces the category weights are applied to
        'apply_to'          => ['assessments', 'gap_analysis'],
    ];

    private const SCORING_MODELS = ['weighted', 'simple'];
    private const ROUNDING_MODES = ['none', 'half', 'whole'];
    private const UNMAPPED_MODES = ['zero', 'exclude'];
    private const APPLY_TARGETS = ['assessments', 'gap_analysis', 'role_readiness', 'development_plans'];

    /* ----------------------------------------------------------------- *
     * Weighting Configuration (scoring rules behind the category weights)
     * ----------------------------------------------------------------- */

    /** GET /competency/studio/weighting-config */
    public function weightingConfig(Request $request)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }

        return response()->json([
            'status'  => 1,
            'message' => 'Weighting configuration fetched successfully',
            'data'    => [
                'settings' => $this->competencySettings(
                    $context['sub_institute_id'],
                    'weighting',
                    self::WEIGHTING_DEFAULTS
                ),
                'options'  => [
                    'scoring_model'     => [
                        ['value' => 'weighted', 'label' => 'Weighted average', 'hint' => 'Each category contributes in proportion to its weight.'],
                        ['value' => 'simple',   'label' => 'Simple average',   'hint' => 'Every competency counts equally; weights are ignored.'],
                    ],
                    'rounding'          => [
                        ['value' => 'none',  'label' => 'No rounding'],
                        ['value' => 'half',  'label' => 'Nearest 0.5'],
                        ['value' => 'whole', 'label' => 'Nearest whole level'],
                    ],
                    'unmapped_handling' => [
                        ['value' => 'exclude', 'label' => 'Excluded', 'hint' => 'Competencies with no mapping are left out of the score.'],
                        ['value' => 'zero',    'label' => 'Counted as zero', 'hint' => 'An unmapped competency drags the score down.'],
                    ],
                    'apply_to'          => [
                        ['value' => 'assessments',       'label' => 'Assessment scoring'],
                        ['value' => 'gap_analysis',      'label' => 'Gap analysis'],
                        ['value' => 'role_readiness',    'label' => 'Role readiness / % match'],
                        ['value' => 'development_plans', 'label' => 'Development plan priorities'],
                    ],
                ],
            ],
        ]);
    }

    /** PUT /competency/studio/weighting-config */
    public function saveWeightingConfig(Request $request)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        $validator = Validator::make($request->all(), [
            'scoring_model'     => 'sometimes|required|in:' . implode(',', self::SCORING_MODELS),
            'rounding'          => 'sometimes|required|in:' . implode(',', self::ROUNDING_MODES),
            'target_threshold'  => 'sometimes|required|integer|min:0|max:100',
            'unmapped_handling' => 'sometimes|required|in:' . implode(',', self::UNMAPPED_MODES),
            'apply_to'          => 'sometimes|array',
            'apply_to.*'        => 'in:' . implode(',', self::APPLY_TARGETS),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 0,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $before = $this->competencySettings($sid, 'weighting', self::WEIGHTING_DEFAULTS);

        $settings = $this->saveCompetencySettings(
            $sid,
            'weighting',
            $request->all(),
            self::WEIGHTING_DEFAULTS,
            $context['user_id']
        );

        $labels = [
            'scoring_model'     => 'Scoring Model',
            'rounding'          => 'Rounding',
            'target_threshold'  => 'Target Threshold',
            'unmapped_handling' => 'Unmapped Competencies',
            'apply_to'          => 'Applied To',
        ];

        $changes = [];
        foreach ($labels as $key => $label) {
            $old = is_array($before[$key]) ? implode(', ', $before[$key]) : $before[$key];
            $new = is_array($settings[$key]) ? implode(', ', $settings[$key]) : $settings[$key];
            if ((string) $old !== (string) $new) {
                $changes[] = ['field' => $key, 'label' => $label, 'old' => $old, 'new' => $new];
            }
        }

        $this->logCompetencyActivity(
            $sid,
            $context['user_id'],
            'updated_weighting_config',
            'Updated the competency scoring configuration',
            'framework_weight',
            null,
            'Weighting Configuration',
            $changes
        );

        return response()->json([
            'status'  => 1,
            'message' => 'Weighting configuration saved successfully',
            'data'    => ['settings' => $settings],
        ]);
    }

    /* ----------------------------------------------------------------- *
     * Summary cards + mapping-coverage donut
     * ----------------------------------------------------------------- */
    public function summary(Request $request)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        // Active framework (most recently touched 'active' one).
        $activeFramework = DB::table('s_competency_frameworks')
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at')
            ->where('status', 'active')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();

        // Total published competencies == approved skills in the catalog.
        $totalCompetencies = DB::table('s_users_skills')
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at')
            ->where('approve_status', 'Approved')
            ->count();

        // Roles + mapping coverage (distinct jobrole names, internally consistent).
        $allRoles = DB::table('s_user_jobrole')
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at')
            ->whereNotNull('jobrole')
            ->where('jobrole', '!=', '')
            ->distinct()
            ->pluck('jobrole')
            ->all();
        $totalRoles = count($allRoles);

        // Tenant per-role distinct skill counts (actual mapping).
        $tenantCounts = DB::table('s_user_skill_jobrole')
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at')
            ->whereNotNull('jobrole')
            ->select('jobrole', DB::raw('COUNT(DISTINCT skill) as c'))
            ->groupBy('jobrole')
            ->pluck('c', 'jobrole')
            ->all();

        // Master baseline distinct skill counts per role (recommended set).
        $masterCounts = DB::table('s_jobrole_skills')
            ->whereIn('jobrole', $totalRoles > 0 ? $allRoles : ['\0__none__'])
            ->select('jobrole', DB::raw('COUNT(DISTINCT skill) as c'))
            ->groupBy('jobrole')
            ->pluck('c', 'jobrole')
            ->all();

        $fully = 0;
        $partial = 0;
        $notMapped = 0;
        foreach ($allRoles as $role) {
            $actual = (int) ($tenantCounts[$role] ?? 0);
            $expected = (int) ($masterCounts[$role] ?? 0);

            if ($actual === 0) {
                $notMapped++;
            } elseif ($expected > 0 && $actual < $expected) {
                $partial++;
            } else {
                $fully++;
            }
        }
        $rolesMapped = $fully + $partial;
        $coverage = $totalRoles > 0 ? (int) round(($rolesMapped / $totalRoles) * 100) : 0;

        $pct = fn ($n) => $totalRoles > 0 ? (int) round(($n / $totalRoles) * 100) : 0;

        return response()->json([
            'status'  => 1,
            'message' => 'Studio summary fetched successfully',
            'data'    => [
                'active_framework' => $activeFramework ? [
                    'id'      => (int) $activeFramework->id,
                    'name'    => $activeFramework->name,
                    'status'  => $activeFramework->status,
                    'version' => $activeFramework->version,
                ] : null,
                'total_competencies' => $totalCompetencies,
                'roles_mapped'       => $rolesMapped,
                'total_roles'        => $totalRoles,
                'coverage_percent'   => $coverage,
                // Last published: the active framework's timestamp, else the most
                // recently touched framework of any status (so it's never blank
                // when frameworks exist).
                'last_published'     => $this->lastPublished($sid, $activeFramework),
                'mapping_summary' => [
                    'total_roles'     => $totalRoles,
                    'fully_mapped'    => $fully,
                    'partially_mapped'=> $partial,
                    'not_mapped'      => $notMapped,
                    'fully_pct'       => $pct($fully),
                    'partial_pct'     => $pct($partial),
                    'not_pct'         => $pct($notMapped),
                ],
            ],
        ]);
    }

    /* ----------------------------------------------------------------- *
     * Framework Structure — category tree from the skill catalog
     * ----------------------------------------------------------------- */
    public function frameworkStructure(Request $request)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        $search = $this->activeFilter($request->input('search'));

        $base = DB::table('s_users_skills')
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at')
            ->where('approve_status', 'Approved')
            ->whereNotNull('category')
            ->where('category', '!=', '');

        // Category-level counts.
        $categoryRows = (clone $base)
            ->select('category', DB::raw('COUNT(*) as c'))
            ->groupBy('category')
            ->orderByDesc('c')
            ->get();

        // Sub-category counts, bucketed under their category.
        $subRows = (clone $base)
            ->whereNotNull('sub_category')
            ->where('sub_category', '!=', '')
            ->select('category', 'sub_category', DB::raw('COUNT(*) as c'))
            ->groupBy('category', 'sub_category')
            ->orderBy('category')
            ->orderByDesc('c')
            ->get();

        $childrenByCategory = [];
        foreach ($subRows as $r) {
            $childrenByCategory[$r->category][] = [
                'name'  => $r->sub_category,
                'count' => (int) $r->c,
            ];
        }

        $tree = [];
        $index = 0;
        foreach ($categoryRows as $r) {
            if ($search && stripos($r->category, $search) === false) {
                // Keep the category if a child matches the search.
                $childMatch = collect($childrenByCategory[$r->category] ?? [])
                    ->contains(fn ($c) => stripos($c['name'], $search) !== false);
                if (!$childMatch) {
                    continue;
                }
            }
            $index++;
            $tree[] = [
                'index'    => $index,
                'category' => $r->category,
                'count'    => (int) $r->c,
                'children' => $childrenByCategory[$r->category] ?? [],
            ];
        }

        return response()->json([
            'status'  => 1,
            'message' => 'Framework structure fetched successfully',
            'data'    => $tree,
        ]);
    }

    /* ----------------------------------------------------------------- *
     * Proficiency scale + KASA behavioural indicators
     * ----------------------------------------------------------------- */
    public function proficiencyScale(Request $request)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        // Tenant-global scale (skill_id NULL). Fall back to any tenant rows.
        $levelQuery = DB::table('s_proficiency_levels')
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at');

        $levels = (clone $levelQuery)->whereNull('skill_id')
            ->orderByRaw('CAST(proficiency_type AS UNSIGNED)')
            ->get();

        if ($levels->isEmpty()) {
            $levels = $levelQuery->orderByRaw('CAST(proficiency_type AS UNSIGNED)')->get();
        }

        $scale = $levels->map(fn ($row) => [
            'id'          => (int) $row->id,
            'level'       => (int) $row->proficiency_type,
            'label'       => $row->proficiency_level,      // "Level 3"
            'name'        => $row->type_description,        // "Applied Expertise"
            'description' => $row->description,
        ])->values()->all();

        // KASA descriptors (one small read per dimension).
        $kasa = [];
        foreach ([
            'knowledge' => 's_proficiency_knowledge',
            'ability'   => 's_proficiency_ability',
            'attitude'  => 's_proficiency_attitude',
            'behaviour' => 's_proficiency_behaviour',
        ] as $key => $table) {
            $rows = DB::table($table)
                ->where('sub_institute_id', $sid)
                ->orderByRaw('CAST(level AS UNSIGNED)')
                ->get(['level', 'descriptor', 'indicators']);

            $kasa[$key] = $rows->map(fn ($r) => [
                'level'      => (int) $r->level,
                'descriptor' => $r->descriptor,
                'indicators' => $r->indicators,
            ])->values()->all();
        }

        return response()->json([
            'status'  => 1,
            'message' => 'Proficiency scale fetched successfully',
            'data'    => [
                'levels' => $scale,
                'kasa'   => $kasa,
            ],
        ]);
    }

    /** Add a level to the tenant-global proficiency scale. */
    public function storeLevel(Request $request)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:191',
            'description' => 'nullable|string',
            'level'       => 'nullable|integer|min:1|max:20',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 0, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $next = $request->input('level');
        if (!$next) {
            $max = DB::table('s_proficiency_levels')
                ->where('sub_institute_id', $sid)
                ->whereNull('skill_id')
                ->whereNull('deleted_at')
                ->max(DB::raw('CAST(proficiency_type AS UNSIGNED)'));
            $next = (int) $max + 1;
        }

        $id = DB::table('s_proficiency_levels')->insertGetId([
            'skill_id'          => null,
            'proficiency_level' => 'Level ' . $next,
            'proficiency_type'  => (string) $next,
            'type_description'  => $request->input('name'),
            'description'       => $request->input('description'),
            'sub_institute_id'  => $sid,
            'created_by'        => $context['user_id'],
            'updated_by'        => $context['user_id'],
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $this->logCompetencyActivity(
            $sid,
            $context['user_id'],
            'created_proficiency_level',
            'Added proficiency level ' . $next . ' "' . $request->input('name') . '"',
            'proficiency_level',
            $id,
            $request->input('name')
        );

        return response()->json(['status' => 1, 'message' => 'Level added successfully', 'data' => ['id' => $id]], 201);
    }

    /** Edit a proficiency level's name / description / label. */
    public function updateLevel(Request $request, $id)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:191',
            'description' => 'nullable|string',
            'label'       => 'nullable|string|max:191',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 0, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $existing = DB::table('s_proficiency_levels')
            ->where('id', $id)->where('sub_institute_id', $sid)->whereNull('deleted_at')->first();
        if (!$existing) {
            return response()->json(['status' => 0, 'message' => 'Level not found'], 404);
        }

        $update = [
            'type_description' => $request->input('name'),
            'description'      => $request->input('description'),
        ];
        if ($request->filled('label')) {
            $update['proficiency_level'] = $request->input('label');
        }

        DB::table('s_proficiency_levels')->where('id', $id)->update($update + [
            'updated_by'       => $context['user_id'],
            'updated_at'       => now(),
        ]);

        $this->logCompetencyActivity(
            $sid,
            $context['user_id'],
            'updated_proficiency_level',
            'Updated proficiency level "' . $request->input('name') . '"',
            'proficiency_level',
            (int) $id,
            $request->input('name'),
            $this->diffChanges($existing, $update, [
                'type_description'  => 'Level Name',
                'description'       => 'Description',
                'proficiency_level' => 'Level Label',
            ])
        );

        return response()->json(['status' => 1, 'message' => 'Level updated successfully']);
    }

    /** Soft-delete a proficiency level. */
    public function deleteLevel(Request $request, $id)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        $existing = DB::table('s_proficiency_levels')
            ->where('id', $id)->where('sub_institute_id', $sid)->whereNull('deleted_at')->first();
        if (!$existing) {
            return response()->json(['status' => 0, 'message' => 'Level not found'], 404);
        }

        DB::table('s_proficiency_levels')->where('id', $id)->update([
            'deleted_at' => now(),
            'deleted_by' => $context['user_id'],
        ]);

        $levelName = $existing->type_description ?: $existing->proficiency_level;

        $this->logCompetencyActivity(
            $sid,
            $context['user_id'],
            'deleted_proficiency_level',
            'Deleted proficiency level "' . $levelName . '"',
            'proficiency_level',
            (int) $id,
            $levelName
        );

        return response()->json(['status' => 1, 'message' => 'Level deleted successfully']);
    }

    /** Formatted last-published date: active framework, else latest of any status. */
    private function lastPublished(int $sid, $activeFramework): ?string
    {
        $latest = $activeFramework ?: DB::table('s_competency_frameworks')
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();

        return $latest && $latest->updated_at
            ? Carbon::parse($latest->updated_at)->format('M j, Y')
            : null;
    }

    /* ----------------------------------------------------------------- *
     * Default category weighting profile (framework_id = null).
     * The Weighting & Configuration tab edits this tenant-wide profile;
     * per-framework overrides live on CompetencyFrameworkController::weights.
     * ----------------------------------------------------------------- */
    public function weights(Request $request)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

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
            ->whereNull('framework_id')
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

    public function saveWeights(Request $request)
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
                ->whereNull('framework_id')
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
                    'framework_id'     => null,
                    'category'         => $category,
                    'weight'           => $weight,
                    'created_by'       => $context['user_id'],
                    'updated_by'       => $context['user_id'],
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
            }
        }

        $this->logCompetencyActivity(
            $sid,
            $context['user_id'],
            'updated_framework_weights',
            'Updated the tenant default competency weighting profile',
            'framework_weight',
            null,
            'Default Weighting Profile',
            $changes
        );

        return response()->json(['status' => 1, 'message' => 'Weighting saved successfully']);
    }
}
