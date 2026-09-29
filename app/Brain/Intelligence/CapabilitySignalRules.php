<?php

namespace App\Brain\Intelligence;

/**
 * What the capability figures mean, and what is worth someone's morning.
 *
 * Four checks, each over a fact {@see CapabilityIntelligence} already
 * computed for its own screen — no rule here runs a query of its own. Nothing
 * is raised below {@see CapabilityIntelligence::MIN_DEPARTMENT_COHORT} or
 * without at least one real row behind it, in keeping with the rest of the
 * Brain catalogue: an empty table produces no finding, not a finding that
 * says zero.
 */
final class CapabilitySignalRules
{
    public function __construct(
        private readonly CapabilityIntelligence $analytics,
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
            'jobroles_without_skill_mapping' => [
                'Job roles with no skill mapped to them',
                fn () => $this->unmappedJobRoles(),
            ],
            'certifications_expired_but_marked_valid' => [
                'Certifications past expiry still marked valid',
                fn () => $this->expiredCertificationsMarkedValid(),
            ],
            'certifications_expiring_soon' => [
                'Certifications expiring within the alert window',
                fn () => $this->expiringCertifications(),
            ],
            'development_plans_overdue' => [
                'Development plans overdue',
                fn () => $this->overdueDevelopmentPlans(),
            ],
        ];

        $findings = [];
        $ruleStatus = [];

        foreach ($rules as $key => [$label, $check]) {
            $raised = $check();
            $ruleStatus[] = [
                'key' => $key,
                'label' => $label,
                'checked' => true,
                'raised' => $raised !== [],
            ];
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

    /* ------------------------------------------------------------ rules */

    /** @return array<int,array<string,mixed>> */
    private function unmappedJobRoles(): array
    {
        $position = $this->analytics->position();
        if ($position === null || $position['jobRolesWithoutSkillMapping'] === 0) {
            return [];
        }

        $share = $position['jobRoles'] > 0
            ? round($position['jobRolesWithoutSkillMapping'] / $position['jobRoles'] * 100, 1)
            : 0.0;

        if ($share < CapabilityIntelligence::UNMAPPED_ROLE_ALERT_SHARE) {
            return [];
        }

        $severe = $share >= CapabilityIntelligence::UNMAPPED_ROLE_SEVERE_SHARE;

        $weakest = array_values(array_filter(
            $this->analytics->byDepartment(),
            static fn ($d) => $d['jobRoles'] >= CapabilityIntelligence::MIN_DEPARTMENT_COHORT
                && $d['jobRolesWithoutSkillMapping'] > 0,
        ));

        return [[
            'id' => "capability-unmapped-roles-{$this->syear}",
            'rule' => 'jobroles_without_skill_mapping',
            'severity' => $severe ? 'high' : 'medium',
            'severityLabel' => $severe ? 'High' : 'Medium',
            'title' => "{$position['jobRolesWithoutSkillMapping']} job roles ({$share}%) have no skill mapped to them",
            'whatHappened' => "{$position['jobRolesWithoutSkillMapping']} of {$position['jobRoles']} job roles "
                ."({$share}%) have no row in the skill-mapping table, out of {$position['jobRolesWithSkillMapping']} "
                .'that do.',
            'whyItMatters' => 'A role with no skill mapped to it cannot be assessed against, cannot anchor a '
                .'development plan, and cannot be cited by a certification requirement — every other capability '
                .'figure for these roles is silent, not zero.',
            'evidence' => array_merge(
                [
                    ['label' => 'Roles with no mapping', 'value' => (string) $position['jobRolesWithoutSkillMapping']],
                    ['label' => 'Total job roles', 'value' => (string) $position['jobRoles']],
                    ['label' => 'Share', 'value' => "{$share}%"],
                ],
                array_map(static fn ($d) => [
                    'label' => $d['label'],
                    'value' => "{$d['jobRolesWithoutSkillMapping']} of {$d['jobRoles']} unmapped",
                    'note' => "{$d['skillCoverage']}% mapped",
                ], array_slice($weakest, 0, 5)),
            ),
            'likelyCause' => 'Roles added to the catalogue after the skill-mapping exercise ran, or a department not '
                .'yet covered by it. This data shows which roles, not why.',
            'causeConfirmed' => false,
            'recommendation' => 'Start with the departments in the evidence above — mapping the weakest department '
                .'first closes the largest share of the gap.',
            'owner' => 'Talent / competency owner',
            'priority' => $severe ? 'high' : 'medium',
            'confidence' => ['band' => 'High', 'value' => 0.9],
            'affected' => [
                'count' => $position['jobRolesWithoutSkillMapping'],
                'total' => $position['jobRoles'],
                'unit' => 'job roles',
            ],
            'impact' => [
                'value' => $position['jobRolesWithoutSkillMapping'],
                'display' => (string) $position['jobRolesWithoutSkillMapping'],
                'label' => 'job roles with no skill coverage',
            ],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /** @return array<int,array<string,mixed>> */
    private function expiredCertificationsMarkedValid(): array
    {
        $dq = $this->analytics->dataQuality();
        if (! $dq['available']) {
            return [];
        }

        $check = $this->findCheck($dq['checks'], 'certifications_expired_but_marked_valid');
        if ($check === null || $check['value'] === 0) {
            return [];
        }

        $count = (int) $check['value'];

        return [[
            'id' => "capability-cert-expired-active-{$this->syear}",
            'rule' => 'certifications_expired_but_marked_valid',
            'severity' => 'high',
            'severityLabel' => 'High',
            'title' => "{$count} certification".($count === 1 ? '' : 's')." past expiry ".($count === 1 ? 'is' : 'are')
                .' still recorded as valid',
            'whatHappened' => "{$count} certification".($count === 1 ? ' has an' : 's have')." expiry date in the "
                .'past while its status column still reads valid or expiring.',
            'whyItMatters' => 'Anything reading only the status column — a compliance export, a manager\'s dashboard '
                .'— would report these people as currently certified when their credential has lapsed.',
            'evidence' => [
                ['label' => 'Expired but marked current', 'value' => (string) $count],
            ],
            'likelyCause' => 'Nobody has run the status transition since the expiry date passed. This data shows the '
                .'lapse, not whether a renewal is already in progress.',
            'causeConfirmed' => false,
            'recommendation' => 'Confirm each one is either renewed or genuinely lapsed, and correct the status '
                .'column so it stops reporting these as current.',
            'owner' => 'Certification owner',
            'priority' => 'high',
            'confidence' => ['band' => 'High', 'value' => 0.95],
            'affected' => ['count' => $count, 'total' => null, 'unit' => 'certifications'],
            'impact' => ['value' => $count, 'display' => (string) $count, 'label' => 'certifications overstated as current'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /** @return array<int,array<string,mixed>> */
    private function expiringCertifications(): array
    {
        $dq = $this->analytics->dataQuality();
        if (! $dq['available']) {
            return [];
        }

        $check = $this->findCheck($dq['checks'], 'certifications_expiring_soon');
        if ($check === null || $check['value'] === 0) {
            return [];
        }

        $count = (int) $check['value'];
        $window = CapabilityIntelligence::CERT_EXPIRY_WINDOW_DAYS;

        return [[
            'id' => "capability-cert-expiring-{$this->syear}",
            'rule' => 'certifications_expiring_soon',
            'severity' => 'medium',
            'severityLabel' => 'Medium',
            'title' => "{$count} certification".($count === 1 ? '' : 's')." expire".($count === 1 ? 's' : '')
                ." within {$window} days",
            'whatHappened' => "{$count} certification".($count === 1 ? ' has' : 's have')." an expiry date within "
                ."the next {$window} days and is still recorded as valid or expiring.",
            'whyItMatters' => 'A renewal that starts after the credential lapses leaves a gap the certification was '
                .'there to cover.',
            'evidence' => [
                ['label' => "Expiring within {$window} days", 'value' => (string) $count],
            ],
            'likelyCause' => null,
            'causeConfirmed' => false,
            'recommendation' => 'Start the renewal for each of these before the expiry date rather than after it.',
            'owner' => 'Certification owner',
            'priority' => 'medium',
            'confidence' => ['band' => 'High', 'value' => 0.95],
            'affected' => ['count' => $count, 'total' => null, 'unit' => 'certifications'],
            'impact' => ['value' => $count, 'display' => (string) $count, 'label' => 'certifications due to lapse'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /** @return array<int,array<string,mixed>> */
    private function overdueDevelopmentPlans(): array
    {
        $dq = $this->analytics->dataQuality();
        if (! $dq['available']) {
            return [];
        }

        $check = $this->findCheck($dq['checks'], 'development_plans_overdue');
        if ($check === null || $check['value'] === 0) {
            return [];
        }

        $count = (int) $check['value'];

        return [[
            'id' => "capability-devplan-overdue-{$this->syear}",
            'rule' => 'development_plans_overdue',
            'severity' => 'medium',
            'severityLabel' => 'Medium',
            'title' => "{$count} development plan".($count === 1 ? '' : 's')." ".($count === 1 ? 'is' : 'are')." overdue",
            'whatHappened' => "{$count} development plan".($count === 1 ? ' is' : 's are')." explicitly marked "
                .'overdue, or past its due date without being marked complete.',
            'whyItMatters' => 'An overdue plan with nobody looking at it is the gap it was written to close, still open.',
            'evidence' => [
                ['label' => 'Overdue plans', 'value' => (string) $count],
            ],
            'likelyCause' => null,
            'causeConfirmed' => false,
            'recommendation' => 'Review each plan with its mentor or approver to close it out or reset its due date.',
            'owner' => 'Mentor / plan approver',
            'priority' => 'medium',
            'confidence' => ['band' => 'High', 'value' => 0.95],
            'affected' => ['count' => $count, 'total' => null, 'unit' => 'development plans'],
            'impact' => ['value' => $count, 'display' => (string) $count, 'label' => 'plans overdue'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /* ------------------------------------------------------------ helpers */

    /** @param array<int,array<string,mixed>> $checks */
    private function findCheck(array $checks, string $key): ?array
    {
        foreach ($checks as $check) {
            if ($check['key'] === $key) {
                return $check;
            }
        }

        return null;
    }
}
