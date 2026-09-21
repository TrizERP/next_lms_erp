<?php

namespace App\Brain\Intelligence;

/**
 * Domain rules and evidence-backed signals for Library Circulation & Reading Engagement.
 */
final class LibrarySignalRules
{
    private const HIGH_DEMAND_THRESHOLD = 15;

    public function __construct(
        private readonly LibraryIntelligence $analytics,
        private readonly ?string $syear,
    ) {
    }

    public function run(): array
    {
        $coverage = $this->analytics->coverage();
        if (!$coverage['available']) {
            return ['findings' => [], 'ruleStatus' => []];
        }

        $rules = [
            'high_demand_titles' => ['High circulation titles with copy contention', fn () => $this->highDemandTitles()],
            'overdue_loan_volume' => ['Overdue unreturned book volume', fn () => $this->overdueLoanVolume()],
            'dormant_catalogue' => ['Titles that have never left the shelf', fn () => $this->dormantCatalogue()],
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
                'detail' => $count > 0 ? "{$count} finding" . ($count === 1 ? '' : 's') . ' raised' : 'Checked against library circulation benchmarks',
            ];
            foreach ($raised as $f) {
                $findings[] = $f;
            }
        }

        $severityOrder = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3, 'info' => 4];
        usort($findings, fn ($a, $b) => ($severityOrder[$a['severity']] ?? 5) <=> ($severityOrder[$b['severity']] ?? 5));

        return [
            'findings' => $findings,
            'ruleStatus' => $ruleStatus,
        ];
    }

    private function highDemandTitles(): array
    {
        $books = $this->analytics->byTitle();
        $findings = [];

        foreach ($books as $b) {
            if ($b['issues'] >= self::HIGH_DEMAND_THRESHOLD) {
                $findings[] = [
                    'id' => "lib_demand_{$b['key']}_{$this->syear}",
                    'rule' => 'high_demand_titles',
                    'severity' => 'medium',
                    'severityLabel' => 'Medium',
                    'title' => "High student demand for '{$b['label']}'",
                    'whatHappened' => "'{$b['label']}' by {$b['author']} has circulated {$b['issues']} times in this academic session, with {$b['onLoan']} copy currently out on active loan.",
                    'whyItMatters' => 'Consistently high-demand titles without duplicate accessions generate reservation bottlenecks for curriculum and leisure reading.',
                    'evidence' => [
                        ['label' => 'Total circulations', 'value' => (string) $b['issues']],
                        ['label' => 'Currently on loan', 'value' => (string) $b['onLoan']],
                        ['label' => 'Author', 'value' => $b['author']],
                    ],
                    'impact' => 'Student wait times for popular reading material.',
                    'recommendation' => 'Procure 2-3 additional accession copies for the primary library stacks.',
                    'owner' => 'Librarian & Procurement Officer',
                    'confidence' => 0.95,
                    'causeConfirmed' => false,
                ];
            }
        }

        return array_slice($findings, 0, 3);
    }

    private function overdueLoanVolume(): array
    {
        $pos = $this->analytics->position();
        if ($pos === null || $pos['overdueCount'] === 0) {
            return [];
        }

        $profile = $this->analytics->overdueProfile();
        $median = $profile['medianDaysOver'];
        $worst = $profile['worstDaysOver'];

        // HOW LATE decides what this finding IS. A book a week overdue is a
        // reminder; a book a year overdue is a book the library no longer has,
        // and the same count covers both. The previous version reported only
        // the count, so a shelf of missing stock and a slow week read alike.
        $longGone = $median !== null && $median >= 180;
        $severity = $longGone || $pos['overdueCount'] >= 50 ? 'high' : 'medium';

        return [[
            'id' => "library-overdue-{$this->syear}",
            'rule' => 'overdue_loan_volume',
            'severity' => $severity,
            'severityLabel' => ucfirst($severity),
            'title' => $longGone
                ? "{$pos['overdueCount']} books are overdue by a median of {$median} days"
                : "{$pos['overdueCount']} library loans are past their due date",
            'whatHappened' => $this->sentence([
                "{$pos['overdueCount']} of {$pos['activeLoans']} unreturned books have passed their due date, held by "
                    ."{$profile['borrowers']} borrowers.",
                $median !== null
                    ? "The median is {$median} days past due and the oldest is {$worst} days."
                    : null,
                $longGone
                    ? 'At that age these are not late returns. A book six months past its due date is stock the '
                        .'library has lost track of, and chasing it is a different task from sending a reminder.'
                    : null,
            ]),
            'whyItMatters' => $longGone
                ? 'Every one of these titles is counted as held and is not on the shelf, so the catalogue overstates '
                    .'what a reader can actually borrow by exactly this number.'
                : 'An overdue book is a title the catalogue lists and a reader cannot get, and the longer it stays '
                    .'out the less likely it is to come back at all.',
            'evidence' => array_values(array_filter([
                ['label' => 'Overdue', 'value' => (string) $pos['overdueCount']],
                ['label' => 'Unreturned loans', 'value' => (string) $pos['activeLoans']],
                ['label' => 'Borrowers holding them', 'value' => (string) $profile['borrowers']],
                $median !== null ? ['label' => 'Median days past due', 'value' => (string) $median] : null,
                $worst !== null ? ['label' => 'Oldest', 'value' => "{$worst} days"] : null,
                ['label' => 'Return rate this year', 'value' => "{$pos['returnRate']}%"],
            ])),
            'likelyCause' => $longGone
                ? 'Loans at this age usually belong to students who have since left, whose books were never '
                    .'collected at the point they stopped being reachable. The circulation record shows the age, '
                    .'not whether the borrower is still on the roll.'
                : null,
            'causeConfirmed' => false,
            'recommendation' => $longGone
                ? 'Check the oldest loans against this year’s roll before sending reminders — a reminder to a student '
                    .'who left last year recovers nothing, and writing the stock off is the honest alternative.'
                : 'Send reminders to the named borrowers while the loans are still recent enough to come back.',
            'owner' => 'Librarian',
            'priority' => $severity,
            'confidence' => ['band' => 'High', 'value' => 0.95],
            'affected' => [
                'count' => $pos['overdueCount'],
                'total' => $pos['activeLoans'],
                'unit' => 'loans',
            ],
            'impact' => [
                'value' => $pos['overdueCount'],
                'display' => (string) $pos['overdueCount'],
                'label' => 'titles off the shelf',
            ],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    private function dormantCatalogue(): array
    {
        $reach = $this->analytics->catalogueReach();
        if ($reach['titles'] === null || $reach['dormantShare'] === null) {
            return [];
        }
        if ($reach['titles'] < 500 || $reach['dormantShare'] < 40.0) {
            return [];
        }

        $position = $this->analytics->position();

        return [[
            'id' => "library-dormant-catalogue-{$this->syear}",
            'rule' => 'dormant_catalogue',
            'severity' => 'medium',
            'severityLabel' => 'Medium',
            'title' => "{$reach['neverBorrowed']} of {$reach['titles']} catalogued titles have never been borrowed",
            'whatHappened' => $this->sentence([
                "The catalogue holds {$reach['titles']} titles. {$reach['everBorrowed']} of them have been issued at "
                    ."least once in the whole circulation history; {$reach['neverBorrowed']} ({$reach['dormantShare']}%) "
                    .'never have.',
                $position !== null
                    ? "This year's circulation covered {$position['distinctTitles']} titles."
                    : null,
            ]),
            'whyItMatters' => 'Shelf space and acquisition budget are the library’s two scarce resources, and more '
                .'than a third of the catalogue is consuming both without reaching a reader. It is also the clearest '
                .'signal available about what the next acquisition should not be.',
            'evidence' => array_values(array_filter([
                ['label' => 'Titles catalogued', 'value' => (string) $reach['titles']],
                ['label' => 'Borrowed at least once', 'value' => (string) $reach['everBorrowed']],
                ['label' => 'Never borrowed', 'value' => (string) $reach['neverBorrowed']],
                ['label' => 'Dormant share', 'value' => "{$reach['dormantShare']}%"],
                $position !== null
                    ? ['label' => 'Titles issued this year', 'value' => (string) $position['distinctTitles']]
                    : null,
            ])),
            // Measured across the WHOLE history, and the caveat that goes with it.
            'likelyCause' => 'Reference stock, prescribed texts kept for a syllabus that changed, and donated books '
                .'all sit here legitimately. This figure counts titles that have never been issued, not titles that '
                .'should not have been bought.',
            'causeConfirmed' => false,
            'recommendation' => 'Read the dormant list by section before the next acquisition round — the sections '
                .'that never move are the ones the budget should stop going to.',
            'owner' => 'Librarian',
            'priority' => 'medium',
            'confidence' => ['band' => 'High', 'value' => 0.9],
            'affected' => ['count' => $reach['neverBorrowed'], 'total' => $reach['titles'], 'unit' => 'titles'],
            'impact' => [
                'value' => $reach['neverBorrowed'],
                'display' => (string) $reach['neverBorrowed'],
                'label' => 'titles never issued',
            ],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /** @param array<int,?string> $parts */
    private function sentence(array $parts): string
    {
        return implode(' ', array_filter($parts, static fn ($p) => $p !== null && $p !== ''));
    }
}

