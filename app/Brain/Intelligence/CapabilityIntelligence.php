<?php

namespace App\Brain\Intelligence;

use App\Brain\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * Capability Intelligence — skills, competencies and job-role mapping, one
 * institute at a time.
 *
 * ── WHAT ONE ROW MEANS ──────────────────────────────────────────────────────
 *
 * `s_user_jobrole` is the institute's job-role catalogue: one row per role
 * defined for a department (a role can recur across departments, so this is
 * role INSTANCES, not distinct titles — the same counting the ported Command
 * Center dashboard uses for its "Job Roles Mapped" tile). A role is "mapped"
 * when at least one row of `s_user_skill_jobrole` — the skill-to-role bridge
 * — names it.
 *
 * ── NONE OF THIS IS YEAR-SCOPED ─────────────────────────────────────────────
 *
 * The constructor takes `$syear` for the same shape every other Brain
 * Intelligence class has, but it is never used to filter a query here.
 * `s_user_jobrole`, `s_user_skill_jobrole`, `s_users_skills`,
 * `s_competency_frameworks`, `s_competency_certifications` and
 * `s_competency_development_plans` carry no `syear` column — a job role, a
 * certification or a development plan is not attached to an academic year,
 * so "this year's capability data" is not a question this domain can answer.
 * Every query below is scoped by `sub_institute_id` alone.
 *
 * ── THE COLUMN THAT DOES NOT MEAN WHAT ITS NAME SAYS ────────────────────────
 *
 * `s_user_skill_jobrole` has a column called `jobrole` and a column called
 * `skill`. Reading them by name, `sj.jobrole` should be the job-role title to
 * join against `s_user_jobrole.jobrole`. It is not: at this institute
 * `sj.jobrole` holds a short numeric code (99.99% of its rows), and it is
 * `sj.skill` that holds the job-role title — "Audit Associate / Audit
 * Assistant Associate", not a skill name. Joining on `sj.jobrole` (the column
 * name `App\Services\Competency\CommandCenterService::resolveRoleScope()`
 * uses) returns ZERO mapped roles for an institute whose skill-mapping table
 * holds 50,231 rows. Joining on `sj.skill` returns 2,875 of 2,896 roles
 * (99.3%) — the number that matches what a human reading the table would
 * call "mapped". This class joins on `sj.skill`, and every figure that
 * depends on the join says so rather than silently reproducing the zero.
 *
 * ── WHY THIS DIFFERS FROM `CommandCenterService`'S OWN CLASS DOC ────────────
 *
 * That service's doc says `s_competency_assessments` and
 * `s_competency_assessment_cycles` "do not exist in this target". They do —
 * both tables exist and are queryable — they are simply empty (0 rows) at
 * this institute. This class treats that the way every other empty table
 * here is treated: guarded by {@see SchemaCache::hasTable()}, reported as
 * zero, never assumed absent without checking.
 */
final class CapabilityIntelligence
{
    private const JOBROLE_TABLE = 's_user_jobrole';

    private const SKILL_MAPPING_TABLE = 's_user_skill_jobrole';

    private const SKILLS_TABLE = 's_users_skills';

    private const FRAMEWORKS_TABLE = 's_competency_frameworks';

    private const CERTIFICATIONS_TABLE = 's_competency_certifications';

    private const DEVELOPMENT_PLANS_TABLE = 's_competency_development_plans';

    /** A certification expiring within this many days is a work-queue item, not a future concern. */
    public const CERT_EXPIRY_WINDOW_DAYS = 30;

    /**
     * A department smaller than this cannot support a coverage comparison
     * against the institute. Departments here run from a single role to a
     * few hundred; a department of two roles at 0% mapped is not a pattern,
     * it is two roles nobody has mapped yet.
     */
    public const MIN_DEPARTMENT_COHORT = 5;

    /** Share of an institute's roles left unmapped before it is worth a finding rather than noise. */
    public const UNMAPPED_ROLE_ALERT_SHARE = 10.0;

    public const UNMAPPED_ROLE_SEVERE_SHARE = 25.0;

    private readonly string $tenantId;

    private readonly ?string $syear;

    /** @var array<string,mixed> */
    private array $memo = [];

    public function __construct(string $tenantId, ?string $syear)
    {
        $this->tenantId = (string) $tenantId;
        $this->syear = $syear === null || $syear === '' ? null : (string) $syear;
    }

    public function syear(): ?string
    {
        return $this->syear;
    }

    public function tenantId(): string
    {
        return $this->tenantId;
    }

    /* -------------------------------------------------------- L0: coverage */

    /** @return array<string,mixed> */
    public function coverage(): array
    {
        return $this->memo['coverage'] ??= $this->computeCoverage();
    }

    /** @return array<string,mixed> */
    private function computeCoverage(): array
    {
        $empty = ['sources' => [], 'counts' => []];

        if (! SchemaCache::hasTable(self::JOBROLE_TABLE)) {
            return $empty + [
                'available' => false,
                'reason' => "Table '".self::JOBROLE_TABLE."' does not exist in this deployment.",
            ];
        }

        $jobRoles = (int) $this->scoped()->count();

        if ($jobRoles === 0) {
            return $empty + [
                'available' => false,
                'reason' => 'No job roles are defined for this institute, so there is nothing for a skill, a '
                    .'framework, a certification or a development plan to be mapped against.',
            ];
        }

        $records = $this->jobRoleRecords();
        $withMapping = 0;
        $departments = [];
        foreach ($records as $record) {
            if ($record['hasSkillMapping']) {
                $withMapping++;
            }
            $departments[$record['departmentId']] = true;
        }

        $mappingRows = SchemaCache::hasTable(self::SKILL_MAPPING_TABLE)
            ? (int) $this->scopedTable(self::SKILL_MAPPING_TABLE)->count()
            : 0;

        $competencies = SchemaCache::hasTable(self::SKILLS_TABLE)
            ? (int) $this->scopedTable(self::SKILLS_TABLE)->where('approve_status', 'Approved')->count()
            : 0;

        $frameworksTotal = SchemaCache::hasTable(self::FRAMEWORKS_TABLE)
            ? (int) $this->scopedTable(self::FRAMEWORKS_TABLE)->count()
            : 0;
        $frameworksActive = SchemaCache::hasTable(self::FRAMEWORKS_TABLE)
            ? (int) $this->scopedTable(self::FRAMEWORKS_TABLE)->where('status', 'active')->count()
            : 0;

        $certificationsTotal = SchemaCache::hasTable(self::CERTIFICATIONS_TABLE)
            ? (int) $this->scopedTable(self::CERTIFICATIONS_TABLE)->count()
            : 0;
        $certificationsValid = SchemaCache::hasTable(self::CERTIFICATIONS_TABLE)
            ? (int) $this->scopedTable(self::CERTIFICATIONS_TABLE)->where('status', 'valid')->count()
            : 0;

        $plansTotal = SchemaCache::hasTable(self::DEVELOPMENT_PLANS_TABLE)
            ? (int) $this->scopedTable(self::DEVELOPMENT_PLANS_TABLE)->count()
            : 0;
        $plansActive = SchemaCache::hasTable(self::DEVELOPMENT_PLANS_TABLE)
            ? (int) $this->scopedTable(self::DEVELOPMENT_PLANS_TABLE)->where('status', 'active')->count()
            : 0;

        return [
            'available' => true,
            'reason' => null,
            'sources' => [
                'jobRoles' => true,
                'skillMapping' => $withMapping > 0,
                'competencies' => $competencies > 0,
                'frameworks' => $frameworksTotal > 0,
                'certifications' => $certificationsTotal > 0,
                'developmentPlans' => $plansTotal > 0,
            ],
            'counts' => [
                'jobRoles' => $jobRoles,
                'jobRolesWithSkillMapping' => $withMapping,
                'skillMappingRows' => $mappingRows,
                'departments' => count($departments),
                'competencies' => $competencies,
                'frameworksTotal' => $frameworksTotal,
                'frameworksActive' => $frameworksActive,
                'certificationsTotal' => $certificationsTotal,
                'certificationsValid' => $certificationsValid,
                'developmentPlansTotal' => $plansTotal,
                'developmentPlansActive' => $plansActive,
            ],
        ];
    }

    /* -------------------------------------------------------- L1: position */

    /** @return array<string,mixed>|null */
    public function position(): ?array
    {
        return $this->memo['position'] ??= $this->computePosition();
    }

    /** @return array<string,mixed>|null */
    private function computePosition(): ?array
    {
        $coverage = $this->coverage();
        if (! $coverage['available']) {
            return null;
        }

        $counts = $coverage['counts'];
        $jobRoles = (int) $counts['jobRoles'];
        $withMapping = (int) $counts['jobRolesWithSkillMapping'];

        return [
            'jobRoles' => $jobRoles,
            'jobRolesWithSkillMapping' => $withMapping,
            'jobRolesWithoutSkillMapping' => $jobRoles - $withMapping,
            'jobRoleSkillCoverage' => round($withMapping / $jobRoles * 100, 1),
            'departments' => (int) $counts['departments'],
            'competencies' => (int) $counts['competencies'],
            'activeFrameworks' => (int) $counts['frameworksActive'],
            'totalFrameworks' => (int) $counts['frameworksTotal'],
            'certifications' => (int) $counts['certificationsValid'],
            'certificationsTotal' => (int) $counts['certificationsTotal'],
            'developmentPlans' => (int) $counts['developmentPlansActive'],
            'developmentPlansTotal' => (int) $counts['developmentPlansTotal'],
        ];
    }

    /* ---------------------------------------------------- L2: distribution */

    /**
     * Skill-mapping coverage by department, weakest first.
     *
     * @return array<int,array<string,mixed>>
     */
    public function byDepartment(): array
    {
        return $this->memo['byDepartment'] ??= $this->computeByDepartment();
    }

    /** @return array<int,array<string,mixed>> */
    private function computeByDepartment(): array
    {
        if (! $this->coverage()['available']) {
            return [];
        }

        $grouped = [];
        foreach ($this->jobRoleRecords() as $record) {
            $key = (string) $record['departmentId'];
            $grouped[$key] ??= [
                'key' => $key,
                'label' => $record['departmentName'],
                'jobRoles' => 0,
                'withMapping' => 0,
            ];

            $grouped[$key]['jobRoles']++;
            if ($record['hasSkillMapping']) {
                $grouped[$key]['withMapping']++;
            }
        }

        $out = [];
        foreach ($grouped as $group) {
            $out[] = [
                'key' => $group['key'],
                'label' => $group['label'],
                'jobRoles' => $group['jobRoles'],
                'jobRolesWithSkillMapping' => $group['withMapping'],
                'jobRolesWithoutSkillMapping' => $group['jobRoles'] - $group['withMapping'],
                'skillCoverage' => round($group['withMapping'] / $group['jobRoles'] * 100, 1),
            ];
        }

        // Weakest coverage first — this table exists to show where mapping has not been done.
        usort($out, static fn ($a, $b) => $a['skillCoverage'] <=> $b['skillCoverage']);

        return $out;
    }

    /* ------------------------------------------------------------- the DQ ledger */

    /** @return array<string,mixed> */
    public function dataQuality(): array
    {
        return $this->memo['dataQuality'] ??= $this->computeDataQuality();
    }

    /** @return array<string,mixed> */
    private function computeDataQuality(): array
    {
        $coverage = $this->coverage();
        if (! $coverage['available']) {
            return ['available' => false, 'reason' => $coverage['reason'], 'checks' => []];
        }

        $counts = $coverage['counts'];
        $jobRoles = (int) $counts['jobRoles'];
        $unmapped = $jobRoles - (int) $counts['jobRolesWithSkillMapping'];

        $noDepartment = (int) $this->scoped()
            ->where(fn ($q) => $q->whereNull('department_id')->orWhere('department_id', '<=', 0))
            ->count();

        $notApproved = SchemaCache::hasTable(self::SKILLS_TABLE)
            ? (int) $this->scopedTable(self::SKILLS_TABLE)->where(fn ($q) => $q->whereNull('approve_status')
                ->orWhere('approve_status', '!=', 'Approved'))->count()
            : 0;

        [$expiredButActive, $expiringSoon] = $this->certificationWindowCounts();
        $overduePlans = $this->overdueDevelopmentPlans();

        return [
            'available' => true,
            'reason' => null,
            'checks' => [
                [
                    'key' => 'jobroles_without_skill_mapping',
                    'label' => 'Job roles with no skill mapped to them',
                    'value' => $unmapped,
                    'format' => 'count',
                    'sharePercent' => $jobRoles > 0 ? round($unmapped / $jobRoles * 100, 2) : null,
                    'shareLabel' => 'of job roles',
                    'state' => $unmapped > 0 ? 'attention' : 'ok',
                    'note' => $unmapped > 0
                        ? 'These roles have no row in the skill-mapping table, so nobody can be assessed against them '
                            .'and no development plan can cite a required skill for them.'
                        : 'Every job role has at least one skill mapped to it.',
                ],
                [
                    'key' => 'jobroles_without_department',
                    'label' => 'Job roles with no department',
                    'value' => $noDepartment,
                    'format' => 'count',
                    'sharePercent' => $jobRoles > 0 ? round($noDepartment / $jobRoles * 100, 2) : null,
                    'shareLabel' => 'of job roles',
                    'state' => $noDepartment > 0 ? 'attention' : 'ok',
                    'note' => $noDepartment > 0
                        ? 'These roles count towards the institute figures but appear in no department in the '
                            .'breakdown below.'
                        : 'Every job role is assigned to a department.',
                ],
                [
                    'key' => 'competencies_not_approved',
                    'label' => 'Competencies awaiting or missing approval',
                    'value' => $notApproved,
                    'format' => 'count',
                    'sharePercent' => null,
                    'shareLabel' => null,
                    'state' => $notApproved > 0 ? 'attention' : 'ok',
                    'note' => $notApproved > 0
                        ? 'These are excluded from the published-competency count until approved.'
                        : 'Every competency on file has been approved.',
                ],
                [
                    'key' => 'certifications_expired_but_marked_valid',
                    'label' => 'Certifications past their expiry date but still marked valid',
                    'value' => $expiredButActive,
                    'format' => 'count',
                    'sharePercent' => null,
                    'shareLabel' => null,
                    'state' => $expiredButActive > 0 ? 'attention' : 'ok',
                    'note' => $expiredButActive > 0
                        ? 'Nobody has moved these to an expired status since the date passed, so a compliance view '
                            .'reading only the status column would call them current.'
                        : 'No certification marked valid or expiring has a past expiry date.',
                ],
                [
                    'key' => 'certifications_expiring_soon',
                    'label' => 'Certifications expiring within '.self::CERT_EXPIRY_WINDOW_DAYS.' days',
                    'value' => $expiringSoon,
                    'format' => 'count',
                    'sharePercent' => null,
                    'shareLabel' => null,
                    'state' => $expiringSoon > 0 ? 'attention' : 'ok',
                    'note' => $expiringSoon > 0
                        ? 'These need a renewal started before they lapse.'
                        : 'No certification is due to expire in the next '.self::CERT_EXPIRY_WINDOW_DAYS.' days.',
                ],
                [
                    'key' => 'development_plans_overdue',
                    'label' => 'Development plans overdue',
                    'value' => $overduePlans,
                    'format' => 'count',
                    'sharePercent' => null,
                    'shareLabel' => null,
                    'state' => $overduePlans > 0 ? 'attention' : 'ok',
                    'note' => $overduePlans > 0
                        ? 'Explicitly marked overdue, or past their due date and not completed.'
                        : 'No open development plan is past its due date.',
                ],
            ],
        ];
    }

    /* ------------------------------------------------------------ helpers */

    /**
     * One entry per job-role row, with department and skill-mapping status
     * resolved once so {@see coverage()}, {@see position()} and
     * {@see byDepartment()} agree with each other and each pay this query's
     * cost only once per request.
     *
     * THE JOIN IS ON `sj.skill`, NOT `sj.jobrole` — see the class doc for
     * why the column named `jobrole` is not the one that holds a job-role
     * name at this institute.
     *
     * @return array<int,array<string,mixed>>
     */
    private function jobRoleRecords(): array
    {
        return $this->memo['jobRoleRecords'] ??= $this->fetchJobRoleRecords();
    }

    /** @return array<int,array<string,mixed>> */
    private function fetchJobRoleRecords(): array
    {
        $hasMappingTable = SchemaCache::hasTable(self::SKILL_MAPPING_TABLE);

        $query = DB::table(self::JOBROLE_TABLE.' as jr')
            ->where('jr.sub_institute_id', $this->tenantId)
            ->whereNull('jr.deleted_at');

        $query->select([
            'jr.id',
            'jr.jobrole',
            'jr.department_id',
            DB::raw('COALESCE(NULLIF(jr.department, \'\'), \'Unassigned\') as department_name'),
        ]);

        if ($hasMappingTable) {
            $query->selectRaw(
                'EXISTS (
                    SELECT 1 FROM '.self::SKILL_MAPPING_TABLE.' AS sj
                    WHERE sj.sub_institute_id = jr.sub_institute_id
                      AND sj.skill = jr.jobrole
                      AND sj.deleted_at IS NULL
                ) as has_skill_mapping'
            );
        }

        $rows = $query->get();

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (string) $row->id,
                'jobrole' => (string) $row->jobrole,
                'departmentId' => (string) ($row->department_id ?? '0'),
                'departmentName' => (string) $row->department_name,
                'hasSkillMapping' => $hasMappingTable && (bool) $row->has_skill_mapping,
            ];
        }

        return $out;
    }

    /**
     * Certifications past expiry while still carrying an active status, and
     * certifications genuinely due within the alert window. Reuses the same
     * status/date logic as `CommandCenterService::workQueues()`'s "Expiring
     * Certifications" queue, split into "already past due" and "still ahead
     * of it" — the two are different facts and the first is worse.
     *
     * @return array{0:int,1:int}
     */
    private function certificationWindowCounts(): array
    {
        if (! SchemaCache::hasTable(self::CERTIFICATIONS_TABLE)) {
            return [0, 0];
        }

        $today = now()->toDateString();
        $windowEnd = now()->addDays(self::CERT_EXPIRY_WINDOW_DAYS)->toDateString();

        $expiredButActive = (int) $this->scopedTable(self::CERTIFICATIONS_TABLE)
            ->whereIn('status', ['valid', 'expiring'])
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', $today)
            ->count();

        $expiringSoon = (int) $this->scopedTable(self::CERTIFICATIONS_TABLE)
            ->whereIn('status', ['valid', 'expiring'])
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [$today, $windowEnd])
            ->count();

        return [$expiredButActive, $expiringSoon];
    }

    /**
     * Development plans explicitly marked overdue, plus plans whose due date
     * has passed without being completed. Same logic as
     * `CommandCenterService::workQueues()`'s "Overdue Development Plans" queue.
     */
    private function overdueDevelopmentPlans(): int
    {
        if (! SchemaCache::hasTable(self::DEVELOPMENT_PLANS_TABLE)) {
            return 0;
        }

        $today = now()->toDateString();

        return (int) $this->scopedTable(self::DEVELOPMENT_PLANS_TABLE)
            ->where(function ($q) use ($today) {
                $q->where('status', 'overdue')
                    ->orWhere(function ($q2) use ($today) {
                        $q2->whereNotNull('due_date')
                            ->where('due_date', '<', $today)
                            ->whereNotIn('status', ['completed']);
                    });
            })
            ->count();
    }

    /** Every job-role-anchored query starts here, so no figure can escape the tenant filter. */
    private function scoped(): \Illuminate\Database\Query\Builder
    {
        return DB::table(self::JOBROLE_TABLE)
            ->where('sub_institute_id', $this->tenantId)
            ->whereNull('deleted_at');
    }

    /** For every other competency-domain table, tenant-scoped the same way. */
    private function scopedTable(string $table): \Illuminate\Database\Query\Builder
    {
        return DB::table($table)
            ->where('sub_institute_id', $this->tenantId)
            ->whereNull('deleted_at');
    }
}
