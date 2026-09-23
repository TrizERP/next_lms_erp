<?php

namespace App\Brain\Intelligence;

use App\Brain\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * Organization Intelligence — one institute's staff body, read live.
 *
 * ── WHAT ONE ROW MEANS ──────────────────────────────────────────────────────
 *
 * One row of `tbluser` is ONE MEMBER OF STAFF. `terminated_date` decides
 * active vs. left — NOT `status` — because that is the column the Employee
 * Directory's own KPI, growth, department-distribution and attrition
 * endpoints already key every figure off (`app/Http/Controllers/api/
 * OrganizationManagement/EmployeeDirectory/EmployeeDirectoryAnalyticsController.php`).
 * `HrIntelligence`, this class's neighbour, reads the same table through
 * `status = 1` for a different purpose (staff attendance and leave); the two
 * conventions are not reconciled here on purpose, because this class exists
 * to sit behind the endpoint that already ships and answers a different
 * question — headcount, hiring and attrition, not who is clocked in today.
 *
 * ── WHY THIS CLASS DOES NOT FILTER BY ACADEMIC YEAR ─────────────────────────
 *
 * `tbluser`, `hrms_departments`, `talent_job_postings`, `talent_job_applications`,
 * `s_users_skills` and `s_skill_matrix` carry no `syear` column between them.
 * The Employee Directory analytics endpoint this class ports its logic from
 * never filters by one either — it measures hiring, attrition and growth over
 * rolling day windows anchored on `now()`, because a staff record does not
 * reset at the start of a school year the way an admission or a result does.
 * The constructor still accepts `$syear`, to match every other Intelligence
 * class's contract, and `syear()` returns it — but no query below is scoped
 * by it, and the class docblock says so rather than leaving that silent.
 *
 * ── WHAT WAS DELIBERATELY NOT PORTED ────────────────────────────────────────
 *
 * `EmployeeDirectoryAnalyticsController::getKPIs()` reports
 * `avgSkillCoverageTrend` as `rand(1, 5)` and `avgProficiencyDeltaTrend` from
 * a hardcoded `$avgProficiency - 0.12` offset, both commented in the source
 * as placeholders standing in for a historical snapshot table that does not
 * exist. Neither trend is reproduced here: this module's contract is that
 * every number on the screen is a real query, and a random integer or a
 * hardcoded constant is not one. `avgSkillCoverage` and `avgProficiency`
 * themselves ARE real aggregates and are kept; only their fabricated
 * trend figures are dropped.
 *
 * ── WHY THE SKILL FILTER IS `status != 'Inactive'`, NOT `status = 'Active'` ──
 *
 * `s_users_skills.status` is documented on its own migration
 * (`2026_08_19_090000_create_s_users_skills_table.php`, rule L-05) as
 * nullable-meaning-unmarked, never nullable-meaning-inactive: "filter with
 * status != 'Inactive', NEVER status = 'Active'". The source KPI endpoint
 * uses `where('status', 'Active')` regardless, which silently drops every
 * unmarked row. This class follows the table's own documented convention
 * instead, because a required-skill row nobody has classified is not the
 * same fact as a skill someone marked inactive.
 */
final class OrganizationIntelligence
{
    private const STAFF_TABLE = 'tbluser';

    private const DEPARTMENT_TABLE = 'hrms_departments';

    private const JOB_POSTING_TABLE = 'talent_job_postings';

    private const JOB_APPLICATION_TABLE = 'talent_job_applications';

    private const SKILL_REQUIREMENT_TABLE = 's_users_skills';

    private const SKILL_MATRIX_TABLE = 's_skill_matrix';

    /**
     * The rolling window new hires, attrition and growth are measured over.
     * Matches `EmployeeDirectoryAnalyticsController::getKPIs()` exactly, so a
     * figure on this screen and the Employee Directory's own KPI cards agree.
     */
    public const RECENT_WINDOW_DAYS = 30;

    /** A department smaller than this cannot support a skill-coverage or attrition comparison against the institute. */
    public const MIN_DEPARTMENT_COHORT = 5;

    /** Below this many recent exits, a department's attrition trend is noise rather than a pattern. */
    public const MIN_ATTRITION_COHORT = 3;

    private readonly string $tenantId;

    private readonly ?string $syear;

    /** @var array<string,mixed> */
    private array $memo = [];

    public function __construct(string $tenantId, ?string $syear)
    {
        $this->tenantId = (string) $tenantId;
        $this->syear = $syear === null || $syear === '' ? null : (string) $syear;
    }

    /** Carried for contract parity with every other Intelligence class. Not used to scope any query — see the class docblock. */
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

        if (! SchemaCache::hasTable(self::STAFF_TABLE)) {
            return $empty + [
                'available' => false,
                'reason' => "Table '".self::STAFF_TABLE."' does not exist in this deployment.",
            ];
        }

        $shape = $this->scoped()
            ->selectRaw(
                'COUNT(*) as staff,
                 SUM(CASE WHEN terminated_date IS NULL THEN 1 ELSE 0 END) as active,
                 COUNT(DISTINCT NULLIF(department_id, 0)) as departments_with_staff'
            )
            ->first();

        $staff = (int) ($shape->staff ?? 0);

        if ($staff === 0) {
            return $empty + [
                'available' => false,
                'reason' => 'No staff records exist for this institute.',
            ];
        }

        $departmentRows = SchemaCache::hasTable(self::DEPARTMENT_TABLE)
            ? (int) DB::table(self::DEPARTMENT_TABLE)->where('sub_institute_id', $this->tenantId)->count()
            : 0;

        $hasPostings = SchemaCache::hasTable(self::JOB_POSTING_TABLE);
        $hasApplications = SchemaCache::hasTable(self::JOB_APPLICATION_TABLE);
        $hasSkillRequirements = SchemaCache::hasTable(self::SKILL_REQUIREMENT_TABLE);
        $hasSkillMatrix = SchemaCache::hasTable(self::SKILL_MATRIX_TABLE);

        return [
            'available' => true,
            'reason' => null,
            'sources' => [
                'staff' => true,
                'departmentMaster' => $departmentRows > 0,
                'recruitmentPipeline' => $hasPostings && $hasApplications,
                'skillRequirements' => $hasSkillRequirements,
                'skillMatrix' => $hasSkillMatrix,
            ],
            'counts' => [
                'staff' => $staff,
                'activeStaff' => (int) $shape->active,
                'departmentsWithStaff' => (int) $shape->departments_with_staff,
                'departmentRows' => $departmentRows,
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

        $now = now();
        $currentStart = (clone $now)->subDays(self::RECENT_WINDOW_DAYS);
        $previousStart = (clone $now)->subDays(self::RECENT_WINDOW_DAYS * 2);
        $previousEnd = $currentStart;

        $active = (int) $coverage['counts']['activeStaff'];

        // Staff who are active today and joined before the previous window
        // closed. Like the source KPI endpoint, this reads "active AND joined
        // early enough" rather than reconstructing the headcount that existed
        // at that date — someone who joined earlier and has since left is not
        // counted, so this is an approximation of the prior headcount, not an
        // exact one. Kept because it is the same approximation the shipped
        // Employee Directory screen already shows.
        $previousActive = (int) $this->scoped()
            ->whereNull('terminated_date')
            ->where('joined_date', '<', $previousEnd)
            ->count();

        $activeTrend = $this->trend($active, $previousActive);

        [$newHires, $previousHires] = $this->hiresInWindows($currentStart, $previousStart, $previousEnd);
        $hiresTrend = $this->trend($newHires, $previousHires);

        [$attrition, $previousAttrition] = $this->attritionInWindows($currentStart, $previousStart, $previousEnd);
        $attritionRate = $active > 0 ? round($attrition / $active * 100, 2) : null;
        $attritionTrend = $this->trend($attrition, $previousAttrition);

        $netGrowth = $newHires - $attrition;
        $previousNetGrowth = $previousHires - $previousAttrition;
        $growthRate = $active > 0 ? round($netGrowth / $active * 100, 2) : null;
        $growthTrend = $this->trend($netGrowth, $previousNetGrowth);

        $skills = $this->skillCoverageProfile();

        return [
            'activeStaff' => $active,
            'previousActiveStaff' => $previousActive,
            'activeStaffTrend' => $activeTrend,
            'newHires' => $newHires,
            'previousNewHires' => $previousHires,
            'newHiresTrend' => $hiresTrend,
            'attritionCount' => $attrition,
            'previousAttritionCount' => $previousAttrition,
            // NULL, NOT ZERO: with no active staff there is no denominator.
            'attritionRate' => $attritionRate,
            'attritionTrend' => $attritionTrend,
            'netGrowth' => $netGrowth,
            'growthRate' => $growthRate,
            'growthTrend' => $growthTrend,
            'departments' => (int) $coverage['counts']['departmentRows'],
            'departmentsWithStaff' => (int) $coverage['counts']['departmentsWithStaff'],
            'requiredSkills' => $skills['required'],
            'coveredSkills' => $skills['covered'],
            // NULL where the institute keeps no skill-requirement register at
            // all, not zero coverage of a register that does not exist.
            'avgSkillCoverage' => $skills['coverage'],
            'avgProficiency' => $skills['avgProficiency'],
        ];
    }

    /** @return array{0:int,1:int} [current, previous] */
    private function hiresInWindows(\DateTimeInterface $currentStart, \DateTimeInterface $previousStart, \DateTimeInterface $previousEnd): array
    {
        if (! SchemaCache::hasTable(self::JOB_APPLICATION_TABLE)) {
            return [0, 0];
        }

        $current = (int) DB::table(self::JOB_APPLICATION_TABLE)
            ->where('sub_institute_id', $this->tenantId)
            ->where('status', 'hired')
            ->where('updated_at', '>=', $currentStart)
            ->count();

        $previous = (int) DB::table(self::JOB_APPLICATION_TABLE)
            ->where('sub_institute_id', $this->tenantId)
            ->where('status', 'hired')
            ->whereBetween('updated_at', [$previousStart, $previousEnd])
            ->count();

        return [$current, $previous];
    }

    /** @return array{0:int,1:int} [current, previous] */
    private function attritionInWindows(\DateTimeInterface $currentStart, \DateTimeInterface $previousStart, \DateTimeInterface $previousEnd): array
    {
        $current = (int) $this->scoped()
            ->whereNotNull('terminated_date')
            ->where('terminated_date', '>=', $currentStart)
            ->count();

        $previous = (int) $this->scoped()
            ->whereNotNull('terminated_date')
            ->whereBetween('terminated_date', [$previousStart, $previousEnd])
            ->count();

        return [$current, $previous];
    }

    /** @return array{required:?int,covered:?int,coverage:?float,avgProficiency:?float} */
    private function skillCoverageProfile(): array
    {
        return $this->memo['skillCoverage'] ??= (function (): array {
            $none = ['required' => null, 'covered' => null, 'coverage' => null, 'avgProficiency' => null];

            if (! SchemaCache::hasTable(self::SKILL_REQUIREMENT_TABLE)) {
                return $none;
            }

            // status != 'Inactive' rather than status = 'Active' — see the
            // class docblock. NULL (unmarked) counts as required; explicitly
            // 'Inactive' does not.
            $required = (int) DB::table(self::SKILL_REQUIREMENT_TABLE)
                ->where('sub_institute_id', $this->tenantId)
                ->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'Inactive'))
                ->count();

            if ($required === 0 || ! SchemaCache::hasTable(self::SKILL_MATRIX_TABLE)) {
                return ['required' => $required > 0 ? $required : null, 'covered' => null, 'coverage' => null, 'avgProficiency' => null];
            }

            $covered = (int) DB::table(self::SKILL_MATRIX_TABLE.' as sm')
                ->join(self::STAFF_TABLE.' as u', 'sm.user_id', '=', 'u.id')
                ->where('u.sub_institute_id', $this->tenantId)
                ->whereNull('u.terminated_date')
                ->distinct()
                ->count('sm.skill_id');

            $avgProficiency = DB::table(self::SKILL_MATRIX_TABLE.' as sm')
                ->join(self::STAFF_TABLE.' as u', 'sm.user_id', '=', 'u.id')
                ->where('u.sub_institute_id', $this->tenantId)
                ->whereNull('u.terminated_date')
                ->avg('sm.skill_level');

            return [
                'required' => $required,
                'covered' => $covered,
                'coverage' => round($covered / $required * 100, 1),
                'avgProficiency' => $avgProficiency !== null ? round((float) $avgProficiency, 2) : null,
            ];
        })();
    }

    /* ---------------------------------------------------- L2: distribution */

    /**
     * Headcount and attrition per department, in one row per department so a
     * reader never has to reconcile two tables by name.
     *
     * `activeStaff` mirrors `getDepartmentDistribution()`; `exits`/
     * `attritionRate` mirror `getAttritionBreakdown()` — both computed here
     * over the SAME grouped query rather than two round trips.
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
        if (! $this->coverage()['available'] || ! SchemaCache::hasTable(self::DEPARTMENT_TABLE)) {
            return [];
        }

        $rows = DB::table(self::DEPARTMENT_TABLE.' as d')
            ->leftJoin(self::STAFF_TABLE.' as u', function ($join) {
                $join->on('u.department_id', '=', 'd.id')
                    ->where('u.sub_institute_id', $this->tenantId);
            })
            ->where('d.sub_institute_id', $this->tenantId)
            ->groupBy('d.id', 'd.department')
            ->select(
                'd.id',
                'd.department',
                DB::raw('COUNT(CASE WHEN u.terminated_date IS NULL THEN u.id END) as active_staff'),
                DB::raw('COUNT(u.id) as all_staff'),
                DB::raw('SUM(CASE WHEN u.terminated_date IS NOT NULL THEN 1 ELSE 0 END) as exits')
            )
            ->orderByDesc('active_staff')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $allStaff = (int) $row->all_staff;
            $exits = (int) $row->exits;

            $out[] = [
                'key' => (string) $row->id,
                'label' => (string) $row->department,
                'activeStaff' => (int) $row->active_staff,
                'allStaff' => $allStaff,
                'exits' => $exits,
                // NULL, NOT ZERO: a department that has never held a staff
                // record has no attrition rate, not a rate of nought.
                'attritionRate' => $allStaff > 0 ? round($exits / $allStaff * 100, 1) : null,
            ];
        }

        return $out;
    }

    /**
     * Skill coverage per department: how many of the skills marked required
     * for the department are held by at least one of its active staff.
     *
     * Departments with no required-skill rows are omitted — a coverage figure
     * over zero requirements is undefined, not zero. Small departments are
     * NOT filtered out here; {@see MIN_DEPARTMENT_COHORT} is applied by the
     * signal rules that compare a department against the institute, the same
     * split `AttendanceIntelligence::byStandard()` uses.
     *
     * @return array<int,array<string,mixed>>
     */
    public function skillCoverageByDepartment(): array
    {
        return $this->memo['skillCoverageByDepartment'] ??= $this->computeSkillCoverageByDepartment();
    }

    /** @return array<int,array<string,mixed>> */
    private function computeSkillCoverageByDepartment(): array
    {
        if (! $this->coverage()['available']
            || ! SchemaCache::hasTable(self::DEPARTMENT_TABLE)
            || ! SchemaCache::hasTable(self::SKILL_REQUIREMENT_TABLE)
            || ! SchemaCache::hasTable(self::SKILL_MATRIX_TABLE)) {
            return [];
        }

        $departments = DB::table(self::DEPARTMENT_TABLE)
            ->where('sub_institute_id', $this->tenantId)
            ->get(['id', 'department']);

        $out = [];
        foreach ($departments as $department) {
            $required = (int) DB::table(self::SKILL_REQUIREMENT_TABLE)
                ->where('sub_institute_id', $this->tenantId)
                ->where('department_id', $department->id)
                ->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'Inactive'))
                ->count();

            if ($required === 0) {
                continue;
            }

            $headcount = (int) DB::table(self::STAFF_TABLE)
                ->where('department_id', $department->id)
                ->where('sub_institute_id', $this->tenantId)
                ->whereNull('terminated_date')
                ->count();

            $covered = (int) DB::table(self::SKILL_MATRIX_TABLE.' as sm')
                ->join(self::STAFF_TABLE.' as u', 'sm.user_id', '=', 'u.id')
                ->where('u.department_id', $department->id)
                ->where('u.sub_institute_id', $this->tenantId)
                ->whereNull('u.terminated_date')
                ->distinct()
                ->count('sm.skill_id');

            $out[] = [
                'key' => (string) $department->id,
                'label' => (string) $department->department,
                'headcount' => $headcount,
                'requiredSkills' => $required,
                'coveredSkills' => $covered,
                'coverage' => round($covered / $required * 100, 1),
            ];
        }

        // Weakest coverage first — this table is read to find where to look.
        usort($out, static fn ($a, $b) => $a['coverage'] <=> $b['coverage']);

        return $out;
    }

    /**
     * Open job postings whose deadline has passed with zero hires recorded
     * against them — a real, queryable proxy for "this role has been open
     * and nobody has filled it", grouped by the department the posting
     * belongs to.
     *
     * @return array<int,array<string,mixed>>
     */
    public function stalledPostings(): array
    {
        return $this->memo['stalledPostings'] ??= $this->computeStalledPostings();
    }

    /** @return array<int,array<string,mixed>> */
    private function computeStalledPostings(): array
    {
        if (! SchemaCache::hasTable(self::JOB_POSTING_TABLE) || ! SchemaCache::hasTable(self::JOB_APPLICATION_TABLE)) {
            return [];
        }

        $rows = DB::table(self::JOB_POSTING_TABLE.' as jp')
            ->leftJoin(self::DEPARTMENT_TABLE.' as d', 'd.id', '=', 'jp.department_id')
            ->leftJoin(self::JOB_APPLICATION_TABLE.' as tja', function ($join) {
                $join->on('tja.job_id', '=', 'jp.id')
                    ->where('tja.status', '=', 'hired');
            })
            ->where('jp.sub_institute_id', $this->tenantId)
            ->whereNotIn('jp.status', ['closed', 'filled', 'inactive'])
            ->whereNotNull('jp.deadline')
            ->where('jp.deadline', '<', now())
            ->groupBy('jp.id', 'jp.title', 'jp.department_id', 'd.department', 'jp.deadline', 'jp.positions')
            ->select(
                'jp.id',
                'jp.title',
                'jp.department_id',
                DB::raw('COALESCE(NULLIF(d.department, ""), "Unassigned") as department_name'),
                'jp.deadline',
                'jp.positions',
                DB::raw('COUNT(tja.id) as hires')
            )
            ->having('hires', '=', 0)
            ->get();

        $now = now();

        return $rows->map(fn ($row) => [
            'key' => (string) $row->id,
            'title' => (string) $row->title,
            'departmentKey' => $row->department_id !== null ? (string) $row->department_id : null,
            'departmentLabel' => (string) $row->department_name,
            'deadline' => (string) $row->deadline,
            'positions' => $row->positions !== null ? (int) $row->positions : null,
            'daysOverdue' => (int) $now->diffInDays($row->deadline),
        ])->all();
    }

    /* ------------------------------------------------------- the DQ ledger */

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

        $active = (int) $coverage['counts']['activeStaff'];

        $noDepartment = (int) $this->scoped()
            ->whereNull('terminated_date')
            ->where(fn ($q) => $q->whereNull('department_id')->orWhere('department_id', '<=', 0))
            ->count();

        $noJoiningDate = (int) $this->scoped()
            ->whereNull('terminated_date')
            ->where(fn ($q) => $q->whereNull('joined_date')->orWhere('joined_date', '0000-00-00'))
            ->count();

        $departmentsWithNoStaff = count(array_filter($this->byDepartment(), static fn ($d) => $d['activeStaff'] === 0));

        $checks = [
            [
                'key' => 'active_staff_no_department',
                'label' => 'Active staff with no department assigned',
                'value' => $noDepartment,
                'format' => 'count',
                'sharePercent' => $active > 0 ? round($noDepartment / $active * 100, 2) : null,
                'shareLabel' => 'of active staff',
                'state' => $noDepartment > 0 ? 'attention' : 'ok',
                'note' => $noDepartment > 0
                    ? 'These staff are excluded from the department breakdown and from every department-level skill '
                        .'coverage figure, because there is no department to attribute them to.'
                    : 'Every active member of staff is assigned to a department.',
            ],
            [
                'key' => 'active_staff_no_joining_date',
                'label' => 'Active staff with no joining date',
                'value' => $noJoiningDate,
                'format' => 'count',
                'sharePercent' => $active > 0 ? round($noJoiningDate / $active * 100, 2) : null,
                'shareLabel' => 'of active staff',
                'state' => $noJoiningDate > 0 ? 'attention' : 'ok',
                'note' => $noJoiningDate > 0
                    ? 'The prior-period headcount used for every trend figure on this screen is read from joining '
                        .'dates, so these staff are excluded from it.'
                    : 'Every active member of staff has a joining date on file.',
            ],
            [
                'key' => 'departments_no_active_staff',
                'label' => 'Departments in the master with no active staff',
                'value' => $departmentsWithNoStaff,
                'format' => 'count',
                'sharePercent' => null,
                'state' => $departmentsWithNoStaff > 0 ? 'attention' : 'ok',
                'note' => $departmentsWithNoStaff > 0
                    ? 'These departments exist in the department master but hold nobody today — either a department '
                        .'nobody has staffed yet or one whose staff have all been reassigned elsewhere.'
                    : 'Every department in the master holds at least one active member of staff.',
            ],
            ...$this->skillDataQualityChecks(),
        ];

        return [
            'available' => true,
            'reason' => null,
            'checks' => $checks,
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private function skillDataQualityChecks(): array
    {
        if (! SchemaCache::hasTable(self::SKILL_MATRIX_TABLE)) {
            return [];
        }

        $active = (int) $this->coverage()['counts']['activeStaff'];

        $withSkillEntry = (int) DB::table(self::SKILL_MATRIX_TABLE.' as sm')
            ->join(self::STAFF_TABLE.' as u', 'sm.user_id', '=', 'u.id')
            ->where('u.sub_institute_id', $this->tenantId)
            ->whereNull('u.terminated_date')
            ->distinct()
            ->count('sm.user_id');

        $noSkillEntry = max(0, $active - $withSkillEntry);

        return [[
            'key' => 'active_staff_no_skill_profile',
            'label' => 'Active staff with no entry in the skill matrix',
            'value' => $noSkillEntry,
            'format' => 'count',
            'sharePercent' => $active > 0 ? round($noSkillEntry / $active * 100, 2) : null,
            'shareLabel' => 'of active staff',
            'state' => $noSkillEntry > 0 ? 'attention' : 'ok',
            'note' => $noSkillEntry > 0
                ? 'These staff hold no row in the skill matrix at all, so they cannot contribute to any department’s '
                    .'skill-coverage figure even where they hold the skill in practice.'
                : 'Every active member of staff has at least one entry in the skill matrix.',
        ]];
    }

    /* ------------------------------------------------------------ helpers */

    /** Percentage change from $previous to $current, matching the Employee Directory KPI endpoint's own rule. */
    private function trend(int|float $current, int|float $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }

    /** Every query in this class starts here, so no figure can escape the tenant filter. */
    private function scoped(): \Illuminate\Database\Query\Builder
    {
        return DB::table(self::STAFF_TABLE)
            ->where('sub_institute_id', $this->tenantId);
    }
}
