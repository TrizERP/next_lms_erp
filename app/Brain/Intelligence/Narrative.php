<?php

namespace App\Brain\Intelligence;

/**
 * Turns a stored signal into something a principal can read in one glance.
 *
 * WHY THIS EXISTS AS ITS OWN LAYER. The database speaks in `rule_key`,
 * `related_entity_type`, `hypothesis_confidence: 0.8234` and a JSON metadata
 * blob. Those are the right names for the engine and the wrong words for a
 * person running a school. Putting the translation in the front end would mean
 * every screen inventing its own phrasing and drifting apart; putting it in the
 * rules would mix detection with presentation. So it lives here: one place that
 * decides how the Brain SPEAKS, separate from how it THINKS.
 *
 * THE CARD CONTRACT — every signal answers the same seven questions, in order:
 *
 *   1. What is happening?      title
 *   2. By how much?            headline (value, comparison, direction)
 *   3. What exactly happened?  whatHappened
 *   4. Why should I care?      whyItMatters
 *   5. How do you know?        evidence — 2 to 4 labelled figures, never JSON
 *   6. Why is it happening?    likelyCause  (only where a cause is approved)
 *   7. What do I do?           recommendation + owner + priority
 *
 * CONFIDENCE IS A WORD, NOT A DECIMAL. "0.8234" invites a reader to treat a
 * modelled score as a measured probability. "High" carries the same ordering
 * with none of the false precision; the number stays available underneath for
 * anyone who wants it.
 *
 * NOTHING HERE INVENTS A FACT. Every figure is read out of the signal's own
 * metadata, which the rules computed from vivek_erp. Where a rule has no
 * approved cause, likelyCause is null and the card says the cause is not yet
 * confirmed — it does not guess one.
 */
final class Narrative
{
    /**
     * Who should act, per root-cause family.
     *
     * A recommendation with no owner is a wish. These are ROLES rather than
     * named people, because the Brain knows which function owns a problem but
     * not who currently holds that post in this school.
     */
    private const OWNERS = [
        'ownership_gap' => 'School Administrator',
        'definition_gap' => 'School Administrator',
        'structural_dormancy' => 'School Administrator',
        'lifecycle_inconsistency' => 'HR / Admin',
        'span_of_control_imbalance' => 'Principal',
        'role_definition_gap' => 'HR / Admin',
        'adoption_gap' => 'IT / Systems Administrator',
        'data_quality' => 'Office Administrator',
        'compliance_exposure' => 'Compliance Officer',
        'process_adoption_gap' => 'Principal',
        'engagement_risk' => 'Academic Coordinator',
        'academic_risk' => 'Academic Coordinator',
        'attendance_risk' => 'Academic Coordinator',
        'sla_breach' => 'Front Office',
        'activation_gap' => 'Principal',
        'collection_risk' => 'Accounts Officer',
    ];

    /**
     * The plain-language frame for each rule.
     *
     * `title` and `what` accept the placeholders the rules put in metadata, so
     * the sentence carries this school's real numbers rather than a generic
     * label. `matters` is the consequence — the half a count never conveys.
     */
    private const FRAMES = [
        'department_without_head' => [
            'title' => 'Most departments have no one accountable for them',
            'what' => ':affected of :total departments have no head assigned.',
            'matters' => 'When a department has no head, approvals and escalations have nowhere to go, and nobody owns the result.',
            'metric' => 'departments without a head',
        ],
        'department_without_description' => [
            'title' => 'Departments have no written remit',
            'what' => ':affected of :total departments have no description of what they are responsible for.',
            'matters' => 'Staff cannot be held to a remit that was never written down, and new joiners have nothing to read.',
            'metric' => 'departments with no remit',
        ],
        'department_without_staff' => [
            'title' => 'The department structure is far larger than the school',
            'what' => ':affected of :total departments have no staff in them at all.',
            'matters' => 'An oversized structure makes every departmental report harder to read and hides the units that actually matter.',
            'metric' => 'empty departments',
        ],
        'department_inactive_with_staff' => [
            'title' => 'Staff are posted to closed departments',
            'what' => ':affected departments are marked closed but still hold staff.',
            'matters' => 'Those staff report into a unit the school considers shut, so they fall outside every active-department report.',
            'metric' => 'closed departments holding staff',
        ],
        'staff_concentration' => [
            'title' => 'One department holds most of the staff',
            'what' => ':affected of :total assigned staff sit in a single department.',
            'matters' => 'A single head is supervising a very large team, which is where workload and appraisal quality usually slip first.',
            'metric' => 'staff in the largest department',
        ],
        'person_without_department' => [
            'title' => 'Some staff are not attached to any department',
            'what' => ':affected of :total staff have no department assigned.',
            'matters' => 'These people are missing from every departmental headcount, workload and appraisal report.',
            'metric' => 'staff with no department',
        ],
        'person_without_job_title' => [
            'title' => 'No staff member has a job title recorded',
            'what' => ':affected of :total staff have no job title.',
            'matters' => 'Without titles the school cannot assess capability, plan progression, or tell who is qualified for which duty.',
            'metric' => 'staff with no job title',
        ],
        'person_without_reporting_manager' => [
            'title' => 'There is no reporting line between staff',
            'what' => ':affected of :total staff have no reporting manager recorded.',
            'matters' => 'The school has a department chart but no chain of accountability, so nothing escalates to a named person.',
            'metric' => 'staff with no manager',
        ],
        'person_never_logged_in' => [
            'title' => 'Staff accounts are not being used',
            'what' => ':affected of :total staff accounts have never been signed in to.',
            'matters' => 'Anything the school records in the system reflects the office, not the staff — so attendance, marks and homework data stay thin.',
            'metric' => 'accounts never used',
        ],
        'person_incomplete_contact' => [
            'title' => 'Some staff cannot be contacted',
            'what' => ':affected of :total staff have no working email or phone number on file.',
            'matters' => 'These staff cannot be reached by any school-wide message, including urgent ones.',
            'metric' => 'staff not contactable',
        ],
        'person_inactive_still_assigned' => [
            'title' => 'Former staff still count towards department headcount',
            'what' => ':affected of :total staff are marked inactive but are still posted to a department.',
            'matters' => 'Headcount and workload figures are overstated wherever these people still appear.',
            'metric' => 'inactive staff still posted',
        ],
        'student_missing_contact' => [
            'title' => 'Some students have no contact number',
            'what' => ':affected of :total students have no phone number on any guardian field.',
            'matters' => 'These families cannot be reached about absence, fees or an emergency.',
            'metric' => 'students not contactable',
        ],
        'student_missing_identity' => [
            'title' => 'Statutory student IDs are missing',
            'what' => ':affected of :total students have no statutory identifier recorded.',
            'matters' => 'Government returns such as UDISE cannot be filed from the system as it stands.',
            'metric' => 'students missing a statutory ID',
        ],
        'student_missing_dob' => [
            'title' => 'Some students have no date of birth',
            'what' => ':affected of :total students have no date of birth recorded.',
            'matters' => 'Age eligibility, cohort banding and several statutory returns all depend on this field.',
            'metric' => 'students with no date of birth',
        ],
        'student_missing_enrollment_no' => [
            'title' => 'Some students have no enrolment number',
            'what' => ':affected of :total students have no enrolment number.',
            'matters' => 'Without a stable number these students are hard to match across marks, fees and attendance records.',
            'metric' => 'students with no enrolment number',
        ],
        'attendance_coverage_gap' => [
            'title' => 'Attendance is only being recorded for a fraction of the school',
            'what' => 'Attendance exists for :covered of :total students.',
            'matters' => 'The school cannot spot absence patterns or intervene early for students it is not tracking.',
            'metric' => 'students with no attendance record',
        ],
        'student_absence_rate' => [
            'title' => 'Absence is running high where attendance is recorded',
            'what' => ':share% of all recorded attendance marks are an absence or leave.',
            'matters' => 'Sustained absence at this level is the strongest early warning the school has for falling results.',
            'metric' => 'absence rate',
        ],
        'student_chronic_absentee' => [
            'title' => 'A group of students is persistently absent',
            'what' => ':affected students have :threshold or more recorded absences.',
            'matters' => 'Concentrated absence in a small group is the pattern that most often precedes dropout.',
            'metric' => 'persistently absent students',
        ],
        'attendance_decline' => [
            'title' => 'Attendance has fallen since last month',
            'what' => 'Attendance moved from :previousRate% to :currentRate% between :previousPeriod and :currentPeriod.',
            'matters' => 'A month-on-month fall of this size usually points at a specific class or cohort rather than the school as a whole.',
            'metric' => 'attendance rate',
        ],
        'class_below_attendance_baseline' => [
            'title' => 'One class is well below the school attendance baseline',
            'what' => ':className is at :currentRate% against a school baseline of :baseline%.',
            'matters' => 'Attendance concentrated in one class points at a timetable, transport or teaching factor the school can actually fix.',
            'metric' => 'class attendance rate',
        ],
        'staff_attendance_open_punch' => [
            'title' => 'Staff shifts are left open',
            'what' => ':affected staff attendance records have a sign-in but no sign-out.',
            'matters' => 'Worked-hours totals cannot be trusted for payroll while these shifts stay open.',
            'metric' => 'shifts with no sign-out',
        ],
        'result_coverage_gap' => [
            'title' => 'Marks are recorded for very few students',
            'what' => 'Marks exist for :covered of :total students.',
            'matters' => 'Academic performance cannot be assessed, compared or acted on from a sample this small.',
            'metric' => 'students with no marks',
        ],
        'result_low_performance' => [
            'title' => 'Average marks are below the pass band',
            'what' => 'The mean recorded score is :meanPercentage% against a pass mark of :passBand%.',
            'matters' => 'Either attainment is genuinely low or marks entry is incomplete — both need checking before results are relied on.',
            'metric' => 'mean score',
        ],
        'homework_non_submission' => [
            'title' => 'Homework is going unsubmitted',
            'what' => ':affected of :total homework assignments are not marked complete.',
            'matters' => 'Non-submission clusters by subject or class, and it usually shows up in assessment results a term later.',
            'metric' => 'assignments outstanding',
        ],
        'homework_decline' => [
            'title' => 'Homework submission has dropped',
            'what' => 'Submission moved from :previousRate% to :currentRate% between :previousPeriod and :currentPeriod.',
            'matters' => 'A falling submission rate is an early indicator of disengagement, ahead of attendance and results.',
            'metric' => 'submission rate',
        ],
        'subject_below_homework_baseline' => [
            'title' => 'One subject is well below the homework baseline',
            'what' => ':subject is at :currentRate% submission against a school baseline of :baseline%.',
            'matters' => 'A single subject lagging usually reflects workload or unclear instructions rather than the students.',
            'metric' => 'subject submission rate',
        ],
        'fee_collection_coverage' => [
            'title' => 'Fees are being collected outside the system',
            'what' => 'Fee receipts exist for :covered of :total students.',
            'matters' => 'Outstanding balances cannot be calculated, so the school has no reliable view of what it is owed.',
            'metric' => 'students with no receipt',
        ],
        'fee_collection_decline' => [
            'title' => 'Fee collection has slowed',
            'what' => 'Collection moved from :previousAmount to :currentAmount between :previousPeriod and :currentPeriod.',
            'matters' => 'A slowdown this size affects cash flow and usually concentrates in a few overdue accounts.',
            'metric' => 'collected this month',
        ],
        'complaint_unresolved' => [
            'title' => 'Complaints are open with no recorded resolution',
            'what' => ':affected of :total complaints have no resolution written against them.',
            'matters' => 'Families see no response, and the school cannot tell which issues are genuinely still open.',
            'metric' => 'complaints unresolved',
        ],
        'capability_unassigned' => [
            'title' => 'The skills framework is not mapped to anyone',
            'what' => ':affected of :total capabilities are not assigned to any department or person.',
            'matters' => 'A framework nobody is mapped to produces no skills assessment and reveals no gaps.',
            'metric' => 'capabilities unmapped',
        ],
    ];

    /**
     * Present one signal row as a card.
     *
     * @param  array<string, mixed>  $signal  a hpbrain_signals row with metadata already decoded
     * @return array<string, mixed>
     */
    public static function forSignal(array $signal): array
    {
        $metadata = is_array($signal['metadata'] ?? null) ? $signal['metadata'] : [];
        $ruleKey = (string) ($signal['rule_key'] ?? ($metadata['rule'] ?? ''));
        $cause = RuleCatalogue::for($ruleKey);
        $frame = self::FRAMES[$ruleKey] ?? null;

        $severity = strtolower((string) ($signal['severity'] ?? 'low'));
        $confidence = (float) ($signal['confidence'] ?? 0);

        return [
            'id' => (string) ($signal['id'] ?? ''),
            'severity' => $severity,
            'severityLabel' => self::severityLabel($severity),
            'title' => $frame
                ? self::fill($frame['title'], $metadata)
                : self::fallbackTitle($metadata, $signal),
            'headline' => self::headline($ruleKey, $metadata, $frame),
            'whatHappened' => $frame
                ? self::fill($frame['what'], $metadata)
                : (string) ($metadata['title'] ?? 'A rule matched against the school record.'),
            'whyItMatters' => $frame['matters'] ?? null,
            'evidence' => self::evidencePoints($ruleKey, $metadata),
            'likelyCause' => $cause['hypothesis'] ?? null,
            'causeConfirmed' => $cause !== null,
            'recommendation' => $cause['action'] ?? null,
            'owner' => self::OWNERS[$cause['family'] ?? ''] ?? 'Principal',
            'priority' => self::priorityLabel($severity, (string) ($signal['priority'] ?? 'normal')),
            'confidence' => [
                'band' => self::confidenceBand($confidence),
                'value' => round($confidence, 2),
            ],
            'affected' => [
                'count' => $metadata['affectedCount'] ?? null,
                'total' => $metadata['totalCount'] ?? null,
                'unit' => $metadata['unit'] ?? null,
            ],
            'raisedAt' => (string) ($signal['created_date'] ?? ''),
            // Kept for the engine and for support, never rendered as the primary
            // label: a person should not have to read `rule_key` to use this.
            'technical' => [
                'ruleKey' => $ruleKey,
                'classification' => (string) ($signal['classification'] ?? ''),
                'source' => (string) ($signal['source'] ?? ''),
                'rootCauseFamily' => $cause['family'] ?? null,
                'status' => (string) ($signal['status'] ?? ''),
            ],
        ];
    }

    /**
     * The one number the card leads with, and what it is measured against.
     *
     * Rules that compare against a previous period or a baseline lead with the
     * comparison, because the change IS the finding. Rules that count a
     * condition lead with the share, because "607 of 609" says more than "607".
     *
     * @return array<string, mixed>|null
     */
    private static function headline(string $ruleKey, array $metadata, ?array $frame): ?array
    {
        $label = $frame['metric'] ?? 'affected';

        // A period-over-period rule: lead with the movement.
        if (isset($metadata['currentRate'], $metadata['previousRate'])) {
            $change = round(((float) $metadata['currentRate']) - ((float) $metadata['previousRate']), 1);

            return [
                'value' => self::pct($metadata['currentRate']),
                'label' => $label,
                'change' => $change,
                'changeLabel' => sprintf('vs %s%% last period', self::num($metadata['previousRate'])),
                'direction' => $change < 0 ? 'down' : ($change > 0 ? 'up' : 'flat'),
            ];
        }

        // A baseline rule: lead with the gap against the school's own average.
        if (isset($metadata['currentRate'], $metadata['baseline'])) {
            $gap = round(((float) $metadata['currentRate']) - ((float) $metadata['baseline']), 1);

            return [
                'value' => self::pct($metadata['currentRate']),
                'label' => $label,
                'change' => $gap,
                'changeLabel' => sprintf('vs school baseline %s%%', self::num($metadata['baseline'])),
                'direction' => $gap < 0 ? 'down' : ($gap > 0 ? 'up' : 'flat'),
            ];
        }

        if (isset($metadata['currentAmount'], $metadata['previousAmount'])) {
            $change = (float) ($metadata['changePercent'] ?? 0);

            return [
                'value' => self::money($metadata['currentAmount']),
                'label' => $label,
                'change' => $change,
                'changeLabel' => sprintf('vs %s last period', self::money($metadata['previousAmount'])),
                'direction' => $change < 0 ? 'down' : ($change > 0 ? 'up' : 'flat'),
            ];
        }

        if (isset($metadata['affectedCount'])) {
            $affected = (int) $metadata['affectedCount'];
            $total = isset($metadata['totalCount']) ? (int) $metadata['totalCount'] : null;

            return [
                'value' => number_format($affected),
                'label' => $label,
                'change' => null,
                'changeLabel' => $total ? sprintf('of %s %s', number_format($total), self::unitWord($metadata, $total)) : null,
                'direction' => 'flat',
            ];
        }

        return null;
    }

    /**
     * Two to four labelled figures — the proof, without a JSON blob in sight.
     *
     * @return array<int, array{label: string, value: string, note?: string}>
     */
    private static function evidencePoints(string $ruleKey, array $metadata): array
    {
        $points = [];

        if (isset($metadata['currentRate'])) {
            $points[] = ['label' => 'Now', 'value' => self::pct($metadata['currentRate']),
                'note' => isset($metadata['currentPeriod']) ? self::monthName((string) $metadata['currentPeriod']) : null];
        }
        if (isset($metadata['previousRate'])) {
            $points[] = ['label' => 'Previous', 'value' => self::pct($metadata['previousRate']),
                'note' => isset($metadata['previousPeriod']) ? self::monthName((string) $metadata['previousPeriod']) : null];
        }
        if (isset($metadata['baseline'])) {
            $points[] = ['label' => 'School baseline', 'value' => self::pct($metadata['baseline'])];
        }
        if (isset($metadata['currentAmount'])) {
            $points[] = ['label' => 'Collected', 'value' => self::money($metadata['currentAmount']),
                'note' => isset($metadata['currentPeriod']) ? self::monthName((string) $metadata['currentPeriod']) : null];
        }
        if (isset($metadata['previousAmount'])) {
            $points[] = ['label' => 'Previous month', 'value' => self::money($metadata['previousAmount'])];
        }
        if (isset($metadata['affectedCount'])) {
            $affected = (int) $metadata['affectedCount'];
            $points[] = [
                'label' => 'Affected',
                'value' => number_format($affected).' '.self::unitWord($metadata, $affected),
            ];
        }
        if (isset($metadata['totalCount']) && ! isset($metadata['currentRate'])) {
            $total = (int) $metadata['totalCount'];
            $points[] = ['label' => 'Out of', 'value' => number_format($total).' '.self::unitWord($metadata, $total)];
        }
        if (isset($metadata['coveredCount'])) {
            $covered = (int) $metadata['coveredCount'];
            $points[] = ['label' => 'Covered', 'value' => number_format($covered).' '.self::unitWord($metadata, $covered)];
        }
        if (isset($metadata['students'])) {
            $points[] = ['label' => 'Students involved', 'value' => number_format((int) $metadata['students'])];
        }
        if (isset($metadata['share']) && ! isset($metadata['currentRate'])) {
            $points[] = ['label' => 'Share', 'value' => self::pct(((float) $metadata['share']) * 100)];
        }
        if (isset($metadata['meanPercentage'])) {
            $points[] = ['label' => 'Mean score', 'value' => self::pct($metadata['meanPercentage'])];
        }
        if (isset($metadata['passBand'])) {
            $points[] = ['label' => 'Pass mark', 'value' => self::pct($metadata['passBand'])];
        }
        if (isset($metadata['worstCount'])) {
            $points[] = ['label' => 'Highest absence', 'value' => number_format((int) $metadata['worstCount']).' days'];
        }

        // Four figures is the most a reader takes in at a glance; past that the
        // card stops being scannable and becomes another table.
        return array_slice(array_map(
            fn ($p) => array_filter($p, fn ($v) => $v !== null && $v !== ''),
            $points
        ), 0, 4);
    }

    /**
     * The unit, agreeing with the number in front of it.
     *
     * "1 classes" undermines a card that is otherwise asking to be trusted, and
     * the rules legitimately store a plural unit because they usually report
     * many. Singularising here keeps the rules simple and the prose correct.
     */
    private static function unitWord(array $metadata, ?int $count = null): string
    {
        $unit = (string) ($metadata['unit'] ?? 'records');

        if ($count !== 1) {
            return $unit;
        }

        return match (true) {
            str_ends_with($unit, 'ies') => substr($unit, 0, -3).'y',
            str_ends_with($unit, 'sses') => substr($unit, 0, -2),
            str_ends_with($unit, 's') && ! str_ends_with($unit, 'ss') => substr($unit, 0, -1),
            default => $unit,
        };
    }

    private static function fallbackTitle(array $metadata, array $signal): string
    {
        $title = trim((string) ($metadata['title'] ?? ''));
        if ($title !== '') {
            return $title;
        }

        // Last resort only: turn the classification into words rather than
        // showing a snake_case token.
        return ucfirst(str_replace('_', ' ', (string) ($signal['classification'] ?? 'Finding')));
    }

    /**
     * Short names the sentence templates use for the metadata the rules write.
     *
     * The rules store `affectedCount`, which is the right name in a data
     * structure and a clumsy one inside a sentence. These aliases let a template
     * read ":affected of :total departments" while the engine keeps its own
     * vocabulary — without this the placeholders render literally, which is
     * exactly the leak of internal naming this class exists to prevent.
     */
    private const ALIASES = [
        'affectedCount' => 'affected',
        'totalCount' => 'total',
        'coveredCount' => 'covered',
    ];

    private static function fill(string $template, array $metadata): string
    {
        foreach (self::ALIASES as $source => $alias) {
            if (array_key_exists($source, $metadata) && ! array_key_exists($alias, $metadata)) {
                $metadata[$alias] = $metadata[$source];
            }
        }

        $replacements = [];
        foreach ($metadata as $key => $value) {
            if (is_array($value)) {
                continue;
            }
            $formatted = match (true) {
                $key === 'share' => self::num(((float) $value) * 100),
                is_int($value) || (is_numeric($value) && floor((float) $value) == $value && abs((float) $value) >= 1000) => number_format((float) $value),
                in_array($key, ['currentPeriod', 'previousPeriod'], true) => self::monthName((string) $value),
                in_array($key, ['currentAmount', 'previousAmount'], true) => self::money($value),
                is_numeric($value) => self::num($value),
                default => (string) $value,
            };
            $replacements[':'.$key] = $formatted;
        }

        return strtr($template, $replacements);
    }

    private static function severityLabel(string $severity): string
    {
        return match ($severity) {
            'critical' => 'Critical',
            'high' => 'High',
            'medium' => 'Medium',
            default => 'Low',
        };
    }

    private static function priorityLabel(string $severity, string $priority): string
    {
        if ($severity === 'critical') {
            return 'Critical';
        }
        if ($severity === 'high' || $priority === 'high') {
            return 'High';
        }

        return $severity === 'medium' ? 'Medium' : 'Low';
    }

    /**
     * A word, not a decimal.
     *
     * The bands match the recommendation engine's own floor: below 0.45 a
     * finding is not acted on, so anything under it must not read as actionable.
     */
    public static function confidenceBand(float $confidence): string
    {
        return match (true) {
            $confidence >= 0.80 => 'High',
            $confidence >= 0.60 => 'Medium',
            $confidence >= 0.45 => 'Low',
            default => 'Very low',
        };
    }

    public static function monthName(string $period): string
    {
        $timestamp = strtotime($period.'-01');

        return $timestamp ? date('F Y', $timestamp) : $period;
    }

    private static function pct($value): string
    {
        return self::num($value).'%';
    }

    private static function num($value): string
    {
        $float = (float) $value;

        return rtrim(rtrim(number_format($float, 1), '0'), '.');
    }

    /** Indian grouping and ₹, because that is what the receipts are in. */
    public static function money($value): string
    {
        $amount = (float) $value;

        if ($amount >= 10000000) {
            return '₹'.self::num($amount / 10000000).'Cr';
        }
        if ($amount >= 100000) {
            return '₹'.self::num($amount / 100000).'L';
        }

        return '₹'.number_format($amount);
    }
}
