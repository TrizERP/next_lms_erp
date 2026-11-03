<?php

namespace App\Brain\Intelligence;

/**
 * What the alumni register means, and what is worth a coordinator's attention.
 *
 * The checks themselves live in AlumniIntelligence::findings(), so the screen and
 * the signal ledger can never disagree about what was found. This class only gives
 * each finding the stable rule key `ModuleSignalBridge` dedupes on — a finding with
 * no rule key cannot be deduped, so a second pipeline run would raise it again.
 *
 * It reads counts and shares only. No alumnus is named: the register holds names,
 * contact details, employers and photographs, and none of it is read here.
 */
final class AlumniSignalRules
{
    /** Finding id (as the screen shows it) => ledger rule key. */
    private const RULES = [
        'alumni-awaiting-verification' => 'awaiting_verification',
        'alumni-unreachable' => 'unreachable',
        'alumni-incomplete-profiles' => 'incomplete_profiles',
        'alumni-dormant' => 'dormant',
        'alumni-mentor-requests-waiting' => 'mentor_requests_waiting',
        'alumni-donations-unverified' => 'donations_unverified',
    ];

    public function __construct(
        private readonly AlumniIntelligence $analytics,
        private readonly ?string $syear = null,
    ) {
    }

    /** @return array{findings:array<int,array<string,mixed>>,ruleStatus:array<int,array<string,mixed>>} */
    public function run(): array
    {
        $raised = $this->analytics->findings();

        $findings = [];
        foreach ($raised['findings'] as $finding) {
            $rule = self::RULES[$finding['id']] ?? null;
            if ($rule === null) {
                continue;
            }
            $findings[] = $finding + ['rule' => $rule];
        }

        $rank = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3, 'info' => 4];
        usort($findings, function ($a, $b) use ($rank) {
            $bySeverity = ($rank[$a['severity']] ?? 5) <=> ($rank[$b['severity']] ?? 5);

            return $bySeverity !== 0
                ? $bySeverity
                : ($b['affected']['count'] ?? 0) <=> ($a['affected']['count'] ?? 0);
        });

        return ['findings' => $findings, 'ruleStatus' => $raised['ruleStatus']];
    }
}
