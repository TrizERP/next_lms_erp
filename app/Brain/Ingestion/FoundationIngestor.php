<?php

namespace App\Brain\Ingestion;

use Illuminate\Support\Facades\DB;
use App\Brain\Support\SchemaCache;
use Illuminate\Support\Facades\Schema;

/**
 * Projects the LMS's own foundation records into the Brain store.
 *
 * The LMS remains the system of record. Nothing is invented here: every Brain
 * row traces back to a row this tenant already owns —
 *
 *   school_detail / institute_detail   -> hpbrain_organizations
 *   hrms_departments                   -> hpbrain_departments
 *   tbluser                            -> hpbrain_people
 *   s_users_skills + competency        -> hpbrain_capabilities
 *   s_skill_knowledge_ability          -> capability KASBA (knowledge/ability/
 *                                         behaviour/attitude), skill from
 *                                         s_users_skills.sub_skills
 *   s_users_skills.tasklist            -> hpbrain_capability_tasks
 *
 * Ids are derived from the source row, so re-running is an update rather than a
 * second copy.
 */
class FoundationIngestor
{
    public const SCOPES = ['organization', 'departments', 'people', 'capabilities'];

    public function __construct(private string $tenantId, private string $actorId)
    {
    }

    /** What is in the LMS versus what has been projected, per scope. */
    public function inventory(): array
    {
        return [
            [
                'scope' => 'organization',
                'label' => 'Organization',
                'source' => 'school_detail / institute_detail',
                'sourceCount' => $this->lmsCount('institute_detail'),
                'target' => 'hpbrain_organizations',
                'targetCount' => $this->brainCount('hpbrain_organizations'),
            ],
            [
                'scope' => 'departments',
                'label' => 'Departments',
                'source' => 'hrms_departments',
                'sourceCount' => $this->lmsCount('hrms_departments'),
                'target' => 'hpbrain_departments',
                'targetCount' => $this->brainCount('hpbrain_departments'),
            ],
            [
                'scope' => 'people',
                'label' => 'People',
                'source' => 'tbluser',
                'sourceCount' => $this->lmsCount('tbluser'),
                'target' => 'hpbrain_people',
                'targetCount' => $this->brainCount('hpbrain_people'),
            ],
            [
                'scope' => 'capabilities',
                'label' => 'Capabilities',
                'source' => 's_users_skills + competency',
                'sourceCount' => $this->lmsCount('s_users_skills') + $this->lmsCount('competency'),
                'target' => 'hpbrain_capabilities',
                'targetCount' => $this->brainCount('hpbrain_capabilities'),
            ],
        ];
    }

    public function run(array $scopes, int $limit = 2000): array
    {
        $result = [];
        foreach ($scopes as $scope) {
            $result[$scope] = match ($scope) {
                'organization' => $this->ingestOrganization(),
                'departments' => $this->ingestDepartments($limit),
                'people' => $this->ingestPeople($limit),
                'capabilities' => $this->ingestCapabilities($limit),
                default => ['skipped' => true, 'reason' => 'unknown_scope'],
            };
        }

        return $result;
    }

    /* ------------------------------------------------------------------ scopes */

    private function ingestOrganization(): array
    {
        if (! SchemaCache::hasTable('hpbrain_organizations')) {
            return ['written' => 0, 'available' => false];
        }

        $name = $this->organizationName();

        $this->upsert('hpbrain_organizations', ['id' => $this->orgId()], [
            'id' => $this->orgId(),
            'tenant_id' => $this->tenantId,
            'name' => $name,
            'legal_name' => $name,
            'org_code' => 'LMS-'.$this->tenantId,
            'industry' => 'education',
            'country' => 'IN',
            'timezone' => 'Asia/Kolkata',
            'currency' => 'INR',
            'status' => 'active',
            'created_by' => $this->actorId,
            'created_date' => now(),
            'updated_date' => now(),
        ]);

        return ['written' => 1, 'available' => true, 'name' => $name];
    }

    private function ingestDepartments(int $limit): array
    {
        if (! SchemaCache::hasTable('hpbrain_departments') || ! SchemaCache::hasTable('hrms_departments')) {
            return ['written' => 0, 'available' => false];
        }

        $rows = DB::table('hrms_departments')
            ->where('sub_institute_id', $this->tenantId)
            ->when(SchemaCache::hasColumn('hrms_departments', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
            ->limit($limit)
            ->get();

        $batch = [];
        foreach ($rows as $row) {
            $id = $this->id('dep', $row->id);
            $batch[] = [
                'id' => $id,
                'tenant_id' => $this->tenantId,
                'org_id' => $this->orgId(),
                'name' => (string) ($row->department ?? 'Department '.$row->id),
                'description' => (string) ($row->description ?? ''),
                'department_type' => 'academic',
                'parent_department_id' => $row->parent_id ? $this->id('dep', $row->parent_id) : null,
                'head_id' => $row->head_user_id ? $this->id('per', $row->head_user_id) : null,
                'status' => ((string) ($row->status ?? '1')) === '0' ? 'inactive' : 'active',
                'created_by' => $this->actorId,
                'created_date' => now(),
                'updated_date' => now(),
            ];
        }

        return ['written' => $this->bulkUpsert('hpbrain_departments', $batch), 'available' => true];
    }

    private function ingestPeople(int $limit): array
    {
        if (! SchemaCache::hasTable('hpbrain_people') || ! SchemaCache::hasTable('tbluser')) {
            return ['written' => 0, 'available' => false];
        }

        $rows = DB::table('tbluser')
            ->where('sub_institute_id', $this->tenantId)
            ->limit($limit)
            ->get();

        $batch = [];
        foreach ($rows as $row) {
            $id = $this->id('per', $row->id);
            $first = trim((string) ($row->first_name ?? ''));
            $last = trim((string) ($row->last_name ?? ''));
            $display = trim($first.' '.$last) ?: (string) ($row->user_name ?? 'User '.$row->id);

            $batch[] = [
                'id' => $id,
                'tenant_id' => $this->tenantId,
                'org_id' => $this->orgId(),
                'employee_id' => (string) ($row->employee_no ?: $row->id),
                'first_name' => $first ?: $display,
                'last_name' => $last,
                'display_name' => $display,
                'email' => (string) ($row->email ?: $row->id.'@lms.local'),
                'phone' => (string) ($row->mobile ?? ''),
                'gender' => (string) ($row->gender ?? ''),
                'employment_type' => 'full_time',
                'employment_status' => ((string) ($row->status ?? '1')) === '0' ? 'inactive' : 'active',
                'department_id' => $row->department_id ? $this->id('dep', $row->department_id) : null,
                'reporting_manager_id' => $row->reporting_manager_id ? $this->id('per', $row->reporting_manager_id) : null,
                'designation' => (string) ($row->occupation ?? ''),
                'location' => (string) ($row->city ?? ''),
                'status' => 'active',
                'created_by' => $this->actorId,
                'created_date' => now(),
                'updated_date' => now(),
            ];
        }

        return ['written' => $this->bulkUpsert('hpbrain_people', $batch), 'available' => true];
    }

    private function ingestCapabilities(int $limit): array
    {
        if (! SchemaCache::hasTable('hpbrain_capabilities')) {
            return ['written' => 0, 'available' => false];
        }

        $capabilities = [];
        $taskBatch = [];

        if (SchemaCache::hasTable('s_users_skills')) {
            $skills = DB::table('s_users_skills')
                ->where('sub_institute_id', $this->tenantId)
                ->when(SchemaCache::hasColumn('s_users_skills', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
                ->limit($limit)
                ->get();

            $kasba = $this->kasbaFor($skills->pluck('id')->all());

            foreach ($skills as $row) {
                $id = $this->id('cap', $row->id);
                $sub = array_values(array_filter(array_map('trim', explode(',', (string) ($row->sub_skills ?? '')))));

                $capabilities[] = [
                    'id' => $id,
                    'tenant_id' => $this->tenantId,
                    'org_id' => $this->orgId(),
                    'capability_code' => (string) ($row->skill_code ?: 'SKL-'.$row->id),
                    'name' => (string) ($row->title ?: 'Capability '.$row->id),
                    'description' => (string) ($row->description ?? ''),
                    'category' => (string) ($row->category ?: 'General'),
                    'capability_type' => strtolower((string) ($row->competency_type ?: 'skill')),
                    'difficulty' => (int) ($row->proficiency_level ?: 1),
                    'criticality' => (string) ($row->skill_importance ?: 'medium'),
                    'version' => 1,
                    'status' => strtolower((string) ($row->status ?: 'active')) === 'active' ? 'active' : 'inactive',
                    'created_by' => $this->actorId,
                    'knowledge' => json_encode($kasba[$row->id]['knowledge'] ?? []),
                    'ability' => json_encode($kasba[$row->id]['ability'] ?? []),
                    'skill' => json_encode($sub),
                    'behaviour' => json_encode($kasba[$row->id]['behaviour'] ?? []),
                    'attitude' => json_encode($kasba[$row->id]['attitude'] ?? []),
                    'created_date' => now(),
                    'updated_date' => now(),
                ];
                foreach ($this->taskRows($id, (string) ($row->tasklist ?? '')) as $task) {
                    $taskBatch[] = $task;
                }
            }
        }

        if (SchemaCache::hasTable('competency')) {
            $rows = DB::table('competency')
                ->where('sub_institute_id', $this->tenantId)
                ->when(SchemaCache::hasColumn('competency', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
                ->limit($limit)
                ->get();

            $items = SchemaCache::hasTable('competency_kasba_item')
                ? DB::table('competency_kasba_item')->where('sub_institute_id', $this->tenantId)->get()->groupBy('competency_id')
                : collect();

            foreach ($rows as $row) {
                $id = $this->id('cmp', $row->id);
                $bucket = ['knowledge' => [], 'ability' => [], 'skill' => [], 'behaviour' => [], 'attitude' => []];
                foreach ($items->get($row->id, collect()) as $item) {
                    $type = strtolower((string) $item->kasba_type);
                    if (isset($bucket[$type])) {
                        $bucket[$type][] = (string) $item->item_label;
                    }
                }

                $capabilities[] = [
                    'id' => $id,
                    'tenant_id' => $this->tenantId,
                    'org_id' => $this->orgId(),
                    'capability_code' => (string) ($row->code ?: 'CMP-'.$row->id),
                    'name' => (string) ($row->name ?: 'Competency '.$row->id),
                    'description' => (string) ($row->description ?? ''),
                    'category' => (string) ($row->category ?: 'Competency'),
                    'capability_type' => strtolower((string) ($row->competency_type ?: 'organizational')),
                    'criticality' => (string) ($row->criticality ?: 'medium'),
                    'version' => 1,
                    'status' => strtolower((string) ($row->status ?: 'active')) === 'active' ? 'active' : 'inactive',
                    'created_by' => $this->actorId,
                    'knowledge' => json_encode($bucket['knowledge']),
                    'ability' => json_encode($bucket['ability']),
                    'skill' => json_encode($bucket['skill']),
                    'behaviour' => json_encode($bucket['behaviour']),
                    'attitude' => json_encode($bucket['attitude']),
                    'created_date' => now(),
                    'updated_date' => now(),
                ];
            }
        }

        return [
            'written' => $this->bulkUpsert('hpbrain_capabilities', $capabilities),
            'tasks' => $this->bulkUpsert('hpbrain_capability_tasks', $taskBatch),
            'available' => true,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function taskRows(string $capabilityId, string $tasklist): array
    {
        if ($tasklist === '' || ! SchemaCache::hasTable('hpbrain_capability_tasks')) {
            return [];
        }

        $rows = [];
        foreach (array_values(array_filter(array_map('trim', explode(',', $tasklist)))) as $index => $name) {
            $rows[] = [
                'id' => substr($capabilityId, 0, 26).'-t'.$index,
                'tenant_id' => $this->tenantId,
                'capability_id' => $capabilityId,
                'name' => $name,
                'description' => '',
                'evidence_required' => 0,
                'status' => 'active',
                'created_by' => $this->actorId,
                'created_date' => now(),
            ];
        }

        return $rows;
    }

    /* ----------------------------------------------------------------- helpers */

    /** knowledge / ability / behaviour / attitude items keyed by source skill id. */
    private function kasbaFor(array $skillIds): array
    {
        if ($skillIds === [] || ! SchemaCache::hasTable('s_skill_knowledge_ability')) {
            return [];
        }

        $out = [];
        foreach (array_chunk($skillIds, 500) as $chunk) {
            $rows = DB::table('s_skill_knowledge_ability')
                ->where('sub_institute_id', $this->tenantId)
                ->whereIn('skill_id', $chunk)
                ->limit(20000)
                ->get(['skill_id', 'classification', 'classification_item']);

            foreach ($rows as $row) {
                $type = strtolower(trim((string) $row->classification));
                $item = trim((string) $row->classification_item);
                if ($item === '' || ! in_array($type, ['knowledge', 'ability', 'skill', 'behaviour', 'attitude'], true)) {
                    continue;
                }
                $out[$row->skill_id][$type][] = $item;
            }
        }

        foreach ($out as $skillId => $types) {
            foreach ($types as $type => $values) {
                $out[$skillId][$type] = array_values(array_slice(array_unique($values), 0, 40));
            }
        }

        return $out;
    }

    private function organizationName(): string
    {
        if (SchemaCache::hasTable('school_detail')) {
            $row = DB::table('school_detail')
                ->where('sub_institute_id', $this->tenantId)
                ->orderBy('id')
                ->first();
            if ($row && $row->title) {
                $name = trim(preg_replace('/\s+/', ' ', strip_tags((string) $row->title)));
                $name = trim(explode(' - ', $name)[0]);
                if ($name !== '') {
                    return $name;
                }
            }
        }

        return 'Organization '.$this->tenantId;
    }

    private function orgId(): string
    {
        return $this->id('org', $this->tenantId);
    }

    private function id(string $prefix, $sourceId): string
    {
        return substr($prefix.'-'.$this->tenantId.'-'.$sourceId, 0, 36);
    }

    private function upsert(string $table, array $key, array $values): void
    {
        DB::table($table)->updateOrInsert($key, $this->existingColumns($table, $values));
    }

    /**
     * One INSERT ... ON DUPLICATE KEY UPDATE per chunk rather than a SELECT and
     * an INSERT per row. The database is remote; row-at-a-time round trips are
     * what made a 617-row department ingest take over a minute.
     */
    private function bulkUpsert(string $table, array $rows, int $chunk = 250): int
    {
        if ($rows === [] || ! SchemaCache::hasTable($table)) {
            return 0;
        }

        // Every row in one INSERT must carry the same columns in the same order.
        // The capability batch mixes two sources (s_users_skills sets
        // `difficulty`, competency does not), so the union is filled in with
        // nulls rather than letting the shorter rows shift the value list.
        $rows = array_map(fn ($row) => $this->existingColumns($table, $row), $rows);
        $keys = [];
        foreach ($rows as $row) {
            foreach (array_keys($row) as $key) {
                $keys[$key] = true;
            }
        }
        $keys = array_keys($keys);
        $template = array_fill_keys($keys, null);

        $written = 0;
        foreach (array_chunk($rows, $chunk) as $slice) {
            $slice = array_map(fn ($row) => array_replace($template, $row), $slice);
            $update = array_values(array_diff($keys, ['id', 'created_date']));
            DB::table($table)->upsert($slice, ['id'], $update);
            $written += count($slice);
        }

        return $written;
    }

    private function existingColumns(string $table, array $row): array
    {
        return array_filter($row, fn ($value, $column) => SchemaCache::hasColumn($table, $column), ARRAY_FILTER_USE_BOTH);
    }

    private function lmsCount(string $table): int
    {
        if (! SchemaCache::hasTable($table) || ! SchemaCache::hasColumn($table, 'sub_institute_id')) {
            return 0;
        }

        return (int) DB::table($table)->where('sub_institute_id', $this->tenantId)->count();
    }

    private function brainCount(string $table): int
    {
        if (! SchemaCache::hasTable($table) || ! SchemaCache::hasColumn($table, 'tenant_id')) {
            return 0;
        }

        return (int) DB::table($table)->where('tenant_id', $this->tenantId)->count();
    }
}
