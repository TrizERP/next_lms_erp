<?php

namespace App\Brain\Intelligence;

/**
 * What the staffing, hiring and skill figures mean, and what is worth
 * someone's morning.
 *
 * ── SMALL COHORTS ───────────────────────────────────────────────────────────
 *
 * Nothing is compared below {@see OrganizationIntelligence::MIN_DEPARTMENT_COHORT}
 * staff or {@see OrganizationIntelligence::MIN_ATTRITION_COHORT} exits, and
 * findings are ordered by how many people or postings they touch rather than
 * by the size of a percentage — the same discipline
 * {@see AttendanceSignalRules} and {@see HrSignalRules} already apply.
 *
 * ── EVERY RULE READS THE ANALYTICS CLASS, NEVER THE DATABASE ────────────────
 *
 * No method here calls `DB::` directly. Every figure comes from
 * {@see OrganizationIntelligence}'s public methods, so the query logic and
 * its guards (missing tables, small cohorts, null-safe denominators) live in
 * exactly one place.
 */
final class OrganizationSignalRules
{
    /** Relative rise in exits (current 30-day window vs. the previous one) that counts as "rising" rather than noise. */
    private const ATTRITION_RISE_PERCENT = 50.0;

    /** Departments named inside one finding's evidence before it stops being readable. */
    private const MAX_NAMED_IN_EVIDENCE = 6;

    /** Coverage below this share is a gap worth naming, for a department large enough to support the comparison. */
    private const LOW_SKILL_COVERAGE_SHARE = 50.0;

    public function __construct(
        private readonly OrganizationIntelligence $analytics,
        private readonly ?string $syear,
    ) {
    }

    /** @return array{findings:array<int,array<string,mixed>>,ruleStatus:array<int,array<string,mixed>>} */
    public function run(): array
    {
        if (! $this->analytics->coverage()['available']) {
            return ['findings' => [], 'ruleStatus' => []];
        }

        $rules = [
            'attrition_rising' => ['Attrition rising against the prior period', fn () => $this->attritionRising()],
            'departments_without_headcount' => ['Departments with no active staff', fn () => $this->departmentsWithoutHeadcount()],
            'stalled_postings' => ['Open postings overdue with no hire', fn () => $this->stalledPostings()],
            'skill_coverage_gap' => ['Departments with low coverage of their required skills', fn () => $this->skillCoverageGap()],
        ];

        $findings = [];
        $ruleStatus = [];

        foreach ($rules as $key => [$label, $check]) {
            $raised = $check();
            $ruleStatus[] = ['key' => $key, 'label' => $label, 'checked' => true, 'raised' => $raised !== []];
            foreach ($raised as $finding) {
                $findings[] = $finding;
            }
        }

        $rank = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3, 'info' => 4];
        usort($findings, function ($a, $b) use ($rank) {
            $bySeverity = ($rank[$a['severity']] ?? 5) <=> ($rank[$b['severity']] ?? 5);

            return $bySeverity !== 0
                ? $bySeverity
                : ($b['affected']['count'] ?? 0) <=> ($a['affected']['count'] ?? 0);
        });

        return ['findings' => $findings, 'ruleStatus' => $ruleStatus];
    }

    /* --------------------------------------------------------------- attrition */

    /** @return array<int,array<string,mixed>> */
    private function attritionRising(): array
    {
        $position = $this->analytics->position();
        if ($position === null || $position['attritionCount'] < OrganizationIntelligence::MIN_ATTRITION_COHORT) {
            return [];
        }
        if ($position['attritionTrend'] < self::ATTRITION_RISE_PERCENT) {
            return [];
        }

        $current = $position['attritionCount'];
        $previous = $position['previousAttritionCount'];
        $days = OrganizationIntelligence::RECENT_WINDOW_DAYS;

        return [[
            'id' => "organization-attrition-rising-{$this->syear}",
            'rule' => 'attrition_rising',
            'severity' => $current >= OrganizationIntelligence::MIN_ATTRITION_COHORT * 2 ? 'high' : 'medium',
            'severityLabel' => $current >= OrganizationIntelligence::MIN_ATTRITION_COHORT * 2 ? 'High' : 'Medium',
            'title' => "Exits rose {$position['attritionTrend']}% against the previous {$days} days",
            'whatHappened' => $this->sentence([
                "{$current} staff left in the last {$days} days, against {$previous} in the {$days} days before that "
                    .'— a rise of '.$position['attritionTrend'].'%.',
                $position['attritionRate'] !== null
                    ? "That is {$position['attritionRate']}% of the {$position['activeStaff']} staff currently active."
                    : null,
            ]),
            'whyItMatters' => 'A rise this size over one rolling window is either the start of a pattern or a single '
                .'department leaving at once. Either way it is worth knowing before the next window closes rather '
                .'than after.',
            'evidence' => [
                ['label' => 'Exits, last '.$days.' days', 'value' => (string) $current],
                ['label' => 'Exits, prior '.$days.' days', 'value' => (string) $previous],
                ['label' => 'Change', 'value' => $position['attritionTrend'].'%'],
                ['label' => 'Active staff', 'value' => (string) $position['activeStaff']],
            ],
            'likelyCause' => 'A department-specific event, a seasonal contract cycle, or simply a small cohort where a '
                .'handful of departures reads as a large percentage. This figure cannot distinguish between them.',
            'causeConfirmed' => false,
            'recommendation' => 'Check whether the recent exits sit in one or two departments before treating this as '
                .'an institute-wide trend — the department breakdown below covers the same window.',
            'owner' => 'HR office',
            'priority' => $current >= OrganizationIntelligence::MIN_ATTRITION_COHORT * 2 ? 'high' : 'medium',
            'confidence' => $current >= 10 ? ['band' => 'Medium', 'value' => 0.7] : ['band' => 'Low', 'value' => 0.5],
            'affected' => ['count' => $current, 'total' => $position['activeStaff'], 'unit' => 'staff'],
            'impact' => ['value' => $position['attritionTrend'], 'display' => $position['attritionTrend'].'%', 'label' => 'rise in exits'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /* --------------------------------------------------------------- departments */

    /** @return array<int,array<string,mixed>> */
    private function departmentsWithoutHeadcount(): array
    {
        $empty = array_values(array_filter($this->analytics->byDepartment(), static fn ($d) => $d['activeStaff'] === 0));
        if ($empty === []) {
            return [];
        }

        $position = $this->analytics->position();
        $totalDepartments = $position['departments'] ?? count($this->analytics->byDepartment());

        return [[
            'id' => "organization-departments-empty-{$this->syear}",
            'rule' => 'departments_without_headcount',
            'severity' => 'low',
            'severityLabel' => 'Low',
            'title' => count($empty) === 1
                ? "{$empty[0]['label']} carries no active staff"
                : count($empty).' departments in the master carry no active staff',
            'whatHappened' => $this->sentence([
                count($empty).' of '.$totalDepartments.' departments in the department master have zero active staff '
                    .'assigned to them.',
                'The largest by historical headcount is '.$empty[0]['label'].', which has held '
                    .$empty[0]['allStaff'].' staff record'.($empty[0]['allStaff'] === 1 ? '' : 's').' over time.',
            ]),
            'whyItMatters' => 'A department with nobody in it is either newly created and not yet staffed, or every '
                .'member of staff who once belonged to it has since moved or left. Both are worth a decision — the '
                .'department master otherwise silently overstates how many parts of the organization are actually run.',
            'evidence' => array_map(static fn ($d) => [
                'label' => $d['label'],
                'value' => '0 active',
                'note' => "{$d['allStaff']} staff record".($d['allStaff'] === 1 ? '' : 's').' historically',
            ], array_slice($empty, 0, self::MAX_NAMED_IN_EVIDENCE)),
            'likelyCause' => null,
            'causeConfirmed' => false,
            'recommendation' => 'Confirm whether each named department is awaiting its first hire or should be '
                .'retired from the master.',
            'owner' => 'HR office',
            'priority' => 'low',
            'confidence' => ['band' => 'High', 'value' => 0.9],
            'affected' => ['count' => count($empty), 'total' => $totalDepartments, 'unit' => 'departments'],
            'impact' => null,
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /* --------------------------------------------------------------- hiring */

    /** @return array<int,array<string,mixed>> */
    private function stalledPostings(): array
    {
        $stalled = $this->analytics->stalledPostings();
        if ($stalled === []) {
            return [];
        }

        usort($stalled, static fn ($a, $b) => $b['daysOverdue'] <=> $a['daysOverdue']);
        $worst = $stalled[0];
        $openPositions = array_sum(array_map(static fn ($p) => $p['positions'] ?? 1, $stalled));

        return [[
            'id' => "organization-stalled-postings-{$this->syear}",
            'rule' => 'stalled_postings',
            'severity' => count($stalled) >= 3 || $worst['daysOverdue'] >= 60 ? 'high' : 'medium',
            'severityLabel' => count($stalled) >= 3 || $worst['daysOverdue'] >= 60 ? 'High' : 'Medium',
            'title' => count($stalled) === 1
                ? "{$worst['title']} has been overdue {$worst['daysOverdue']} days with no hire"
                : count($stalled).' open postings are past their deadline with no hire recorded',
            'whatHappened' => $this->sentence([
                count($stalled).' job posting'.(count($stalled) === 1 ? '' : 's').' still open have a deadline that '
                    .'has already passed and no application marked hired against '.(count($stalled) === 1 ? 'it' : 'any of them').'.',
                "The longest overdue is {$worst['title']} ({$worst['departmentLabel']}), {$worst['daysOverdue']} days "
                    .'past its deadline.',
                "Together they account for {$openPositions} open position".($openPositions === 1 ? '' : 's').'.',
            ]),
            'whyItMatters' => 'A department carrying an open requisition past its own deadline is running short-staffed '
                .'against a plan someone already approved. Every day it stays open is a day of cover the department is '
                .'absorbing without the person the posting was meant to bring in.',
            'evidence' => array_map(static fn ($p) => [
                'label' => $p['title'],
                'value' => "{$p['daysOverdue']} days overdue",
                'note' => $p['departmentLabel'].($p['positions'] !== null ? " · {$p['positions']} position(s)" : ''),
            ], array_slice($stalled, 0, self::MAX_NAMED_IN_EVIDENCE)),
            'likelyCause' => 'A weak candidate pipeline for the role, a deadline set before the search began in '
                .'earnest, or an offer still being negotiated outside the application stage this figure can see.',
            'causeConfirmed' => false,
            'recommendation' => 'Extend or close each named posting explicitly — a passed deadline with no decision '
                .'either way is why this figure keeps growing rather than resolving.',
            'owner' => 'Talent acquisition',
            'priority' => count($stalled) >= 3 ? 'high' : 'medium',
            'confidence' => ['band' => 'High', 'value' => 0.9],
            'affected' => ['count' => count($stalled), 'total' => null, 'unit' => 'postings'],
            'impact' => ['value' => $openPositions, 'display' => (string) $openPositions, 'label' => 'positions left unfilled past deadline'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /* --------------------------------------------------------------- skills */

    /** @return array<int,array<string,mixed>> */
    private function skillCoverageGap(): array
    {
        $weak = array_values(array_filter(
            $this->analytics->skillCoverageByDepartment(),
            static fn ($d) => $d['headcount'] >= OrganizationIntelligence::MIN_DEPARTMENT_COHORT
                && $d['coverage'] < self::LOW_SKILL_COVERAGE_SHARE,
        ));

        if ($weak === []) {
            return [];
        }

        // Already sorted weakest-first by the analytics class.
        $worst = $weak[0];
        $staff = array_sum(array_column($weak, 'headcount'));

        return [[
            'id' => "organization-skill-coverage-gap-{$this->syear}",
            'rule' => 'skill_coverage_gap',
            'severity' => $worst['coverage'] < self::LOW_SKILL_COVERAGE_SHARE / 2 ? 'high' : 'medium',
            'severityLabel' => $worst['coverage'] < self::LOW_SKILL_COVERAGE_SHARE / 2 ? 'High' : 'Medium',
            'title' => count($weak) === 1
                ? "{$worst['label']} covers only {$worst['coverage']}% of its required skills"
                : count($weak).' departments cover less than '.self::LOW_SKILL_COVERAGE_SHARE.'% of their required skills',
            'whatHappened' => $this->sentence([
                "{$worst['label']} has {$worst['coveredSkills']} of {$worst['requiredSkills']} required skills held by "
                    ."at least one of its {$worst['headcount']} active staff ({$worst['coverage']}%).",
                count($weak) > 1
                    ? 'A further '.(count($weak) - 1).' department'.(count($weak) - 1 === 1 ? '' : 's')
                        .' with at least '.OrganizationIntelligence::MIN_DEPARTMENT_COHORT.' staff sit below the same line.'
                    : null,
            ]),
            'whyItMatters' => 'A required skill nobody in the department holds is not a training gap on paper — it is '
                .'work the department cannot currently do without pulling someone from elsewhere or engaging outside '
                .'help.',
            'evidence' => array_map(static fn ($d) => [
                'label' => $d['label'],
                'value' => "{$d['coverage']}%",
                'note' => "{$d['coveredSkills']} of {$d['requiredSkills']} skills · {$d['headcount']} active staff",
            ], array_slice($weak, 0, self::MAX_NAMED_IN_EVIDENCE)),
            'likelyCause' => 'Skills marked required after the department was last staffed, a skill matrix nobody has '
                .'filled in for newer staff, or a genuine capability gap. This figure cannot distinguish between them.',
            'causeConfirmed' => false,
            'recommendation' => 'Check whether the named departments’ skill matrix entries are simply unfilled before '
                .'treating this as a hiring or training gap — an empty matrix and a real gap look identical here.',
            'owner' => 'HR office',
            'priority' => 'medium',
            'confidence' => $staff >= 20 ? ['band' => 'Medium', 'value' => 0.65] : ['band' => 'Low', 'value' => 0.45],
            'affected' => ['count' => $staff, 'total' => null, 'unit' => 'staff'],
            'impact' => ['value' => $worst['coverage'], 'display' => "{$worst['coverage']}%", 'label' => 'skill coverage in the weakest department'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /* ------------------------------------------------------------ helpers */

    /** @param array<int,?string> $parts */
    private function sentence(array $parts): string
    {
        return implode(' ', array_filter($parts, static fn ($p) => $p !== null && $p !== ''));
    }
}
