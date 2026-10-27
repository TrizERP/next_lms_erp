<?php

namespace App\Brain\Intelligence;

/**
 * Domain rules and evidence-backed signals for Career Evidence Readiness.
 *
 * Every rule here is arithmetic over the career ledger, never a judgement about
 * a student. A finding names how many students are affected and never which —
 * these are children, and a signal row is read by people who do not need the
 * ids to decide what to do.
 *
 * None of these rules executes anything. Each one recommends a step for a
 * counsellor or coordinator to take; the human-approval gate downstream is
 * unchanged.
 */
final class CareerSignalRules
{
    /** Below this cohort a percentage says more about the sample than the school. */
    private const MIN_COHORT = 10;

    public function __construct(
        private readonly CareerIntelligence $analytics,
        private readonly ?string $syear,
    ) {
    }

    public function run(): array
    {
        $coverage = $this->analytics->coverage();
        if (! $coverage['available']) {
            return ['findings' => [], 'ruleStatus' => []];
        }

        $rules = [
            'evidence_coverage_gap' => ['Grade 9-10 students with no career evidence', fn () => $this->evidenceCoverageGap()],
            'aspiration_without_evidence' => ['Stated aspirations with nothing to support them', fn () => $this->aspirationWithoutEvidence()],
            'stale_evidence' => ['Career evidence that has gone stale', fn () => $this->staleEvidence()],
            'revised_evidence' => ['Evidence ledger rows contested or superseded', fn () => $this->revisedEvidence()],
        ];

        $findings = [];
        $ruleStatus = [];

        foreach ($rules as $key => [$label, $check]) {
            $raised = $check();
            $count = count($raised);
            $ruleStatus[] = [
                'rule' => $key,
                'label' => $label,
                'status' => $count > 0 ? 'fired' : 'checked',
                'detail' => $count > 0 ? "{$count} finding" . ($count === 1 ? '' : 's') . ' raised' : 'Checked against the career evidence ledger',
            ];
            foreach ($raised as $f) {
                $findings[] = $f;
            }
        }

        $severityOrder = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3, 'info' => 4];
        usort($findings, fn ($a, $b) => ($severityOrder[$a['severity']] ?? 5) <=> ($severityOrder[$b['severity']] ?? 5));

        return ['findings' => $findings, 'ruleStatus' => $ruleStatus];
    }

    private function evidenceCoverageGap(): array
    {
        $p = $this->analytics->position();
        if ($p === null || $p['cohort'] < self::MIN_COHORT || $p['unevidenced'] === 0) {
            return [];
        }
        // At or above this share the ledger is doing its job and the remainder
        // is a normal tail, not a finding.
        if ($p['coverageRate'] >= 60.0) {
            return [];
        }

        $severity = $p['coverageRate'] < 20.0 ? 'high' : 'medium';
        $bySource = $this->analytics->bySource();
        $sources = $bySource === []
            ? 'none'
            : implode(', ', array_map(fn ($s) => "{$s['label']} ({$s['events']})", $bySource));

        return [[
            'id' => "career-evidence-coverage-{$this->syear}",
            'rule' => 'evidence_coverage_gap',
            'severity' => $severity,
            'severityLabel' => ucfirst($severity),
            'title' => "{$p['unevidenced']} of {$p['cohort']} Grade 9-10 students have no career evidence",
            'whatHappened' => $p['events'] === 0
                ? "{$p['cohort']} students are on the roll in Grade 9 or 10 this year and the career evidence ledger holds no rows for any of them."
                : "{$p['evidenced']} of {$p['cohort']} students on the roll in Grade 9 or 10 have at least one row in the career evidence ledger; "
                    . "{$p['unevidenced']} ({$this->pct(100 - $p['coverageRate'])}%) have none.",
            'whyItMatters' => 'Career guidance for a student with no evidence can only repeat what the student says they want. '
                . 'The alignment between an ambition and what the student has demonstrated is undefined until at least one observation exists.',
            'evidence' => [
                ['label' => 'Students on the roll (Grade 9-10)', 'value' => (string) $p['cohort']],
                ['label' => 'With any career evidence', 'value' => (string) $p['evidenced']],
                ['label' => 'Without any career evidence', 'value' => (string) $p['unevidenced']],
                ['label' => 'Coverage', 'value' => "{$p['coverageRate']}%"],
                ['label' => 'With evidence dated this academic year', 'value' => (string) $p['evidencedThisYear']],
                ['label' => 'Evidence sources in use', 'value' => $sources],
            ],
            'likelyCause' => 'Evidence is written when a student submits an online exam or when the ingest command is run for one student; nothing runs it across a cohort on a schedule. Students who have not sat an online exam therefore have nothing recorded, whatever their marks.',
            'causeConfirmed' => false,
            'recommendation' => 'Decide which Grade 9-10 classes are expected to carry career evidence this year, then check whether online exams are being set for them and whether evidence ingestion has been run for the rest.',
            'owner' => 'Career Counsellor',
            'priority' => $severity,
            'confidence' => ['band' => 'High', 'value' => 0.9],
            'affected' => ['count' => $p['unevidenced'], 'total' => $p['cohort'], 'unit' => 'students'],
            'impact' => [
                'value' => $p['unevidenced'],
                'display' => (string) $p['unevidenced'],
                'label' => 'students with no evidence',
            ],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    private function aspirationWithoutEvidence(): array
    {
        $p = $this->analytics->position();
        if ($p === null || $p['aspirants'] === 0 || $p['aspirantsWithoutEvidence'] < 3) {
            return [];
        }

        $share = round($p['aspirantsWithoutEvidence'] / max(1, $p['aspirants']) * 100, 1);
        $severity = ($share >= 50.0 && $p['aspirantsWithoutEvidence'] >= 10) ? 'high' : 'medium';

        return [[
            'id' => "career-aspiration-no-evidence-{$this->syear}",
            'rule' => 'aspiration_without_evidence',
            'severity' => $severity,
            'severityLabel' => ucfirst($severity),
            'title' => "{$p['aspirantsWithoutEvidence']} students state a career aspiration with no supporting evidence",
            'whatHappened' => "{$p['aspirants']} Grade 9-10 students have a current career aspiration on file. "
                . "{$p['aspirantsWithoutEvidence']} ({$share}%) have no row in the evidence ledger, so their stated ambition cannot be tested against anything they have demonstrated.",
            'whyItMatters' => 'An aspiration without evidence is the case where counselling is most likely to either rubber-stamp a choice or discourage it on impression alone.',
            'evidence' => [
                ['label' => 'Students with a current aspiration', 'value' => (string) $p['aspirants']],
                ['label' => 'Of those, with no career evidence', 'value' => (string) $p['aspirantsWithoutEvidence']],
                ['label' => 'Share without evidence', 'value' => "{$share}%"],
            ],
            'likelyCause' => null,
            'causeConfirmed' => false,
            'recommendation' => 'Prioritise these students when evidence is next collected, before any alignment conversation relies on their stated aspiration.',
            'owner' => 'Career Counsellor',
            'priority' => $severity,
            'confidence' => ['band' => 'High', 'value' => 0.9],
            'affected' => ['count' => $p['aspirantsWithoutEvidence'], 'total' => $p['aspirants'], 'unit' => 'students'],
            'impact' => [
                'value' => $p['aspirantsWithoutEvidence'],
                'display' => (string) $p['aspirantsWithoutEvidence'],
                'label' => 'aspirations untested',
            ],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    private function staleEvidence(): array
    {
        $p = $this->analytics->position();
        if ($p === null || $p['evidenced'] < self::MIN_COHORT) {
            return [];
        }

        $share = round($p['staleStudents'] / max(1, $p['evidenced']) * 100, 1);
        if ($share < 50.0) {
            return [];
        }

        $days = CareerIntelligence::STALE_AFTER_DAYS;

        return [[
            'id' => "career-stale-evidence-{$this->syear}",
            'rule' => 'stale_evidence',
            'severity' => 'medium',
            'severityLabel' => 'Medium',
            'title' => "{$p['staleStudents']} of {$p['evidenced']} evidenced students have nothing newer than {$days} days",
            'whatHappened' => "For {$p['staleStudents']} of the {$p['evidenced']} students who have any career evidence ({$share}%), the newest observation is more than {$days} days old.",
            'whyItMatters' => 'Evidence describes a student at the time it was observed. A year-old observation is a record of who the student was, and a career conversation built on it risks describing someone who has since changed.',
            'evidence' => [
                ['label' => 'Students with any evidence', 'value' => (string) $p['evidenced']],
                ['label' => "Newest observation older than {$days} days", 'value' => (string) $p['staleStudents']],
                ['label' => 'Share stale', 'value' => "{$share}%"],
                ['label' => 'Evidenced with a row dated this academic year', 'value' => (string) $p['evidencedThisYear']],
            ],
            'likelyCause' => null,
            'causeConfirmed' => false,
            'recommendation' => 'Refresh evidence for the stale students before they are counselled; an up-to-date observation is worth more than a larger pile of old ones.',
            'owner' => 'Career Counsellor',
            'priority' => 'medium',
            'confidence' => ['band' => 'High', 'value' => 0.85],
            'affected' => ['count' => $p['staleStudents'], 'total' => $p['evidenced'], 'unit' => 'students'],
            'impact' => [
                'value' => $p['staleStudents'],
                'display' => (string) $p['staleStudents'],
                'label' => 'students with stale evidence',
            ],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    private function revisedEvidence(): array
    {
        $p = $this->analytics->position();
        if ($p === null || $p['events'] < 20) {
            return [];
        }

        $revised = max($p['contestedEvents'], $p['supersededEvents']);
        $share = round($revised / max(1, $p['events']) * 100, 1);
        if ($share < 30.0) {
            return [];
        }

        return [[
            'id' => "career-revised-evidence-{$this->syear}",
            'rule' => 'revised_evidence',
            'severity' => 'low',
            'severityLabel' => 'Low',
            'title' => "{$share}% of career evidence rows have been revised",
            'whatHappened' => "Of {$p['events']} ledger rows for this cohort, {$p['supersededEvents']} have been superseded by a newer observation and {$p['contestedEvents']} are marked contested.",
            'whyItMatters' => 'The ledger is append-only, so a revision is normal when a student is re-assessed. A high share means a large part of what was recorded no longer stands, and counts of "evidence" overstate what is currently held.',
            'evidence' => [
                ['label' => 'Ledger rows (cohort)', 'value' => (string) $p['events']],
                ['label' => 'Superseded', 'value' => (string) $p['supersededEvents']],
                ['label' => 'Contested', 'value' => (string) $p['contestedEvents']],
                ['label' => 'Currently standing', 'value' => (string) $p['activeEvents']],
            ],
            'likelyCause' => null,
            'causeConfirmed' => false,
            'recommendation' => 'Read the revised rows by source before relying on the total; the standing count is the one that reflects the students today.',
            'owner' => 'Career Counsellor',
            'priority' => 'low',
            'confidence' => ['band' => 'High', 'value' => 0.85],
            'affected' => ['count' => $revised, 'total' => $p['events'], 'unit' => 'ledger rows'],
            'impact' => [
                'value' => $revised,
                'display' => (string) $revised,
                'label' => 'rows revised',
            ],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    private function pct(float $v): string
    {
        return rtrim(rtrim(number_format($v, 1), '0'), '.');
    }
}
