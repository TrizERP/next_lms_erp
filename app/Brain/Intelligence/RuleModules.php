<?php

namespace App\Brain\Intelligence;

/**
 * Which module each Brain rule belongs to.
 *
 * WHY THIS EXISTS. The intelligence loop is tenant-wide: one pipeline produces
 * every signal, hypothesis and recommendation for the institute. That is right
 * for the Enterprise Brain, whose question is "what is happening here", and
 * wrong for a module's own Intelligence tab, whose question is "what is
 * happening in Fees" — showing a bursar the department-ownership signals
 * answers nothing they came for.
 *
 * So the signals are attributed to modules, and a module's tab shows its own.
 *
 * MOST OF THIS IS READ, NOT DECIDED. The newer rules already carry their module
 * in their key — `mod_hostel_unassigned_bed_numbers`, `mod_result_weak_subject`
 * — so MODULE_TOKENS only translates that token into the module key the rest of
 * the platform uses (config/platform_services.php, the same vocabulary the
 * category rows carry in `platform_module_key`). One taxonomy, not a second
 * one for the Brain.
 *
 * The older rules predate that convention and are attributed one by one in
 * RULES. They are listed rather than pattern-matched because the patterns lie:
 * `student_chronic_absentee` is an attendance signal that happens to be about a
 * student, and `staff_attendance_open_punch` is attendance rather than HR. A
 * prefix rule would put both in the wrong tab with great confidence.
 *
 * FAMILIES is the fallback for a sibling rule added later — a new `fee_*` rule
 * is a Fees rule — and it is consulted last, so it can never override an
 * explicit attribution above.
 *
 * A rule that matches nothing returns '', and a module with no rules is told it
 * has none. Both are deliberate: `capability_unassigned` belongs to a
 * capability module the platform registry does not declare, and inventing a
 * home for it would put it somewhere a person would never think to look.
 */
final class RuleModules
{
    /**
     * `mod_<token>_…` => the platform module key.
     *
     * The tokens are what the rule keys actually use; the values are
     * config/platform_services.php module keys.
     */
    private const MODULE_TOKENS = [
        'academic' => 'academics',
        'admissions' => 'admissions',
        'attendance' => 'attendance',
        'communication' => 'communication',
        'fees' => 'fees',
        // Homework is an Academics component (academics.homework), not a module
        // of its own.
        'homework' => 'academics',
        'hostel' => 'hostel',
        'hr' => 'hr',
        'inventory' => 'inventory',
        'library' => 'library',
        // The result rules are the Examination module's: marks, results and the
        // classes behind them.
        'result' => 'examination',
        'student' => 'students',
        'transport' => 'transport',
    ];

    /**
     * The rules that predate the `mod_` convention, attributed individually.
     *
     * Read against what each rule actually measures — see
     * App\Brain\Intelligence\RuleCatalogue for the hypothesis and action behind
     * each one — not against the word its key starts with.
     */
    private const RULES = [
        // Organization structure and the staff posted into it. The platform
        // registry calls this HR & Payroll.
        'department_without_head' => 'hr',
        'department_without_description' => 'hr',
        'department_without_staff' => 'hr',
        'department_inactive_with_staff' => 'hr',
        'staff_concentration' => 'hr',
        'person_without_department' => 'hr',
        'person_without_job_title' => 'hr',
        'person_without_reporting_manager' => 'hr',
        'person_never_logged_in' => 'hr',
        'person_incomplete_contact' => 'hr',
        'person_inactive_still_assigned' => 'hr',

        // The student record itself.
        'student_missing_contact' => 'students',
        'student_missing_identity' => 'students',
        'student_missing_dob' => 'students',
        'student_missing_enrollment_no' => 'students',

        // Attendance, whoever it is about. Two of these are keyed 'student_'
        // and one 'staff_', and all three are attendance signals.
        'attendance_coverage_gap' => 'attendance',
        'attendance_decline' => 'attendance',
        'class_below_attendance_baseline' => 'attendance',
        'student_absence_rate' => 'attendance',
        'student_chronic_absentee' => 'attendance',
        'staff_attendance_open_punch' => 'attendance',

        // Marks and results.
        'result_coverage_gap' => 'examination',
        'result_low_performance' => 'examination',

        // Homework is academics.
        'homework_non_submission' => 'academics',
        'homework_decline' => 'academics',
        'subject_below_homework_baseline' => 'academics',

        // Fees, including the fees-intelligence family.
        'fee_collection_coverage' => 'fees',
        'fee_collection_shortfall' => 'fees',
        'fee_collection_decline' => 'fees',
        'fee_receipt_coverage' => 'fees',
        'fee_outstanding_concentration' => 'fees',
        'fee_overdue_backlog' => 'fees',
        'fee_cycle_decline' => 'fees',
        'fee_class_collection_gap' => 'fees',
        'fee_head_collection_gap' => 'fees',
        'fee_payment_mode_concentration' => 'fees',
        'fee_cancellation_pressure' => 'fees',
        'fee_reconciliation_gap' => 'fees',

        // Complaint handling is a Front desk component.
        'complaint_unresolved' => 'front_desk',

        // Capability activation belongs to a capability module the platform
        // registry does not declare. Left unattributed on purpose.
        'capability_unassigned' => '',
    ];

    /**
     * Last resort for a rule added after this file was written: the family its
     * key starts with. Longest prefix wins, so a more specific family can be
     * added above a more general one without reordering anything.
     */
    private const FAMILIES = [
        'attendance_' => 'attendance',
        'capability_' => '',
        'class_' => 'attendance',
        'complaint_' => 'front_desk',
        'department_' => 'hr',
        'fee_' => 'fees',
        'homework_' => 'academics',
        'person_' => 'hr',
        'result_' => 'examination',
        'staff_' => 'hr',
        'student_' => 'students',
        'subject_' => 'academics',
    ];

    /** The module a rule belongs to, or '' when nothing declares one. */
    public static function moduleFor(string $ruleKey): string
    {
        $ruleKey = strtolower(trim($ruleKey));

        if ($ruleKey === '') {
            return '';
        }

        if (array_key_exists($ruleKey, self::RULES)) {
            return self::RULES[$ruleKey];
        }

        if (str_starts_with($ruleKey, 'mod_')) {
            $token = explode('_', substr($ruleKey, 4))[0] ?? '';

            return self::MODULE_TOKENS[$token] ?? '';
        }

        $best = '';
        $bestLength = 0;

        foreach (self::FAMILIES as $prefix => $module) {
            if (str_starts_with($ruleKey, $prefix) && strlen($prefix) > $bestLength) {
                $best = $module;
                $bestLength = strlen($prefix);
            }
        }

        return $best;
    }

    /**
     * The subset of `$ruleKeys` belonging to one module.
     *
     * Classifying the keys that exist, rather than building a LIKE clause per
     * module, is what keeps the individual attributions above authoritative: a
     * pattern broad enough to catch `student_missing_dob` would also catch
     * `student_chronic_absentee`, which is attendance's.
     *
     * @param  iterable<string>  $ruleKeys
     * @return list<string>
     */
    public static function filter(iterable $ruleKeys, string $module): array
    {
        $module = strtolower(trim($module));

        if ($module === '') {
            return [];
        }

        $matched = [];

        foreach ($ruleKeys as $ruleKey) {
            if (self::moduleFor((string) $ruleKey) === $module) {
                $matched[] = (string) $ruleKey;
            }
        }

        return array_values(array_unique($matched));
    }

    /**
     * Whether any rule at all is attributed to a module.
     *
     * Answers "does this module have intelligence yet?" without waiting for a
     * pipeline run, so a module that has rules but has not fired any signal is
     * told that, rather than being told it has no intelligence.
     */
    public static function isDeclared(string $module): bool
    {
        $module = strtolower(trim($module));

        if ($module === '') {
            return false;
        }

        if (in_array($module, self::RULES, true) || in_array($module, self::MODULE_TOKENS, true)) {
            return true;
        }

        return in_array($module, self::FAMILIES, true);
    }

    /**
     * Every rule the catalogue declares for a module, whether firing or not.
     *
     * @return list<string>
     */
    public static function declaredRules(string $module): array
    {
        return self::filter(array_keys(RuleCatalogue::CAUSES), $module);
    }
}
