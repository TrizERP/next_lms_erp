<?php

namespace App\Brain\Intelligence;

/**
 * What the correspondence register means, and what is worth somebody's morning.
 *
 * ── WHAT A RULE HERE MAY NOT DO ─────────────────────────────────────────────
 *
 * IT MAY NOT READ WHAT A LETTER SAID. `title` and `description` are free text —
 * 4,476 distinct descriptions across one institute's rows — and a correspondence
 * register holds legal notices, staff disciplinary matters and letters about
 * individual children. No rule below groups by them, quotes them or counts
 * their values. Every figure is a count of rows.
 *
 * IT MAY NOT TREAT A YEAR BOUNDARY AS A DUPLICATE. Inward numbers restart each
 * academic year, which is how a register is meant to work. Counting distinct
 * numbers across the whole table reported 409 duplicates at one institute where
 * the true within-year figure is five, and a rule built on that would have sent
 * a records office looking for four hundred collisions that do not exist.
 *
 * IT MAY NOT CALL A QUIET REGISTER A BUSY ONE. The outward rule fires on the
 * RATIO between the two registers, not on a raw count: an institute that logs
 * twelve letters out against two hundred in has a different problem from one
 * that logs none at all, and the finding says which.
 */
final class CorrespondenceSignalRules
{
    /**
     * Below this ratio of outward to inward, the outward register is not being
     * kept rather than merely being quieter.
     */
    private const OUTWARD_RATIO_THRESHOLD = 0.05;

    /** Above this share of entries with no scan, the register points at paper. */
    private const NO_ATTACHMENT_THRESHOLD = 10.0;

    public function __construct(
        private readonly CorrespondenceIntelligence $analytics,
        private readonly ?string $syear,
    ) {
    }

    /** @return array{findings:array<int,array<string,mixed>>,ruleStatus:array<int,array<string,mixed>>} */
    public function run(): array
    {
        $shape = $this->analytics->shape();

        if ($shape['inward'] === 0 && $shape['outward'] === 0) {
            return ['findings' => [], 'ruleStatus' => []];
        }

        $rules = [
            'outward_register_unused' => [
                'Correspondence logged in but not out',
                fn () => $this->outwardRegisterUnused(),
            ],
            'unresolved_file_location' => [
                'Entries filed to a location that is not on file',
                fn () => $this->unresolvedFileLocation(),
            ],
            'no_file_location_recorded' => [
                'Entries recording no file location at all',
                fn () => $this->noFileLocationRecorded(),
            ],
            'duplicate_inward_numbers' => [
                'Inward numbers issued more than once in the year',
                fn () => $this->duplicateInwardNumbers(),
            ],
            'missing_attachment' => [
                'Inward entries holding no copy of what arrived',
                fn () => $this->missingAttachment(),
            ],
        ];

        $findings = [];
        $ruleStatus = [];

        foreach ($rules as $key => [$label, $check]) {
            $raised = $check();
            $ruleStatus[] = ['key' => $key, 'label' => $label, 'checked' => true, 'raised' => $raised !== []];

            foreach ($raised as $finding) {
                $findings[] = $finding + ['rule' => $key];
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

    /* --------------------------------------------------- the one-way register */

    /**
     * A register that records everything arriving and nothing leaving.
     *
     * This is the most consequential thing this module can say. The question a
     * correspondence register exists to answer is whether what came in was ever
     * answered, and that question needs both halves.
     *
     * @return array<int,array<string,mixed>>
     */
    private function outwardRegisterUnused(): array
    {
        if (! $this->analytics->coverage()['available']) {
            return [];
        }

        $shape = $this->analytics->shape();
        $inward = $shape['inward'];
        $outward = $shape['outward'];

        if ($inward === 0) {
            return [];
        }

        $ratio = $outward / $inward;
        if ($ratio > self::OUTWARD_RATIO_THRESHOLD) {
            return [];
        }

        $none = $outward === 0;
        $perOutward = $outward > 0 ? round($inward / $outward) : null;

        return [[
            'id' => "correspondence-outward-unused-{$this->syear}",
            'severity' => $none ? 'high' : 'medium',
            'severityLabel' => $none ? 'High' : 'Medium',
            'title' => $none
                ? "{$inward} letters were logged in this year and none was logged out"
                : "{$inward} letters were logged in this year against {$outward} logged out",
            'whatHappened' => $this->sentence([
                "The inward register holds {$inward} entries for {$this->syear}.",
                $none
                    ? 'The outward register holds none at all for the same year.'
                    : "The outward register holds {$outward} — roughly one entry out for every {$perOutward} in.",
                'Both registers are in the same system and both are scoped to the same year, so this is a difference '
                    .'in how they are used rather than in what is available.',
            ]),
            'whyItMatters' => 'A correspondence register exists to answer one question: did we reply, and when. '
                .'Recording only what arrives makes that question unanswerable — there is no record that a notice was '
                .'responded to, no date to prove a deadline was met, and nothing to produce if the reply is ever '
                .'disputed. The inward half being this complete is what shows the office has the habit; it is only '
                .'the outgoing half that is not being kept.',
            'evidence' => array_values(array_filter([
                ['label' => 'Inward entries this year', 'value' => (string) $inward],
                ['label' => 'Outward entries this year', 'value' => (string) $outward],
                $perOutward !== null
                    ? ['label' => 'Inward per outward', 'value' => (string) $perOutward]
                    : null,
                ['label' => 'Academic year', 'value' => (string) $this->syear],
            ])),
            'likelyCause' => 'Logging something inward is forced by the act of receiving it — somebody is holding the '
                .'envelope. Sending something out has no such moment, so the outward register is filled only when '
                .'somebody remembers to go back to it after the letter has gone.',
            'causeConfirmed' => false,
            'recommendation' => 'Decide whether the outward register is meant to be kept. If it is, the moment to '
                .'write the entry is when the reply is signed, not afterwards; if it is not, the inward register '
                .'should not be relied on to show that anything was answered.',
            'owner' => 'Correspondence office',
            'priority' => $none ? 'high' : 'medium',
            'confidence' => ['band' => 'High', 'value' => 0.9],
            'affected' => ['count' => $inward, 'total' => $inward, 'unit' => 'inward entries'],
            'impact' => ['value' => $inward, 'display' => (string) $inward, 'label' => 'letters with no recorded reply'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /* ------------------------------------------------------ where the paper is */

    /** @return array<int,array<string,mixed>> */
    private function unresolvedFileLocation(): array
    {
        $shape = $this->analytics->shape();
        $unresolved = $shape['locationsUnresolved'];

        if ($shape['inward'] === 0 || $unresolved === 0) {
            return [];
        }

        $share = round($unresolved / $shape['inward'] * 100, 1);

        return [[
            'id' => "correspondence-unresolved-location-{$this->syear}",
            'severity' => $share >= 25.0 ? 'high' : 'medium',
            'severityLabel' => $share >= 25.0 ? 'High' : 'Medium',
            'title' => "{$unresolved} of {$shape['inward']} inward entries are filed to a location that is not on file",
            'whatHappened' => $this->sentence([
                "{$unresolved} inward entries this year ({$share}%) name a physical file location with no row in "
                    .'this institute’s file-location master.',
                "{$shape['locationsUsed']} distinct file locations are named across the year’s entries.",
                'The ids are not resolved against any other institute’s master.',
            ]),
            'whyItMatters' => 'The file location is the only thing on the record that says where the physical paper '
                .'went. Without it the entry proves a letter arrived and gives no way to produce it — which is the '
                .'one thing a correspondence register is asked for when a document is actually needed.',
            'evidence' => [
                ['label' => 'Entries with an unknown file location', 'value' => (string) $unresolved],
                ['label' => 'Inward entries this year', 'value' => (string) $shape['inward']],
                ['label' => 'Share', 'value' => "{$share}%"],
                ['label' => 'Distinct file locations named', 'value' => (string) $shape['locationsUsed']],
            ],
            'likelyCause' => 'File locations renamed, merged or deleted from the master after entries had already '
                .'been filed against them, or entries imported carrying location ids from a previous system. The '
                .'entry holds an id and the master holds nothing at it; which came first is not recorded.',
            'causeConfirmed' => false,
            'recommendation' => 'Reconcile the location ids these entries actually use against the file-location '
                .'master before the next audit asks for a document. Restoring the master rows is usually cheaper '
                .'than re-filing the entries.',
            'owner' => 'Correspondence office',
            'priority' => $share >= 25.0 ? 'high' : 'medium',
            'confidence' => ['band' => 'High', 'value' => 0.95],
            'affected' => ['count' => $unresolved, 'total' => $shape['inward'], 'unit' => 'entries'],
            'impact' => ['value' => $unresolved, 'display' => (string) $unresolved, 'label' => 'entries whose paper cannot be located'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /**
     * Entries that record no file location at all.
     *
     * A DIFFERENT DEFECT FROM THE ONE ABOVE, and deliberately a separate rule.
     * An id pointing at a master row that was deleted is fixed by restoring the
     * master; an id that was never set is fixed at the desk, by the person
     * logging the letter. Counting them together would hide the larger of the
     * two — at one institute 923 of 1,361 entries in a single year carry no
     * location, against zero broken references.
     *
     * @return array<int,array<string,mixed>>
     */
    private function noFileLocationRecorded(): array
    {
        $shape = $this->analytics->shape();
        $missing = $shape['locationsMissing'];

        if ($shape['inward'] === 0 || $missing === 0) {
            return [];
        }

        $share = round($missing / $shape['inward'] * 100, 1);
        $all = $missing === $shape['inward'];

        return [[
            'id' => "correspondence-no-location-{$this->syear}",
            'severity' => $share >= 50.0 ? 'high' : 'medium',
            'severityLabel' => $share >= 50.0 ? 'High' : 'Medium',
            'title' => $all
                ? "No inward entry this year records where the paper was filed ({$shape['inward']} entries)"
                : "{$missing} of {$shape['inward']} inward entries record no file location at all ({$share}%)",
            'whatHappened' => $this->sentence([
                "{$missing} inward entries this year ({$share}%) carry no physical file location — the field is "
                    .'empty rather than pointing at something that has since been deleted.',
                $shape['locationsUnresolved'] > 0
                    ? "A further {$shape['locationsUnresolved']} name a location this institute's master does not hold."
                    : 'Every entry that does name a location names one this institute holds.',
                "{$shape['locationsUsed']} distinct file locations are in use across the year’s entries.",
            ]),
            'whyItMatters' => 'The register records that a letter arrived and, for these entries, nothing about where '
                .'it went. When the document is actually needed — an audit, a dispute, a legal deadline — the entry '
                .'proves it exists and gives nobody anywhere to look. That is a worse position than not having '
                .'logged it, because the log implies it can be produced.',
            'evidence' => array_values(array_filter([
                ['label' => 'Entries with no file location', 'value' => (string) $missing],
                ['label' => 'Inward entries this year', 'value' => (string) $shape['inward']],
                ['label' => 'Share', 'value' => "{$share}%"],
                ['label' => 'Distinct file locations in use', 'value' => (string) $shape['locationsUsed']],
                $shape['locationsUnresolved'] > 0
                    ? ['label' => 'Also naming an unknown location', 'value' => (string) $shape['locationsUnresolved']]
                    : null,
            ])),
            'likelyCause' => 'The file location is not required to save an entry, so it is filled when the paper is '
                .'filed straight away and left empty when it is put aside to file later. Nothing returns to it '
                .'afterwards.',
            'causeConfirmed' => false,
            'recommendation' => 'Require a file location before an inward entry can be saved. Back-filling the '
                .'existing entries is only possible while somebody still remembers where the paper went, so it is '
                .'worth doing for the current year before it is worth doing for any earlier one.',
            'owner' => 'Correspondence office',
            'priority' => $share >= 50.0 ? 'high' : 'medium',
            'confidence' => ['band' => 'High', 'value' => 0.95],
            'affected' => ['count' => $missing, 'total' => $shape['inward'], 'unit' => 'entries'],
            'impact' => ['value' => $missing, 'display' => (string) $missing, 'label' => 'entries with no filing location'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /* ------------------------------------------------------------ the numbering */

    /** @return array<int,array<string,mixed>> */
    private function duplicateInwardNumbers(): array
    {
        $shape = $this->analytics->shape();
        $dupes = $shape['duplicateNumbers'];

        if ($dupes === 0) {
            return [];
        }

        return [[
            'id' => "correspondence-duplicate-numbers-{$this->syear}",
            'severity' => 'low',
            'severityLabel' => 'Low',
            'title' => $dupes === 1
                ? 'One inward number was issued twice this year'
                : "{$dupes} inward numbers were issued more than once this year",
            'whatHappened' => $this->sentence([
                "{$dupes} inward ".($dupes === 1 ? 'number names' : 'numbers name').' more than one entry within '
                    ."{$this->syear}, affecting {$shape['duplicatedRows']} rows of {$shape['inward']}.",
                'Counted within the year only: inward numbering restarts each academic year by design, so numbers '
                    .'repeating across years are correct and are not counted here.',
                $shape['missingNumbers'] > 0
                    ? "{$shape['missingNumbers']} entries this year carry no inward number at all."
                    : null,
            ]),
            'whyItMatters' => 'The inward number is the handle everything else uses to refer to a letter — a reply, a '
                .'file note, a minute. Where one number names two entries, a reference to it is ambiguous, and the '
                .'ambiguity only surfaces when somebody is trying to find a specific document.',
            'evidence' => array_values(array_filter([
                ['label' => 'Numbers issued more than once', 'value' => (string) $dupes],
                ['label' => 'Entries affected', 'value' => (string) $shape['duplicatedRows']],
                ['label' => 'Inward entries this year', 'value' => (string) $shape['inward']],
                $shape['missingNumbers'] > 0
                    ? ['label' => 'Entries with no number at all', 'value' => (string) $shape['missingNumbers']]
                    : null,
                ['label' => 'Counting grain', 'value' => 'within this academic year', 'note' => 'Numbering restarts each year by design'],
            ])),
            'likelyCause' => 'A number typed by hand rather than issued by the system, so two entries made on the '
                .'same day or by two people can take the same one. Nothing in the schema enforces uniqueness.',
            'causeConfirmed' => false,
            'recommendation' => 'Issue the inward number from the system rather than accepting it as typed input. The '
                .'affected entries are few enough to renumber by hand once that is in place.',
            'owner' => 'Correspondence office',
            'priority' => 'low',
            'confidence' => ['band' => 'High', 'value' => 0.95],
            'affected' => ['count' => $shape['duplicatedRows'], 'total' => $shape['inward'], 'unit' => 'entries'],
            'impact' => ['value' => $dupes, 'display' => (string) $dupes, 'label' => 'ambiguous inward numbers'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /* ------------------------------------------------------------ the scan */

    /** @return array<int,array<string,mixed>> */
    private function missingAttachment(): array
    {
        if (! $this->analytics->coverage()['available']) {
            return [];
        }

        $shape = $this->analytics->shape();
        $without = $shape['withoutAttachment'];

        if ($shape['inward'] === 0 || $without === 0) {
            return [];
        }

        $share = round($without / $shape['inward'] * 100, 1);
        if ($share < self::NO_ATTACHMENT_THRESHOLD) {
            return [];
        }

        return [[
            'id' => "correspondence-missing-attachment-{$this->syear}",
            'severity' => 'low',
            'severityLabel' => 'Low',
            'title' => "{$without} of {$shape['inward']} inward entries hold no copy of what arrived ({$share}%)",
            'whatHappened' => "{$without} inward entries this year record that something arrived without a scanned "
                .'document attached to them, leaving the register pointing at paper that has to be found physically.',
            'whyItMatters' => 'An entry with a scan can be answered from a desk. An entry without one can only be '
                .'answered by somebody walking to the file, and only if the file location on it resolves — which for '
                .'some of these entries it does not.',
            'evidence' => [
                ['label' => 'Entries with no scan', 'value' => (string) $without],
                ['label' => 'Inward entries this year', 'value' => (string) $shape['inward']],
                ['label' => 'Share', 'value' => "{$share}%"],
                ['label' => 'Entries with a scan', 'value' => (string) $shape['withAttachment']],
            ],
            'likelyCause' => 'Scanning is a separate step from logging, so it is done when the office is quiet and '
                .'skipped when it is not. Nothing requires an attachment before an entry can be saved.',
            'causeConfirmed' => false,
            'recommendation' => 'Scan at the point of logging rather than as a later pass. The entries already '
                .'logged without one are worth back-filling only where the file location resolves, since the rest '
                .'cannot be found to scan.',
            'owner' => 'Correspondence office',
            'priority' => 'low',
            'confidence' => ['band' => 'High', 'value' => 0.9],
            'affected' => ['count' => $without, 'total' => $shape['inward'], 'unit' => 'entries'],
            'impact' => ['value' => $without, 'display' => (string) $without, 'label' => 'entries with no copy held'],
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
