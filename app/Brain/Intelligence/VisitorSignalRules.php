<?php

namespace App\Brain\Intelligence;

/**
 * What the gate register means, and what is worth somebody's morning.
 *
 * ── WHAT A RULE HERE MAY NOT DO ─────────────────────────────────────────────
 *
 * IT MAY NOT TIME A VISIT. `in_time` and `out_time` are bare times on an
 * inconsistent clock — 12.0% of closed visits at the richest institute record an
 * exit before the entry. No rule here computes a duration, a dwell time or an
 * average visit length, and the contradicting rows are reported as a record
 * check instead.
 *
 * IT MAY NOT NAME A VISITOR, OR A HOST. The register holds a name, a contact
 * number, an email and a photograph of everybody who has come through the gate.
 * None of it is read. `to_meet` is not read either: it holds a staff id on most
 * rows and a typed human name on the rest, so its values are a member of staff's
 * name often enough that showing them is not defensible. Every figure below is a
 * count or a share.
 *
 * IT MAY NOT RESOLVE A TYPE ACROSS TENANTS. One institute's entire register
 * names visitor-type id 10, which belongs to a DIFFERENT institute. Resolving it
 * would put another school's reference data on this screen and would turn a real
 * finding into a chart that looks fine.
 */
final class VisitorSignalRules
{
    /**
     * Above this share of the year's visits left open, the register is not
     * recording exits rather than merely missing a few.
     */
    private const OPEN_SHARE_THRESHOLD = 15.0;

    /** Above this share of closed visits contradicting themselves, the clock is unusable. */
    private const CONTRADICTION_THRESHOLD = 2.0;

    public function __construct(
        private readonly VisitorIntelligence $analytics,
        private readonly ?string $syear,
    ) {
    }

    /** @return array{findings:array<int,array<string,mixed>>,ruleStatus:array<int,array<string,mixed>>} */
    public function run(): array
    {
        $shape = $this->analytics->shape();

        // Nothing recorded and no broken row to report: silence is correct.
        if ($shape['visits'] === 0 && $shape['badDates'] === 0) {
            return ['findings' => [], 'ruleStatus' => []];
        }

        $rules = [
            'visits_never_closed' => [
                'Visitors signed in and never signed out',
                fn () => $this->visitsNeverClosed(),
            ],
            'visitor_type_not_in_master' => [
                'Visits filed under a type this institute does not hold',
                fn () => $this->visitorTypeNotInMaster(),
            ],
            'exit_before_entry' => [
                'Visits whose recorded exit precedes the entry',
                fn () => $this->exitBeforeEntry(),
            ],
            'appointment_type_unused' => [
                'Whether a visitor was expected is not being recorded',
                fn () => $this->appointmentTypeUnused(),
            ],
            'unusable_visit_date' => [
                'Visitor rows that belong to no academic year',
                fn () => $this->unusableVisitDate(),
            ],
        ];

        $findings = [];
        $ruleStatus = [];

        foreach ($rules as $key => [$label, $check]) {
            $raised = $check();
            $ruleStatus[] = ['key' => $key, 'label' => $label, 'checked' => true, 'raised' => $raised !== []];

            // Tagged HERE rather than in each rule, so a rule added later cannot
            // forget to. ModuleSignalBridge dedupes on (tenant, rule, year) and a
            // finding with no rule key cannot be deduped.
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

    /* ------------------------------------------------------ who is on site */

    /**
     * Visitors the register never signed out.
     *
     * This is the most consequential thing this module can say. The one question
     * a gate register exists to answer is "who is on site right now", and a
     * visit with no exit time means the register's own answer is that this
     * person never left.
     *
     * @return array<int,array<string,mixed>>
     */
    private function visitsNeverClosed(): array
    {
        if (! $this->analytics->coverage()['available']) {
            return [];
        }

        $shape = $this->analytics->shape();
        $open = $shape['open'];
        $visits = $shape['visits'];

        if ($open === 0) {
            return [];
        }

        $share = round($open / $visits * 100, 1);
        if ($share < self::OPEN_SHARE_THRESHOLD) {
            return [];
        }

        // Which kinds of visitor account for it, where the types resolve. Never
        // named individuals — the type is a category, not a person.
        $byType = $this->analytics->byType();
        usort($byType, static fn ($a, $b) => $b['openVisits'] <=> $a['openVisits']);
        $worst = array_values(array_filter($byType, static fn ($t) => $t['openVisits'] > 0));

        $all = $open === $visits;

        return [[
            'id' => "visitor-never-closed-{$this->syear}",
            'severity' => $share >= 50.0 ? 'high' : 'medium',
            'severityLabel' => $share >= 50.0 ? 'High' : 'Medium',
            'title' => $all
                ? "No visitor signed in this year was ever signed out ({$visits} visits)"
                : "{$open} of {$visits} visitors were never signed out ({$share}%)",
            'whatHappened' => $this->sentence([
                "{$visits} visitors were recorded at the gate this year and {$open} of them ({$share}%) have no exit "
                    .'time against their row.',
                $worst !== []
                    ? 'The largest share is '.$worst[0]['label'].' — '.$worst[0]['openVisits'].' of '
                        .$worst[0]['visits'].' ('.$worst[0]['openShare'].'%) left open.'
                    : null,
                'How long any of them stayed cannot be established from this table, so this is a count of visits '
                    .'that were never closed rather than of people still on site.',
            ]),
            'whyItMatters' => 'A visitor register answers one question: who is on the premises. A visit with no exit '
                .'time means the register’s own answer is that this person never left — so a roll call in an '
                .'evacuation, or a check at the end of the day, would be looking for people who went home hours ago '
                .'and would not notice anybody who genuinely had not.',
            'evidence' => array_merge(
                [
                    ['label' => 'Visits with no exit recorded', 'value' => (string) $open],
                    ['label' => 'Visits this year', 'value' => (string) $visits],
                    ['label' => 'Share left open', 'value' => "{$share}%"],
                    ['label' => 'Visits closed', 'value' => (string) $shape['closed']],
                ],
                array_map(static fn ($t) => [
                    'label' => $t['label'],
                    'value' => "{$t['openVisits']} of {$t['visits']} left open",
                    'note' => "{$t['openShare']}% of this visitor type",
                ], array_slice($worst, 0, 4)),
            ),
            'likelyCause' => 'Signing out is a second trip to the desk that nothing enforces, so it is done when the '
                .'desk is quiet and skipped when it is not. The register records the arrival because the visitor is '
                .'standing there; it records the departure only if somebody remembers.',
            'causeConfirmed' => false,
            'recommendation' => 'Decide what the gate register is for. If it is to answer who is on site, sign-out '
                .'has to be enforced at the point of exit rather than left to memory — and until it is, no figure '
                .'from this register can be used to say who was in the building.',
            'owner' => 'Front desk',
            'priority' => $share >= 50.0 ? 'high' : 'medium',
            'confidence' => ['band' => 'High', 'value' => 0.95],
            'affected' => ['count' => $open, 'total' => $visits, 'unit' => 'visits'],
            'impact' => ['value' => $open, 'display' => (string) $open, 'label' => 'visits never closed'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /* -------------------------------------------------- the reference chain */

    /**
     * Visits naming a visitor type that this institute's own master does not
     * hold.
     *
     * Measured, one institute's ENTIRE register names type id 10, which is a row
     * belonging to a different institute, while its own six types sit unused.
     * This runs below the coverage floor for the same reason the hostel's
     * structural rules do: a row pointing at a reference record that does not
     * exist is true of the records whether there are twenty of them or two
     * thousand.
     *
     * @return array<int,array<string,mixed>>
     */
    private function visitorTypeNotInMaster(): array
    {
        $shape = $this->analytics->shape();
        $unresolved = $shape['unresolvedRows'];

        if ($shape['visits'] === 0 || $unresolved === 0) {
            return [];
        }

        $share = round($unresolved / $shape['visits'] * 100, 1);
        $all = $unresolved === $shape['visits'];

        return [[
            'id' => "visitor-type-unresolved-{$this->syear}",
            'severity' => $all ? 'high' : 'medium',
            'severityLabel' => $all ? 'High' : 'Medium',
            'title' => $all
                ? "Every visit this year is filed under a visitor type this institute does not hold ({$unresolved})"
                : "{$unresolved} of {$shape['visits']} visits are filed under a visitor type this institute does not hold",
            'whatHappened' => $this->sentence([
                "{$unresolved} of this year's {$shape['visits']} visits ({$share}%) name a visitor-type id with no "
                    .'row in this institute’s own visitor_type master.',
                $shape['typesResolved'] === 0
                    ? 'Not one of the types used this year resolves, so the register cannot say what kind of visitor '
                        .'anybody was.'
                    : "{$shape['typesResolved']} of the {$shape['typesUsed']} types used this year do resolve.",
                'The ids are not looked up against any other institute’s master, which is where ids like these '
                    .'usually turn out to exist.',
            ]),
            'whyItMatters' => 'The visitor type is the only thing on this record that says what kind of person came '
                .'through the gate — a parent, a contractor, a vendor, a former pupil. Without it the register is a '
                .'list of arrivals with no categories, so it cannot answer who is allowed where, which visits need a '
                .'safeguarding check, or how much of the day the gate spends on deliveries. It also means the type '
                .'breakdown on this screen is deliberately empty rather than wrong.',
            'evidence' => [
                ['label' => 'Visits naming an unknown type', 'value' => (string) $unresolved],
                ['label' => 'Visits this year', 'value' => (string) $shape['visits']],
                ['label' => 'Share', 'value' => "{$share}%"],
                ['label' => 'Distinct types used', 'value' => (string) $shape['typesUsed']],
                ['label' => 'Of those, held by this institute', 'value' => (string) $shape['typesResolved']],
            ],
            'likelyCause' => 'A register seeded or copied from another institute’s configuration, so the rows carry '
                .'that institute’s type ids while this one’s own types were created separately and never used. The '
                .'visit row holds an id and this institute’s master holds nothing at it; which came first is not '
                .'recorded.',
            'causeConfirmed' => false,
            'recommendation' => 'Map the type ids the register actually uses onto this institute’s own visitor types, '
                .'then point new visits at the local ones. Until that is done the kind of visitor is not recoverable '
                .'for any of these rows and no visitor report can group by it.',
            'owner' => 'Front desk',
            'priority' => $all ? 'high' : 'medium',
            'confidence' => ['band' => 'High', 'value' => 0.95],
            'affected' => ['count' => $unresolved, 'total' => $shape['visits'], 'unit' => 'visits'],
            'impact' => ['value' => $unresolved, 'display' => (string) $unresolved, 'label' => 'visits with no usable type'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /* ------------------------------------------------------------ the clock */

    /** @return array<int,array<string,mixed>> */
    private function exitBeforeEntry(): array
    {
        $shape = $this->analytics->shape();
        $bad = $shape['exitBeforeEntry'];

        if ($bad === 0 || $shape['closed'] === 0) {
            return [];
        }

        $share = round($bad / $shape['closed'] * 100, 1);
        if ($share < self::CONTRADICTION_THRESHOLD) {
            return [];
        }

        return [[
            'id' => "visitor-exit-before-entry-{$this->syear}",
            'severity' => 'medium',
            'severityLabel' => 'Medium',
            'title' => "{$bad} of {$shape['closed']} closed visits record the visitor leaving before they arrived",
            'whatHappened' => $this->sentence([
                "{$bad} of the {$shape['closed']} visits closed this year ({$share}%) carry an exit time earlier "
                    .'than their entry time.',
                'Both columns are bare times with no date and nothing recording whether they are on a 12- or a '
                    .'24-hour clock, so an entry at 13:00 and an exit at 01:53 cannot be told from a genuine error.',
            ]),
            'whyItMatters' => 'This is why no visit duration appears anywhere on this screen. The times are stored, '
                .'and they look usable, so any report built on them would produce a confident average that is wrong '
                .'on at least one visit in fifty and silently twelve hours out on others. The safer reading is that '
                .'this register records ORDER of arrival reliably and elapsed time not at all.',
            'evidence' => [
                ['label' => 'Closed visits with exit before entry', 'value' => (string) $bad],
                ['label' => 'Closed visits this year', 'value' => (string) $shape['closed']],
                ['label' => 'Share', 'value' => "{$share}%"],
                ['label' => 'Visit duration reported anywhere', 'value' => 'no', 'note' => 'Deliberately not computed from these columns'],
            ],
            'likelyCause' => 'A desk entering times by hand from a clock or a watch, with no enforced format, so some '
                .'rows are written as they appear on a 12-hour face. Nothing in the schema records which convention '
                .'a row used.',
            'causeConfirmed' => false,
            'recommendation' => 'Capture entry and exit as full timestamps taken from the system clock rather than as '
                .'typed times. It is the only change that makes any duration figure from this register defensible; '
                .'correcting the existing rows is not possible, because the intended clock was never recorded.',
            'owner' => 'Front desk',
            'priority' => 'medium',
            'confidence' => ['band' => 'High', 'value' => 0.9],
            'affected' => ['count' => $bad, 'total' => $shape['closed'], 'unit' => 'visits'],
            'impact' => ['value' => $bad, 'display' => (string) $bad, 'label' => 'self-contradicting visit records'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /* ------------------------------------------------------- the front desk */

    /**
     * Whether a visitor was expected is a field nobody fills.
     *
     * Raised only when the field is effectively UNUSED across a material number
     * of visits. A school where 70% of visitors arrive unannounced is not a
     * finding — that is what a school gate is like. A school that records
     * nothing at all about whether anybody was expected has lost the one field
     * that separates a booked meeting from a stranger at the door.
     *
     * @return array<int,array<string,mixed>>
     */
    private function appointmentTypeUnused(): array
    {
        if (! $this->analytics->coverage()['available']) {
            return [];
        }

        $shape = $this->analytics->shape();
        $visits = $shape['visits'];
        $recorded = $shape['appointmentRecorded'];
        $prior = $shape['prior'];

        // Two distinct failures, and only these two. Anything in between is a
        // distribution, not a defect.
        $neverRecorded = $recorded === 0;
        $neverExpected = $recorded > 0 && $prior === 0;

        if (! $neverRecorded && ! $neverExpected) {
            return [];
        }

        return [[
            'id' => "visitor-appointment-unused-{$this->syear}",
            'severity' => 'low',
            'severityLabel' => 'Low',
            'title' => $neverRecorded
                ? "Whether a visitor was expected was not recorded on any of this year's {$visits} visits"
                : "All {$visits} visits this year are recorded as unannounced arrivals",
            'whatHappened' => $neverRecorded
                ? "None of this year's {$visits} visits carries an appointment type, so the register does not "
                    .'distinguish a booked meeting from somebody arriving at the gate.'
                : "All {$recorded} visits carrying an appointment type this year are marked as walk-ins, and not one "
                    .'is recorded as a prior appointment. The field is populated and only ever takes one value.',
            'whyItMatters' => 'Whether somebody was expected is what separates a meeting from a stranger at the door. '
                .'It decides who should have been pre-checked, whose arrival somebody is waiting for, and which '
                .'visits a safeguarding policy applies to. Recorded on every row it is useful; recorded the same way '
                .'on every row it carries no information at all.',
            'evidence' => array_values(array_filter([
                ['label' => 'Visits this year', 'value' => (string) $visits],
                ['label' => 'Carrying an appointment type', 'value' => (string) $recorded],
                ['label' => 'Recorded as a prior appointment', 'value' => (string) $prior],
                $recorded > 0
                    ? ['label' => 'Recorded as a walk-in', 'value' => (string) $shape['direct']]
                    : null,
            ])),
            'likelyCause' => $neverRecorded
                ? 'A field left at its default on a form the desk fills in a hurry, or a sign-in screen that does not '
                    .'ask. Nothing records which.'
                : 'Appointments booked somewhere other than this system — a diary, a phone call or an email — so '
                    .'everybody arriving is entered at the desk as though unannounced.',
            'causeConfirmed' => false,
            'recommendation' => 'Either record the appointment type at sign-in and use it, or stop collecting it. A '
                .'field that is always blank, or always the same value, makes the register look as though it '
                .'distinguishes expected visitors when it does not.',
            'owner' => 'Front desk',
            'priority' => 'low',
            'confidence' => ['band' => 'High', 'value' => 0.9],
            'affected' => ['count' => $visits, 'total' => $visits, 'unit' => 'visits'],
            'impact' => ['value' => $visits, 'display' => (string) $visits, 'label' => 'visits with no expectation recorded'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /* -------------------------------------------------------------- the date */

    /**
     * Rows that belong to no academic year at all.
     *
     * Runs below the coverage floor deliberately: a row with a zero date is
     * inside no year window, so if this rule required coverage it could never
     * fire for the very rows it is about.
     *
     * @return array<int,array<string,mixed>>
     */
    private function unusableVisitDate(): array
    {
        $bad = $this->analytics->shape()['badDates'];

        if ($bad === 0) {
            return [];
        }

        return [[
            'id' => "visitor-unusable-date-{$this->syear}",
            'severity' => 'low',
            'severityLabel' => 'Low',
            // The count is register-wide and says so in the title. These rows
            // are inside NO year window, so scoping them to the year being
            // viewed would report them nowhere at all — but a title that did
            // not say so would read as "2 of this year's visits".
            'title' => $bad === 1
                ? 'One visitor record in this register belongs to no academic year'
                : "{$bad} visitor records in this register belong to no academic year",
            'whatHappened' => "{$bad} row".($bad === 1 ? '' : 's').' in this institute’s visitor register '
                .($bad === 1 ? 'carries' : 'carry').' a zero or empty visit date. They fall inside no academic '
                .'year’s term dates, so they appear on this screen’s counts nowhere and in no year-scoped visitor '
                .'report.',
            'whyItMatters' => 'A visit with no date cannot be found again. If somebody needs to establish who was on '
                .'site on a particular day — which is the only reason this register is kept — these rows will not be '
                .'in the answer, and nothing on a normal report would reveal that they are missing.',
            'evidence' => [
                ['label' => 'Rows with no usable visit date', 'value' => (string) $bad],
                ['label' => 'Scope', 'value' => 'whole register', 'note' => 'Counted across all years, since these rows belong to none'],
            ],
            'likelyCause' => 'Rows created without the date field being set, or imported from a source that had no '
                .'date for them. MySQL stores the result as a zero date rather than rejecting it.',
            'causeConfirmed' => false,
            'recommendation' => 'Find these rows and either date them from whatever else is on them or remove them. '
                .'They are invisible to every year-scoped view, so they will not be noticed any other way.',
            'owner' => 'Front desk',
            'priority' => 'low',
            'confidence' => ['band' => 'High', 'value' => 0.95],
            'affected' => ['count' => $bad, 'total' => null, 'unit' => 'rows'],
            'impact' => ['value' => $bad, 'display' => (string) $bad, 'label' => 'rows in no academic year'],
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
