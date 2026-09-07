<?php

namespace App\Http\Controllers\Brain;

use App\Brain\Ingestion\FoundationIngestor;
use App\Brain\Screens\ScreenRegistry;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Brain\Support\SchemaCache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class BrainController extends Controller
{
    /* --------------------------------------------------------------- session */

    public function access(Request $request): JsonResponse
    {
        return response()->json([
            'allowed' => true,
            'tenantId' => $this->tenant($request),
            'userId' => (string) $request->attributes->get('auth.userId'),
            'role' => (string) $request->attributes->get('auth.role'),
        ]);
    }

    /** The whole screen catalogue, so the LMS navigation has one source of truth. */
    public function navigation(Request $request): JsonResponse
    {
        $screens = [];
        foreach (ScreenRegistry::all() as $key => $meta) {
            $screens[] = [
                'key' => $key,
                'title' => $meta['title'],
                'section' => $meta['section'],
                'sectionLabel' => ScreenRegistry::SECTIONS[$meta['section']] ?? $meta['section'],
                'description' => $meta['description'] ?? '',
                'dedicated' => (bool) ($meta['dedicated'] ?? false),
            ];
        }

        return response()->json(['sections' => ScreenRegistry::SECTIONS, 'screens' => $screens]);
    }

    /* -------------------------------------------------------------- overview */

    public function overview(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);

        return response()->json([
            'tenantId' => $tenant,
            'organization' => $this->organization($tenant),
            'foundation' => [
                'departments' => $this->countLms('hrms_departments', $tenant),
                'people' => $this->countLms('tbluser', $tenant),
                'students' => $this->countLms('tblstudent', $tenant),
                'skills' => $this->countLms('s_users_skills', $tenant),
                'competencies' => $this->countLms('competency', $tenant),
            ],
            'brain' => [
                'organizations' => $this->countBrain('hpbrain_organizations', $tenant),
                'departments' => $this->countBrain('hpbrain_departments', $tenant),
                'people' => $this->countBrain('hpbrain_people', $tenant),
                'capabilities' => $this->countBrain('hpbrain_capabilities', $tenant),
                'capabilityTasks' => $this->countBrain('hpbrain_capability_tasks', $tenant),
                'assignments' => $this->countBrain('hpbrain_capability_assignments', $tenant),
                'signals' => $this->countBrain('hpbrain_signals', $tenant),
                'evidence' => $this->countBrain('hpbrain_evidence', $tenant),
                'cases' => $this->countBrain('hpbrain_cases', $tenant),
                'recommendations' => $this->countBrain('hpbrain_recommendations', $tenant),
                'decisions' => $this->countBrain('hpbrain_decisions', $tenant),
                'executions' => $this->countBrain('hpbrain_eso_executions', $tenant),
                'knowledgeAssets' => $this->countBrain('hpbrain_knowledge_assets', $tenant),
                'mentalModels' => $this->countBrain('hpbrain_mental_models', $tenant),
                'policies' => $this->countBrain('hpbrain_policies', $tenant),
            ],
            'loop' => $this->loopStages($tenant),
            'capabilityCategories' => $this->breakdown('hpbrain_capabilities', $tenant, 'category', 8),
            'recentActivity' => $this->recentRows('hpbrain_audit_logs', $tenant, 10),
        ]);
    }

    /** Signal → Evidence → Case → Recommendation → Decision → Execution → Outcome. */
    private function loopStages(string $tenant): array
    {
        $stages = [
            ['key' => 'signal', 'label' => 'Signal', 'table' => 'hpbrain_signals'],
            ['key' => 'evidence', 'label' => 'Evidence', 'table' => 'hpbrain_evidence'],
            ['key' => 'case', 'label' => 'Case', 'table' => 'hpbrain_cases'],
            ['key' => 'recommendation', 'label' => 'Recommendation', 'table' => 'hpbrain_recommendations'],
            ['key' => 'decision', 'label' => 'Decision', 'table' => 'hpbrain_decisions'],
            ['key' => 'execution', 'label' => 'Execution', 'table' => 'hpbrain_eso_executions'],
            ['key' => 'outcome', 'label' => 'Outcome', 'table' => 'hpbrain_outcomes'],
        ];

        return array_map(function ($stage) use ($tenant) {
            $stage['count'] = $this->countBrain($stage['table'], $tenant);
            $stage['available'] = SchemaCache::hasTable($stage['table']);
            return $stage;
        }, $stages);
    }

    /* ------------------------------------------------------------ foundation */

    public function foundation(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);

        return response()->json([
            'organization' => $this->organization($tenant),
            'departments' => $this->lmsRows('hrms_departments', $tenant, 100),
            'people' => $this->lmsRows('tbluser', $tenant, 100),
            'students' => $this->lmsRows('tblstudent', $tenant, 100),
        ]);
    }

    /**
     * Departments — read straight from the LMS's own hrms_departments so the
     * screen shows the organization as the LMS already knows it, alongside
     * whatever has been projected into the Brain store.
     */
    public function departments(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);
        $search = trim((string) $request->query('q', ''));

        $rows = [];
        $total = 0;
        if (SchemaCache::hasTable('hrms_departments')) {
            $query = DB::table('hrms_departments')->where('sub_institute_id', $tenant);
            if (SchemaCache::hasColumn('hrms_departments', 'deleted_at')) {
                $query->whereNull('deleted_at');
            }
            if ($search !== '') {
                $query->where('department', 'like', '%'.$search.'%');
            }
            $total = (int) (clone $query)->count();
            $rows = $query->orderBy('department')->limit(300)->get()->map(fn ($r) => (array) $r)->all();
        }

        $headIds = array_values(array_filter(array_map(fn ($r) => $r['head_user_id'] ?? null, $rows)));
        $heads = [];
        if ($headIds && SchemaCache::hasTable('tbluser')) {
            foreach (DB::table('tbluser')->whereIn('id', $headIds)->get(['id', 'first_name', 'last_name']) as $user) {
                $heads[(string) $user->id] = trim($user->first_name.' '.$user->last_name);
            }
        }

        $staffByDepartment = [];
        if (SchemaCache::hasTable('tbluser') && SchemaCache::hasColumn('tbluser', 'department_id')) {
            $staffByDepartment = DB::table('tbluser')
                ->where('sub_institute_id', $tenant)
                ->select('department_id', DB::raw('COUNT(*) as aggregate'))
                ->groupBy('department_id')
                ->pluck('aggregate', 'department_id')
                ->all();
        }

        return response()->json([
            'total' => $total,
            'brainProjected' => $this->countBrain('hpbrain_departments', $tenant),
            'data' => array_map(function ($row) use ($heads, $staffByDepartment) {
                $row['head_name'] = $heads[(string) ($row['head_user_id'] ?? '')] ?? null;
                $row['staff_count'] = (int) ($staffByDepartment[$row['id']] ?? 0);
                return $row;
            }, $rows),
        ]);
    }

    /** People — the LMS's own users, which the Brain reuses rather than duplicates. */
    public function people(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);
        $search = trim((string) $request->query('q', ''));

        if (! SchemaCache::hasTable('tbluser')) {
            return response()->json(['total' => 0, 'data' => [], 'available' => false]);
        }

        $query = DB::table('tbluser')->where('sub_institute_id', $tenant);
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($inner) use ($like) {
                $inner->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('employee_no', 'like', $like);
            });
        }

        $total = (int) (clone $query)->count();
        $rows = $query->orderBy('first_name')
            ->limit(300)
            ->get(['id', 'first_name', 'last_name', 'email', 'mobile', 'gender', 'employee_no', 'department_id', 'occupation', 'status', 'user_profile_id'])
            ->map(fn ($r) => (array) $r)
            ->all();

        $departments = [];
        if (SchemaCache::hasTable('hrms_departments')) {
            $departments = DB::table('hrms_departments')
                ->where('sub_institute_id', $tenant)
                ->pluck('department', 'id')
                ->all();
        }

        $incomplete = 0;
        $rows = array_map(function ($row) use ($departments, &$incomplete) {
            $row['department_name'] = $departments[$row['department_id']] ?? null;
            $row['record_complete'] = ! empty($row['email']) && ! empty($row['mobile']) && ! empty($row['department_id']);
            if (! $row['record_complete']) {
                $incomplete++;
            }
            return $row;
        }, $rows);

        return response()->json([
            'total' => $total,
            'incomplete' => $incomplete,
            'brainProjected' => $this->countBrain('hpbrain_people', $tenant),
            'available' => true,
            'data' => $rows,
        ]);
    }

    /* ---------------------------------------------------------- capabilities */

    public function capabilities(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);
        $this->requireTable('hpbrain_capabilities');

        $query = DB::table('hpbrain_capabilities')->where('tenant_id', $tenant);
        if ($request->filled('q')) {
            $q = '%'.$request->query('q').'%';
            $query->where(function ($inner) use ($q) {
                $inner->where('name', 'like', $q)
                    ->orWhere('capability_code', 'like', $q)
                    ->orWhere('category', 'like', $q);
            });
        }
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('category')) {
            $query->where('category', $request->query('category'));
        }
        if ($request->filled('type')) {
            $query->where('capability_type', $request->query('type'));
        }

        $total = (int) (clone $query)->count();
        $rows = $query->orderBy('category')->orderBy('name')->limit(300)->get();
        $ids = $rows->pluck('id')->map(fn ($id) => (string) $id)->all();

        $assignmentCounts = $this->groupCount('hpbrain_capability_assignments', $tenant, 'capability_id', $ids);
        $assessmentCounts = $this->assessmentCounts($tenant, $ids);
        $taskCounts = $this->groupCount('hpbrain_capability_tasks', $tenant, 'capability_id', $ids);

        return response()->json([
            'total' => $total,
            'returned' => $rows->count(),
            'categories' => $this->breakdown('hpbrain_capabilities', $tenant, 'category', 40),
            'statuses' => $this->breakdown('hpbrain_capabilities', $tenant, 'status', 10),
            'types' => $this->breakdown('hpbrain_capabilities', $tenant, 'capability_type', 20),
            'summary' => [
                'capabilities' => $this->countBrain('hpbrain_capabilities', $tenant),
                'assignments' => $this->countBrain('hpbrain_capability_assignments', $tenant),
                'assessments' => $this->countBrain('hpbrain_capability_proficiency', $tenant),
                'tasks' => $this->countBrain('hpbrain_capability_tasks', $tenant),
            ],
            'data' => $rows->map(function ($row) use ($assignmentCounts, $assessmentCounts, $taskCounts) {
                $item = $this->decodeKasba((array) $row);
                $id = (string) $row->id;
                $item['assignments_count'] = $assignmentCounts[$id] ?? 0;
                $item['assessments_count'] = $assessmentCounts[$id] ?? 0;
                $item['tasks_count'] = $taskCounts[$id] ?? 0;
                return $item;
            })->values(),
        ]);
    }

    public function capabilityStore(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);
        $this->requireTable('hpbrain_capabilities');

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'capability_code' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'status' => 'nullable|string|max:50',
            'capability_type' => 'nullable|string|max:50',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $id = $this->uuid();
        $row = [
            'id' => $id,
            'tenant_id' => $tenant,
            'org_id' => 'org-'.$tenant,
            'name' => $request->input('name'),
            'capability_code' => $request->input('capability_code') ?: 'CAP-'.strtoupper(substr(str_replace('-', '', $id), 0, 8)),
            'category' => $request->input('category', 'General'),
            'description' => $request->input('description'),
            'status' => $request->input('status', 'active'),
            'capability_type' => $request->input('capability_type', 'organizational'),
            'criticality' => $request->input('criticality', 'medium'),
            'version' => 1,
            'created_by' => (string) $request->attributes->get('auth.userId'),
            'knowledge' => json_encode($this->listInput($request, 'knowledge')),
            'ability' => json_encode($this->listInput($request, 'ability')),
            'skill' => json_encode($this->listInput($request, 'skill')),
            'behaviour' => json_encode($this->listInput($request, 'behaviour')),
            'attitude' => json_encode($this->listInput($request, 'attitude')),
            'created_date' => now(),
            'updated_date' => now(),
        ];

        DB::table('hpbrain_capabilities')->insert($this->existingColumns('hpbrain_capabilities', $row));
        $this->audit($request, 'capability.created', 'Capability', $id, $row);

        return response()->json(['data' => DB::table('hpbrain_capabilities')->where('id', $id)->first()], 201);
    }

    public function capabilityShow(Request $request, string $tenantId, string $id): JsonResponse
    {
        $tenant = $this->tenant($request);
        $this->requireTable('hpbrain_capabilities');

        $capability = DB::table('hpbrain_capabilities')->where('tenant_id', $tenant)->where('id', $id)->first();
        if (! $capability) {
            return response()->json(['error' => 'capability_not_found'], 404);
        }

        $assignments = $this->rows('hpbrain_capability_assignments', $tenant, ['capability_id' => $id], 200);
        $assignmentIds = array_values(array_filter(array_map(fn ($a) => (string) ($a['id'] ?? ''), $assignments)));

        $proficiency = [];
        if ($assignmentIds && SchemaCache::hasTable('hpbrain_capability_proficiency')) {
            $proficiency = DB::table('hpbrain_capability_proficiency')
                ->where('tenant_id', $tenant)
                ->whereIn('assignment_id', $assignmentIds)
                ->limit(200)
                ->get()
                ->map(fn ($r) => (array) $r)
                ->all();
        }

        return response()->json([
            'data' => $this->decodeKasba((array) $capability),
            'assignments' => $this->labelTargets($tenant, $assignments),
            'proficiency' => $proficiency,
            'tasks' => $this->rows('hpbrain_capability_tasks', $tenant, ['capability_id' => $id], 200),
            'versions' => $this->rows('hpbrain_capability_versions', $tenant, ['capability_id' => $id], 50),
            'audit' => $this->rows('hpbrain_audit_logs', $tenant, ['entity_id' => $id], 50),
            'assignableTargets' => $this->assignableTargets($tenant),
        ]);
    }

    public function capabilityUpdate(Request $request, string $tenantId, string $id): JsonResponse
    {
        $tenant = $this->tenant($request);
        $this->requireTable('hpbrain_capabilities');

        $existing = DB::table('hpbrain_capabilities')->where('tenant_id', $tenant)->where('id', $id)->first();
        if (! $existing) {
            return response()->json(['error' => 'capability_not_found'], 404);
        }

        $allowed = ['name', 'capability_code', 'category', 'description', 'status', 'capability_type', 'criticality', 'difficulty', 'knowledge', 'ability', 'skill', 'behaviour', 'attitude'];
        $row = [];
        foreach ($allowed as $field) {
            if (! $request->has($field)) {
                continue;
            }
            $row[$field] = in_array($field, ['knowledge', 'ability', 'skill', 'behaviour', 'attitude'], true)
                ? json_encode($this->listInput($request, $field))
                : $request->input($field);
        }
        $row['updated_date'] = now();

        DB::table('hpbrain_capabilities')->where('tenant_id', $tenant)->where('id', $id)->update($this->existingColumns('hpbrain_capabilities', $row));
        $this->audit($request, 'capability.updated', 'Capability', $id, $row);

        return response()->json(['data' => DB::table('hpbrain_capabilities')->where('tenant_id', $tenant)->where('id', $id)->first()]);
    }

    public function capabilityAssign(Request $request, string $tenantId, string $id): JsonResponse
    {
        $tenant = $this->tenant($request);
        $this->requireTable('hpbrain_capability_assignments');

        $validator = Validator::make($request->all(), [
            'target_type' => 'required|string|max:80',
            'target_id' => 'required|string|max:100',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $capability = DB::table('hpbrain_capabilities')->where('tenant_id', $tenant)->where('id', $id)->first();
        if (! $capability) {
            return response()->json(['error' => 'capability_not_found'], 404);
        }

        $row = [
            'id' => $this->uuid(),
            'tenant_id' => $tenant,
            'capability_id' => $id,
            'target_type' => $request->input('target_type'),
            'target_id' => (string) $request->input('target_id'),
            'assigned_by' => (string) $request->attributes->get('auth.userId'),
            'assigned_date' => now(),
            'status' => 'active',
        ];

        DB::table('hpbrain_capability_assignments')->insert($this->existingColumns('hpbrain_capability_assignments', $row));
        $this->audit($request, 'capability.assigned', 'Capability', $id, $row);

        return response()->json(['data' => $row], 201);
    }

    public function capabilityUnassign(Request $request, string $tenantId, string $id, string $assignmentId): JsonResponse
    {
        $tenant = $this->tenant($request);
        $this->requireTable('hpbrain_capability_assignments');

        $deleted = DB::table('hpbrain_capability_assignments')
            ->where('tenant_id', $tenant)
            ->where('capability_id', $id)
            ->where('id', $assignmentId)
            ->delete();

        if (! $deleted) {
            return response()->json(['error' => 'assignment_not_found'], 404);
        }

        $this->audit($request, 'capability.unassigned', 'Capability', $id, ['assignment_id' => $assignmentId]);

        return response()->json(['deleted' => true]);
    }

    /** Who a capability can be assigned to: this tenant's own departments and people. */
    private function assignableTargets(string $tenant): array
    {
        $departments = SchemaCache::hasTable('hrms_departments')
            ? DB::table('hrms_departments')->where('sub_institute_id', $tenant)
                ->when(SchemaCache::hasColumn('hrms_departments', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
                ->orderBy('department')->limit(500)->get(['id', 'department'])
                ->map(fn ($r) => ['id' => (string) $r->id, 'label' => (string) $r->department])->all()
            : [];

        $people = SchemaCache::hasTable('tbluser')
            ? DB::table('tbluser')->where('sub_institute_id', $tenant)->orderBy('first_name')->limit(500)
                ->get(['id', 'first_name', 'last_name'])
                ->map(fn ($r) => ['id' => (string) $r->id, 'label' => trim($r->first_name.' '.$r->last_name) ?: 'User '.$r->id])->all()
            : [];

        return ['department' => $departments, 'person' => $people];
    }

    private function labelTargets(string $tenant, array $assignments): array
    {
        $departments = SchemaCache::hasTable('hrms_departments')
            ? DB::table('hrms_departments')->where('sub_institute_id', $tenant)->pluck('department', 'id')->all()
            : [];
        $people = SchemaCache::hasTable('tbluser')
            ? DB::table('tbluser')->where('sub_institute_id', $tenant)->select('id', 'first_name', 'last_name')->get()
                ->mapWithKeys(fn ($r) => [(string) $r->id => trim($r->first_name.' '.$r->last_name)])->all()
            : [];

        return array_map(function ($assignment) use ($departments, $people) {
            $type = strtolower((string) ($assignment['target_type'] ?? ''));
            $target = (string) ($assignment['target_id'] ?? '');
            $assignment['target_label'] = $type === 'department'
                ? ($departments[$target] ?? $target)
                : ($people[$target] ?? $target);
            return $assignment;
        }, $assignments);
    }

    private function assessmentCounts(string $tenant, array $capabilityIds): array
    {
        if ($capabilityIds === [] || ! SchemaCache::hasTable('hpbrain_capability_proficiency') || ! SchemaCache::hasTable('hpbrain_capability_assignments')) {
            return [];
        }

        return DB::table('hpbrain_capability_proficiency as p')
            ->join('hpbrain_capability_assignments as a', 'a.id', '=', 'p.assignment_id')
            ->where('p.tenant_id', $tenant)
            ->whereIn('a.capability_id', $capabilityIds)
            ->select('a.capability_id', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('a.capability_id')
            ->pluck('aggregate', 'capability_id')
            ->map(fn ($value) => (int) $value)
            ->all();
    }

    /* ------------------------------------------------------------- ingestion */

    public function ingestion(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);
        $ingestor = new FoundationIngestor($tenant, (string) $request->attributes->get('auth.userId'));

        return response()->json([
            'tenantId' => $tenant,
            'scopes' => FoundationIngestor::SCOPES,
            'inventory' => $ingestor->inventory(),
            'history' => $this->auditRows($tenant, 'ingestion.run', 20),
        ]);
    }

    public function ingestionRun(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);
        $requested = (array) $request->input('scopes', FoundationIngestor::SCOPES);
        $scopes = array_values(array_intersect(FoundationIngestor::SCOPES, array_map('strval', $requested)));
        if ($scopes === []) {
            return response()->json(['error' => 'no_valid_scope', 'allowed' => FoundationIngestor::SCOPES], 422);
        }

        // Organization first: departments, people and capabilities all reference it.
        usort($scopes, fn ($a, $b) => array_search($a, FoundationIngestor::SCOPES, true) <=> array_search($b, FoundationIngestor::SCOPES, true));

        // An administrator-triggered projection of thousands of LMS rows across a
        // remote database legitimately outruns the default request time limit.
        @set_time_limit(600);

        $ingestor = new FoundationIngestor($tenant, (string) $request->attributes->get('auth.userId'));
        $started = microtime(true);
        $result = $ingestor->run($scopes, (int) $request->input('limit', 2000));
        $elapsed = (int) round((microtime(true) - $started) * 1000);

        $this->audit($request, 'ingestion.run', 'Ingestion', $tenant, ['scopes' => $scopes, 'result' => $result, 'ms' => $elapsed]);
        $this->telemetry($tenant, 'ingestion.run', array_sum(array_map(fn ($r) => (int) ($r['written'] ?? 0), $result)));

        return response()->json([
            'ran' => $scopes,
            'result' => $result,
            'elapsedMs' => $elapsed,
            'inventory' => $ingestor->inventory(),
        ]);
    }

    /* ---------------------------------------------------------- screen model */

    public function section(Request $request, string $tenantId, string $section): JsonResponse
    {
        $tenant = $this->tenant($request);
        if (! isset(ScreenRegistry::SECTIONS[$section])) {
            return response()->json(['error' => 'unknown_brain_section', 'section' => $section], 404);
        }

        $screens = [];
        foreach (ScreenRegistry::inSection($section) as $key) {
            $meta = ScreenRegistry::get($key);
            $screens[] = [
                'key' => $key,
                'title' => $meta['title'],
                'description' => $meta['description'] ?? '',
                'metrics' => $this->metricsFor($meta, $tenant),
            ];
        }

        return response()->json([
            'section' => $section,
            'label' => ScreenRegistry::SECTIONS[$section],
            'tenantId' => $tenant,
            'screens' => $screens,
        ]);
    }

    public function screen(Request $request, string $tenantId, string $screen): JsonResponse
    {
        $tenant = $this->tenant($request);
        $meta = ScreenRegistry::get($screen);
        if (! $meta) {
            return response()->json(['error' => 'unknown_brain_screen', 'screen' => $screen], 404);
        }

        $search = trim((string) $request->query('q', ''));

        $panels = [];
        foreach ($meta['panels'] ?? [] as $panel) {
            $panels[] = $this->panelData($panel, $tenant, $search);
        }

        $breakdowns = [];
        foreach ($meta['breakdowns'] ?? [] as $breakdown) {
            $breakdowns[] = [
                'key' => $breakdown['key'],
                'title' => $breakdown['title'],
                'available' => SchemaCache::hasTable($breakdown['table']),
                'data' => $this->breakdown($breakdown['table'], $tenant, $breakdown['group'], 12),
            ];
        }

        $series = [];
        foreach ($meta['series'] ?? [] as $spec) {
            $series[] = $this->seriesData($spec, $tenant);
        }

        return response()->json([
            'screen' => $screen,
            'title' => $meta['title'],
            'section' => $meta['section'],
            'sectionLabel' => ScreenRegistry::SECTIONS[$meta['section']] ?? $meta['section'],
            'description' => $meta['description'] ?? '',
            'tenantId' => $tenant,
            'metrics' => $this->metricsFor($meta, $tenant),
            'panels' => $panels,
            'breakdowns' => $breakdowns,
            'series' => $series,
        ]);
    }

    private function metricsFor(array $meta, string $tenant): array
    {
        $metrics = [];
        foreach ($meta['metrics'] ?? [] as $metric) {
            $table = $metric['table'];
            $available = SchemaCache::hasTable($table) && SchemaCache::hasColumn($table, 'tenant_id');
            $value = 0;
            if ($available) {
                $query = DB::table($table)->where('tenant_id', $tenant);
                foreach ($metric['where'] ?? [] as $column => $expected) {
                    if (SchemaCache::hasColumn($table, $column)) {
                        $query->where($column, $expected);
                    }
                }
                $value = (int) $query->count();
            }

            $metrics[] = [
                'key' => $metric['key'],
                'label' => $metric['label'],
                'value' => $value,
                'available' => $available,
                'table' => $table,
            ];
        }

        return $metrics;
    }

    private function panelData(array $panel, string $tenant, string $search): array
    {
        $table = $panel['table'];
        $columns = $panel['columns'];
        $available = SchemaCache::hasTable($table) && SchemaCache::hasColumn($table, 'tenant_id');

        $out = [
            'key' => $panel['key'],
            'title' => $panel['title'],
            'table' => $table,
            'available' => $available,
            'count' => 0,
            'columns' => [],
            'rows' => [],
        ];

        if (! $available) {
            return $out;
        }

        $present = array_values(array_filter(array_keys($columns), fn ($column) => SchemaCache::hasColumn($table, $column)));
        $out['columns'] = array_map(fn ($column) => ['key' => $column, 'label' => $columns[$column]], $present);

        $query = DB::table($table)->where('tenant_id', $tenant);
        $searchable = array_values(array_filter($panel['search'] ?? [], fn ($column) => SchemaCache::hasColumn($table, $column)));
        if ($search !== '' && $searchable) {
            $like = '%'.$search.'%';
            $query->where(function ($inner) use ($searchable, $like) {
                foreach ($searchable as $column) {
                    $inner->orWhere($column, 'like', $like);
                }
            });
        }

        $out['count'] = (int) (clone $query)->count();

        foreach (['created_date', 'created_at', 'recorded_at', 'recorded_date', 'observed_date', 'updated_date'] as $column) {
            if (SchemaCache::hasColumn($table, $column)) {
                $query->orderBy($column, 'desc');
                break;
            }
        }

        $select = $present;
        if (SchemaCache::hasColumn($table, 'id') && ! in_array('id', $select, true)) {
            array_unshift($select, 'id');
        }

        $out['rows'] = $query->limit((int) ($panel['limit'] ?? 50))->get($select)->map(fn ($r) => (array) $r)->all();

        return $out;
    }

    private function seriesData(array $spec, string $tenant): array
    {
        $table = $spec['table'];
        $available = SchemaCache::hasTable($table) && SchemaCache::hasColumn($table, 'tenant_id');

        return [
            'key' => $spec['key'],
            'title' => $spec['title'],
            'available' => $available,
            'points' => $available
                ? DB::table($table)->where('tenant_id', $tenant)
                    ->orderBy($spec['at'], 'desc')->limit(60)
                    ->get([$spec['label'].' as label', $spec['value'].' as value', $spec['at'].' as at'])
                    ->map(fn ($r) => (array) $r)->reverse()->values()->all()
                : [],
        ];
    }

    /* ------------------------------------------------------------ knowledge */

    /** KASBA rolled up from this tenant's capabilities. */
    public function kasba(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);
        $this->requireTable('hpbrain_capabilities');

        $rows = DB::table('hpbrain_capabilities')
            ->where('tenant_id', $tenant)
            ->limit(500)
            ->get(['id', 'name', 'category', 'capability_type', 'knowledge', 'ability', 'skill', 'behaviour', 'attitude']);

        $facets = ['knowledge' => [], 'ability' => [], 'skill' => [], 'behaviour' => [], 'attitude' => []];
        $capabilities = [];

        foreach ($rows as $row) {
            $decoded = $this->decodeKasba((array) $row);
            $counts = [];
            foreach (array_keys($facets) as $facet) {
                $items = $decoded[$facet] ?? [];
                $counts[$facet] = count($items);
                foreach ($items as $item) {
                    $label = trim((string) $item);
                    if ($label === '') {
                        continue;
                    }
                    $facets[$facet][$label] = ($facets[$facet][$label] ?? 0) + 1;
                }
            }

            $capabilities[] = [
                'id' => $row->id,
                'name' => $row->name,
                'category' => $row->category,
                'capability_type' => $row->capability_type,
                'counts' => $counts,
            ];
        }

        $summary = [];
        foreach ($facets as $facet => $items) {
            arsort($items);
            $summary[] = [
                'facet' => $facet,
                'distinct' => count($items),
                'total' => array_sum($items),
                'top' => array_map(
                    fn ($label, $count) => ['label' => $label, 'count' => $count],
                    array_keys(array_slice($items, 0, 15, true)),
                    array_values(array_slice($items, 0, 15, true))
                ),
            ];
        }

        return response()->json([
            'tenantId' => $tenant,
            'capabilityCount' => $this->countBrain('hpbrain_capabilities', $tenant),
            'facets' => $summary,
            'capabilities' => $capabilities,
        ]);
    }

    /** AI Assistant: context-scoped search plus the AI/conversation history. */
    public function aiAssistant(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);
        $userId = (string) $request->attributes->get('auth.userId');

        return response()->json([
            'tenantId' => $tenant,
            'metrics' => [
                ['key' => 'sessions', 'label' => 'Conversations', 'value' => $this->countBrain('hpbrain_conversation_sessions', $tenant), 'available' => SchemaCache::hasTable('hpbrain_conversation_sessions')],
                ['key' => 'messages', 'label' => 'Messages', 'value' => $this->countBrain('hpbrain_conversation_messages', $tenant), 'available' => SchemaCache::hasTable('hpbrain_conversation_messages')],
                ['key' => 'executions', 'label' => 'AI executions', 'value' => $this->countBrain('hpbrain_ai_executions', $tenant), 'available' => SchemaCache::hasTable('hpbrain_ai_executions')],
                ['key' => 'templates', 'label' => 'Prompt templates', 'value' => $this->countBrain('hpbrain_prompt_templates', $tenant), 'available' => SchemaCache::hasTable('hpbrain_prompt_templates')],
            ],
            'sessions' => $this->recentRows('hpbrain_conversation_sessions', $tenant, 25),
            'executions' => $this->recentRows('hpbrain_ai_executions', $tenant, 25),
            'templates' => $this->recentRows('hpbrain_prompt_templates', $tenant, 25),
            'notifications' => SchemaCache::hasTable('hpbrain_notifications')
                ? DB::table('hpbrain_notifications')->where('tenant_id', $tenant)->where('user_id', $userId)->orderByDesc('created_date')->limit(20)->get()->map(fn ($r) => (array) $r)->all()
                : [],
        ]);
    }

    /* -------------------------------------------------------------- settings */

    public function settings(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);

        $settings = SchemaCache::hasTable('hpbrain_settings')
            ? DB::table('hpbrain_settings')->where('tenant_id', $tenant)->get()->map(function ($row) {
                $row = (array) $row;
                // Stored JSON-encoded to satisfy the column's json_valid()
                // constraint; shown decoded, which is how it was entered.
                $decoded = json_decode((string) $row['value'], true);
                $row['value'] = is_scalar($decoded) || $decoded === null
                    ? (string) ($decoded ?? '')
                    : json_encode($decoded);
                return $row;
            })->all()
            : [];

        return response()->json([
            'tenantId' => $tenant,
            'available' => SchemaCache::hasTable('hpbrain_settings'),
            'role' => (string) $request->attributes->get('auth.role'),
            'organization' => $this->organization($tenant),
            'settings' => $settings,
            'apiKeys' => SchemaCache::hasTable('hpbrain_api_keys')
                ? DB::table('hpbrain_api_keys')->where('tenant_id', $tenant)->limit(50)->get(['id', 'name', 'key_prefix', 'scopes', 'last_used_date', 'revoked_date', 'created_date'])->map(fn ($r) => (array) $r)->all()
                : [],
            'audit' => $this->recentRows('hpbrain_audit_logs', $tenant, 40),
            'permissions' => \App\Brain\Authorization\Role::permissions((string) $request->attributes->get('auth.role')),
        ]);
    }

    public function settingsUpdate(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);
        $this->requireTable('hpbrain_settings');

        $validator = Validator::make($request->all(), [
            'key' => 'required|string|max:255',
            'value' => 'present',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $key = (string) $request->input('key');
        $value = $request->input('value');

        // hpbrain_settings.value carries a json_valid() CHECK constraint, so a
        // bare scalar is stored as its JSON encoding rather than raw text.
        $encoded = json_encode($value);

        DB::table('hpbrain_settings')->updateOrInsert(
            ['tenant_id' => $tenant, 'user_id' => '_org_', 'key' => $key],
            ['value' => $encoded, 'updated_date' => now()]
        );

        $this->audit($request, 'settings.updated', 'Settings', $key, ['key' => $key, 'value' => $value]);

        return response()->json(['data' => ['key' => $key, 'value' => $value]]);
    }

    /* ---------------------------------------------------------------- search */

    public function search(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);
        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response()->json(['data' => []]);
        }

        $like = '%'.$q.'%';
        $results = [];

        $sources = [
            'capability' => ['hpbrain_capabilities', 'tenant_id', ['name', 'capability_code', 'category'], 'name'],
            'knowledge' => ['hpbrain_knowledge_assets', 'tenant_id', ['title', 'category'], 'title'],
            'eso' => ['hpbrain_eso_definitions', 'tenant_id', ['name', 'eso_code'], 'name'],
            'person' => ['tbluser', 'sub_institute_id', ['first_name', 'last_name', 'email'], 'first_name'],
            'department' => ['hrms_departments', 'sub_institute_id', ['department'], 'department'],
        ];

        foreach ($sources as $type => [$table, $tenantColumn, $fields, $labelColumn]) {
            if (! SchemaCache::hasTable($table) || ! SchemaCache::hasColumn($table, $tenantColumn)) {
                continue;
            }
            $existing = array_values(array_filter($fields, fn ($field) => SchemaCache::hasColumn($table, $field)));
            if ($existing === []) {
                continue;
            }

            $rows = DB::table($table)->where($tenantColumn, $tenant)
                ->where(function ($inner) use ($existing, $like) {
                    foreach ($existing as $field) {
                        $inner->orWhere($field, 'like', $like);
                    }
                })
                ->limit(10)->get();

            foreach ($rows as $row) {
                $record = (array) $row;
                $results[] = [
                    'type' => $type,
                    'id' => (string) ($record['id'] ?? ''),
                    'label' => (string) ($record[$labelColumn] ?? ''),
                    'record' => $record,
                ];
            }
        }

        return response()->json(['data' => $results, 'query' => $q]);
    }

    /* --------------------------------------------------------------- helpers */

    private function tenant(Request $request): string
    {
        return (string) $request->attributes->get('tenantId', $request->attributes->get('auth.tenantId'));
    }

    private function listInput(Request $request, string $field): array
    {
        $value = $request->input($field, []);
        if (is_string($value)) {
            $value = array_filter(array_map('trim', explode(',', $value)));
        }

        return array_values((array) $value);
    }

    private function decodeKasba(array $row): array
    {
        foreach (['knowledge', 'ability', 'skill', 'behaviour', 'attitude'] as $facet) {
            if (! array_key_exists($facet, $row)) {
                continue;
            }
            $decoded = is_string($row[$facet]) ? json_decode($row[$facet], true) : $row[$facet];
            $row[$facet] = is_array($decoded) ? array_values($decoded) : [];
        }

        return $row;
    }

    private function requireTable(string $table): void
    {
        if (! SchemaCache::hasTable($table)) {
            abort(response()->json(['error' => 'brain_schema_missing', 'table' => $table], 503));
        }
    }

    private function countBrain(string $table, string $tenant): int
    {
        if (! SchemaCache::hasTable($table) || ! SchemaCache::hasColumn($table, 'tenant_id')) {
            return 0;
        }

        return (int) DB::table($table)->where('tenant_id', $tenant)->count();
    }

    private function countLms(string $table, string $tenant): int
    {
        if (! SchemaCache::hasTable($table) || ! SchemaCache::hasColumn($table, 'sub_institute_id')) {
            return 0;
        }

        return (int) DB::table($table)->where('sub_institute_id', $tenant)->count();
    }

    private function organization(string $tenant): array
    {
        $name = null;
        if (SchemaCache::hasTable('school_detail')) {
            $row = DB::table('school_detail')->where('sub_institute_id', $tenant)->orderBy('id')->first();
            if ($row && $row->title) {
                $name = trim(explode(' - ', trim(preg_replace('/\s+/', ' ', strip_tags((string) $row->title))))[0]);
            }
        }

        $brain = SchemaCache::hasTable('hpbrain_organizations')
            ? DB::table('hpbrain_organizations')->where('tenant_id', $tenant)->first()
            : null;

        $detail = SchemaCache::hasTable('institute_detail')
            ? DB::table('institute_detail')->where('sub_institute_id', $tenant)->first()
            : null;

        return [
            'tenantId' => $tenant,
            'name' => $brain->name ?? $name ?? ('Organization '.$tenant),
            'orgCode' => $brain->org_code ?? ('LMS-'.$tenant),
            'projected' => (bool) $brain,
            'detail' => $detail ? (array) $detail : null,
        ];
    }

    private function lmsRows(string $table, string $tenant, int $limit): array
    {
        if (! SchemaCache::hasTable($table) || ! SchemaCache::hasColumn($table, 'sub_institute_id')) {
            return [];
        }

        return DB::table($table)->where('sub_institute_id', $tenant)->limit($limit)->get()->map(fn ($row) => (array) $row)->all();
    }

    private function rows(string $table, string $tenant, array $where, int $limit): array
    {
        if (! SchemaCache::hasTable($table) || ! SchemaCache::hasColumn($table, 'tenant_id')) {
            return [];
        }

        $query = DB::table($table)->where('tenant_id', $tenant);
        foreach ($where as $column => $value) {
            if (! SchemaCache::hasColumn($table, $column)) {
                return [];
            }
            $query->where($column, $value);
        }

        return $query->limit($limit)->get()->map(fn ($row) => (array) $row)->all();
    }

    private function recentRows(string $table, string $tenant, int $limit): array
    {
        if (! SchemaCache::hasTable($table) || ! SchemaCache::hasColumn($table, 'tenant_id')) {
            return [];
        }

        $query = DB::table($table)->where('tenant_id', $tenant);
        foreach (['created_date', 'created_at', 'recorded_at', 'recorded_date', 'updated_date', 'id'] as $column) {
            if (SchemaCache::hasColumn($table, $column)) {
                $query->orderBy($column, 'desc');
                break;
            }
        }

        return $query->limit($limit)->get()->map(fn ($row) => (array) $row)->all();
    }

    private function auditRows(string $tenant, string $action, int $limit): array
    {
        if (! SchemaCache::hasTable('hpbrain_audit_logs')) {
            return [];
        }

        return DB::table('hpbrain_audit_logs')
            ->where('tenant_id', $tenant)
            ->where('action', $action)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    private function breakdown(string $table, string $tenant, string $column, int $limit): array
    {
        if (! SchemaCache::hasTable($table) || ! SchemaCache::hasColumn($table, 'tenant_id') || ! SchemaCache::hasColumn($table, $column)) {
            return [];
        }

        return DB::table($table)
            ->where('tenant_id', $tenant)
            ->select($column.' as label', DB::raw('COUNT(*) as value'))
            ->groupBy($column)
            ->orderByDesc('value')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => ['label' => (string) ($row->label ?: 'Unspecified'), 'value' => (int) $row->value])
            ->all();
    }

    private function groupCount(string $table, string $tenant, string $groupColumn, array $ids): array
    {
        if ($ids === [] || ! SchemaCache::hasTable($table) || ! SchemaCache::hasColumn($table, 'tenant_id') || ! SchemaCache::hasColumn($table, $groupColumn)) {
            return [];
        }

        return DB::table($table)
            ->where('tenant_id', $tenant)
            ->whereIn($groupColumn, $ids)
            ->select($groupColumn, DB::raw('COUNT(*) as aggregate'))
            ->groupBy($groupColumn)
            ->pluck('aggregate', $groupColumn)
            ->map(fn ($value) => (int) $value)
            ->all();
    }

    private function existingColumns(string $table, array $row): array
    {
        if (! SchemaCache::hasTable($table)) {
            return $row;
        }

        return array_filter($row, fn ($value, $column) => SchemaCache::hasColumn($table, $column), ARRAY_FILTER_USE_BOTH);
    }

    private function audit(Request $request, string $action, string $entityType, string $entityId, array $changes): void
    {
        try {
            if (! SchemaCache::hasTable('hpbrain_audit_logs')) {
                return;
            }

            DB::table('hpbrain_audit_logs')->insert($this->existingColumns('hpbrain_audit_logs', [
                'id' => $this->uuid(),
                'tenant_id' => $this->tenant($request),
                'org_id' => 'org-'.$this->tenant($request),
                'entity_type' => $entityType,
                'entity_id' => substr($entityId, 0, 36),
                'action' => $action,
                'actor_id' => (string) $request->attributes->get('auth.userId'),
                'actor_name' => (string) $request->attributes->get('auth.role'),
                'changes' => json_encode($changes),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
                'source' => 'lms',
                'status' => 'success',
                'created_at' => now(),
            ]));
        } catch (\Throwable $e) {
            // Never turn the business action into a 500 because audit storage is unavailable.
        }
    }

    private function telemetry(string $tenant, string $event, float $value): void
    {
        try {
            if (! SchemaCache::hasTable('hpbrain_telemetry_events')) {
                return;
            }

            DB::table('hpbrain_telemetry_events')->insert($this->existingColumns('hpbrain_telemetry_events', [
                'id' => $this->uuid(),
                'tenant_id' => $tenant,
                'org_id' => 'org-'.$tenant,
                'event_type' => $event,
                'entity_type' => 'Tenant',
                'entity_id' => $tenant,
                'metric_name' => 'rows_written',
                'metric_value' => $value,
                'unit' => 'rows',
                'recorded_date' => now(),
            ]));
        } catch (\Throwable $e) {
            // Telemetry is observability, not the operation.
        }
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
