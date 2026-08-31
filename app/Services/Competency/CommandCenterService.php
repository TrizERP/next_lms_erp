<?php

namespace App\Services\Competency;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Ported from G2G's `App\Services\Competency\CommandCenterService`.
 *
 * Aggregates every metric the Competency Command Center dashboard renders:
 * the six summary tiles, four progress rings, five work queues, the recent
 * activity feed and the assessment-calendar bar.
 *
 * All queries are tenant scoped by sub_institute_id and honour the five
 * command-center filters (Department, Job Role, Location, Business Unit,
 * Job Family). The role-based filters are resolved against s_user_jobrole into
 * a set of matching job roles, which then constrains every competency-domain
 * table (they carry a `jobrole` column).
 *
 * DELIBERATE DEGRADATION, DOCUMENTED PER-METRIC BELOW: `s_competency_assessments`
 * and `s_competency_assessment_cycles` do not exist in this target - assessment
 * cycles were explicitly out of scope for this Competency Management port (see
 * `routes/competency_management.php`'s class doc). Every metric that would read
 * them degrades gracefully to 0 / an empty slice rather than throwing or
 * blocking the rest of the dashboard - the Command Center screen was not part
 * of the original port scope either (see `CompetencyCommandCenterController`'s
 * class doc: only its `filters()` endpoint was ported before this pass), so
 * this is new surface area, not a regression against something that worked.
 */
class CommandCenterService
{
    /** Build the full dashboard payload. */
    public function dashboard(int $subInstituteId, ?int $userId, array $filters): array
    {
        [$totalRoles, $rolesWithSkills, $scopeRoles] = $this->resolveRoleScope($subInstituteId, $filters);

        return [
            'summary'             => $this->summary($subInstituteId, $filters, $scopeRoles, $totalRoles, $rolesWithSkills),
            'progress'            => $this->progress($subInstituteId, $scopeRoles, $totalRoles, $rolesWithSkills),
            'work_queues'         => $this->workQueues($subInstituteId, $userId, $scopeRoles),
            'recent_activity'     => $this->recentActivity($subInstituteId),
            'assessment_calendar' => $this->assessmentCalendar($subInstituteId, $scopeRoles),
        ];
    }

    /* ----------------------------------------------------------------- *
     * Role scope + coverage KPIs (from the existing Skill/Role tables)
     * ----------------------------------------------------------------- */

    /**
     * @return array{0:int,1:int,2:?array<int,string>} [totalRoles, rolesWithSkills, scopeRoles|null]
     */
    private function resolveRoleScope(int $subInstituteId, array $filters): array
    {
        $roleBase = DB::table('s_user_jobrole as jr')
            ->where('jr.sub_institute_id', $subInstituteId)
            ->whereNull('jr.deleted_at');

        if ($filters['department_id'] !== null) {
            $roleBase->where('jr.department_id', (int) $filters['department_id']);
        }
        if ($filters['jobrole'] !== null) {
            $roleBase->where('jr.jobrole', $filters['jobrole']);
        }
        if ($filters['location'] !== null) {
            $roleBase->where('jr.location', $filters['location']);
        }
        if ($filters['business_unit'] !== null) {
            $roleBase->where('jr.industries', $filters['business_unit']);
        }
        if ($filters['job_family'] !== null) {
            $roleBase->where('jr.jobrole_category', $filters['job_family']);
        }

        $totalRoles = (clone $roleBase)->count();

        $rolesWithSkills = (clone $roleBase)
            ->whereExists(function ($q) use ($subInstituteId) {
                $q->select(DB::raw(1))
                    ->from('s_user_skill_jobrole as sj')
                    ->whereColumn('sj.jobrole', 'jr.jobrole')
                    ->where('sj.sub_institute_id', $subInstituteId)
                    ->whereNull('sj.deleted_at');
            })
            ->count();

        $scopeActive = collect($filters)->contains(fn ($v) => $v !== null);
        $scopeRoles = $scopeActive
            ? (clone $roleBase)->distinct()->pluck('jr.jobrole')->filter()->values()->all()
            : null;

        return [$totalRoles, $rolesWithSkills, $scopeRoles];
    }

    /** Constrain a competency-domain query to the active role scope. */
    private function applyScope($query, ?array $scopeRoles)
    {
        if ($scopeRoles !== null) {
            // Empty scope => the filter matched no roles => return nothing.
            $query->whereIn('jobrole', empty($scopeRoles) ? ['\0__none__'] : $scopeRoles);
        }

        return $query;
    }

    private function baseCount(string $table, int $subInstituteId, ?array $scopeRoles)
    {
        $q = DB::table($table)
            ->where('sub_institute_id', $subInstituteId)
            ->whereNull('deleted_at');

        return $this->applyScope($q, $scopeRoles);
    }

    /**
     * Total published competencies. In this ERP a competency IS an approved
     * skill, so this reads the existing s_users_skills catalog rather than a
     * duplicate competency table. Honours the same filters: Department maps to
     * s_users_skills.department_id; the role-based filters restrict to the
     * skills the in-scope job roles require (via s_jobrole_skills).
     */
    private function competenciesCount(int $sid, array $filters, ?array $scopeRoles): int
    {
        $q = DB::table('s_users_skills')
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at')
            ->where('approve_status', 'Approved');

        if ($filters['department_id'] !== null) {
            $q->where('department_id', (int) $filters['department_id']);
        }

        $roleFilterActive = $filters['jobrole'] !== null || $filters['location'] !== null
            || $filters['business_unit'] !== null || $filters['job_family'] !== null;

        if ($roleFilterActive) {
            $roles = ($scopeRoles && count($scopeRoles)) ? $scopeRoles : ['\0__none__'];
            $q->whereIn('title', function ($sub) use ($roles) {
                $sub->select('skill')->distinct()->from('s_jobrole_skills')->whereIn('jobrole', $roles);
            });
        }

        return $q->count();
    }

    /* ----------------------------------------------------------------- *
     * Summary tiles
     * ----------------------------------------------------------------- */

    private function summary(int $sid, array $filters, ?array $scopeRoles, int $totalRoles, int $rolesWithSkills): array
    {
        $competencies = $this->competenciesCount($sid, $filters, $scopeRoles);

        $frameworks = (clone $this->baseCount('s_competency_frameworks', $sid, $scopeRoles))
            ->where('status', 'active')->count();

        // DEGRADED: s_competency_assessments does not exist in this target
        // (assessment cycles were out of scope for this port). Reports 0
        // rather than throwing or blocking the rest of the dashboard.
        $assessments = 0;

        $certifications = (clone $this->baseCount('s_competency_certifications', $sid, $scopeRoles))
            ->where('status', 'valid')->count();

        $plans = (clone $this->baseCount('s_competency_development_plans', $sid, $scopeRoles))
            ->where('status', 'active')->count();

        return [
            ['key' => 'competencies', 'label' => 'Total Competencies', 'value' => $competencies, 'desc' => 'Published'],
            ['key' => 'frameworks', 'label' => 'Active Frameworks', 'value' => $frameworks, 'desc' => 'Active'],
            ['key' => 'roles_mapped', 'label' => 'Job Roles Mapped', 'value' => $rolesWithSkills, 'total' => $totalRoles, 'desc' => 'Out of ' . $totalRoles],
            ['key' => 'assessments', 'label' => 'Active Assessments', 'value' => $assessments, 'desc' => 'In progress'],
            ['key' => 'certifications', 'label' => 'Certifications', 'value' => $certifications, 'desc' => 'Valid'],
            ['key' => 'development_plans', 'label' => 'Development Plans', 'value' => $plans, 'desc' => 'Active'],
        ];
    }

    /* ----------------------------------------------------------------- *
     * Progress rings
     * ----------------------------------------------------------------- */

    private function progress(int $sid, ?array $scopeRoles, int $totalRoles, int $rolesWithSkills): array
    {
        // Role mapping completion (from Skill/Role tables)
        $rolePercent = $totalRoles > 0 ? round(($rolesWithSkills / $totalRoles) * 100) : 0;

        // DEGRADED: assessment completion needs s_competency_assessments,
        // which does not exist in this target. Reports 0/0 (0%) rather than
        // throwing.
        $totalAssessments = 0;
        $completedAssessments = 0;
        $assessmentPercent = 0;

        // Certification compliance: valid certifications / all still-relevant
        // (non-revoked) certifications. Count-based so it stays meaningful
        // regardless of how many distinct employee records the tenant holds.
        $validCerts = (clone $this->baseCount('s_competency_certifications', $sid, $scopeRoles))
            ->where('status', 'valid')->count();
        $activeCerts = (clone $this->baseCount('s_competency_certifications', $sid, $scopeRoles))
            ->where('status', '!=', 'revoked')->count();
        $certPercent = $activeCerts > 0 ? round(($validCerts / $activeCerts) * 100) : 0;

        // Development plan completion
        $totalPlans = (clone $this->baseCount('s_competency_development_plans', $sid, $scopeRoles))->count();
        $completedPlans = (clone $this->baseCount('s_competency_development_plans', $sid, $scopeRoles))
            ->where('status', 'completed')->count();
        $planPercent = $totalPlans > 0 ? round(($completedPlans / $totalPlans) * 100) : 0;

        return [
            ['key' => 'role_mapping', 'title' => 'Role Mapping Completion', 'percent' => (int) $rolePercent, 'current' => $rolesWithSkills, 'total' => $totalRoles, 'sub' => 'Roles Mapped'],
            ['key' => 'assessment', 'title' => 'Assessment Completion', 'percent' => (int) $assessmentPercent, 'current' => $completedAssessments, 'total' => $totalAssessments, 'sub' => 'Completed Assessments'],
            ['key' => 'certification', 'title' => 'Certification Compliance', 'percent' => (int) $certPercent, 'current' => $validCerts, 'total' => $activeCerts, 'sub' => 'Valid Certifications'],
            ['key' => 'development', 'title' => 'Development Plan Completion', 'percent' => (int) $planPercent, 'current' => $completedPlans, 'total' => $totalPlans, 'sub' => 'Completed Plans'],
        ];
    }

    /* ----------------------------------------------------------------- *
     * Work queues
     * ----------------------------------------------------------------- */

    private function workQueues(int $sid, ?int $userId, ?array $scopeRoles): array
    {
        $today = Carbon::today()->toDateString();
        $in30Days = Carbon::today()->addDays(30)->toDateString();

        // Pending my approvals: dev plans awaiting the current user's approval.
        $myApprovals = 0;
        if ($userId !== null) {
            $myApprovals = (clone $this->baseCount('s_competency_development_plans', $sid, $scopeRoles))
                ->where('approval_status', 'pending_approval')
                ->where('approver_id', $userId)
                ->count();
        }

        // DEGRADED: pending reviews / open assessments need s_competency_assessments,
        // which does not exist in this target. Reports 0 rather than throwing.
        $pendingReviews = 0;
        $openAssessments = 0;

        $expiringCerts = (clone $this->baseCount('s_competency_certifications', $sid, $scopeRoles))
            ->whereIn('status', ['valid', 'expiring'])
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [$today, $in30Days])
            ->count();

        $overduePlans = (clone $this->baseCount('s_competency_development_plans', $sid, $scopeRoles))
            ->where(function ($q) use ($today) {
                $q->where('status', 'overdue')
                    ->orWhere(function ($q2) use ($today) {
                        $q2->whereNotNull('due_date')
                            ->where('due_date', '<', $today)
                            ->whereNotIn('status', ['completed']);
                    });
            })
            ->count();

        return [
            ['key' => 'my_approvals', 'label' => 'Pending My Approvals', 'count' => $myApprovals],
            ['key' => 'pending_reviews', 'label' => 'Pending Reviews', 'count' => $pendingReviews],
            ['key' => 'open_assessments', 'label' => 'Open Assessments', 'count' => $openAssessments],
            ['key' => 'expiring_certifications', 'label' => 'Expiring Certifications (30 Days)', 'count' => $expiringCerts],
            ['key' => 'overdue_plans', 'label' => 'Overdue Development Plans', 'count' => $overduePlans],
        ];
    }

    /* ----------------------------------------------------------------- *
     * Recent activity
     * ----------------------------------------------------------------- */

    private function recentActivity(int $sid, int $limit = 6): array
    {
        $rows = DB::table('s_competency_activity_log')
            ->where('sub_institute_id', $sid)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return $rows->map(function ($row) {
            $timestamp = $row->created_at;

            return [
                'id'        => (int) $row->id,
                'user'      => $row->actor_name ?: 'System',
                'action'    => $row->description ?: $row->action,
                'time'      => $timestamp ? Carbon::parse($timestamp)->diffForHumans() : '',
                'timestamp' => $timestamp,
            ];
        })->all();
    }

    /* ----------------------------------------------------------------- *
     * Assessment calendar
     *
     * DEGRADED: both s_competency_assessments and s_competency_assessment_cycles
     * are missing in this target (assessment cycles out of scope for this
     * port). Returns an empty/zeroed slice rather than throwing.
     * ----------------------------------------------------------------- */

    private function assessmentCalendar(int $sid, ?array $scopeRoles): array
    {
        return [
            'cycle'          => null,
            'upcoming_count' => 0,
        ];
    }

    /* ----------------------------------------------------------------- *
     * Filter options (drives the five dropdowns)
     * ----------------------------------------------------------------- */

    public function filterOptions(int $sid): array
    {
        $roleBase = DB::table('s_user_jobrole')
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at');

        $departments = (clone $roleBase)
            ->whereNotNull('department_id')
            ->where('department_id', '!=', 0)
            ->select('department_id', 'department')
            ->distinct()
            ->orderBy('department')
            ->get()
            ->map(fn ($r) => ['value' => (string) $r->department_id, 'label' => $r->department ?: ('Department ' . $r->department_id)])
            ->unique('value')
            ->values()
            ->all();

        $simpleOptions = function ($column) use ($roleBase) {
            return (clone $roleBase)
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->select($column)
                ->distinct()
                ->orderBy($column)
                ->pluck($column)
                ->map(fn ($v) => ['value' => (string) $v, 'label' => (string) $v])
                ->all();
        };

        return [
            'departments'    => $departments,
            'jobroles'       => $simpleOptions('jobrole'),
            'locations'      => $simpleOptions('location'),
            'business_units' => $simpleOptions('industries'),
            'job_families'   => $simpleOptions('jobrole_category'),
        ];
    }
}
