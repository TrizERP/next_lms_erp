<?php

namespace App\Brain\Intelligence;

/**
 * Human-approved causal metadata for every rule in LmsSignalRules.
 *
 * WHY THIS IS A SEPARATE, EXPLICIT TABLE OF FACTS. In the reference Brain
 * (hp-enterprise-brain/app/Domain/Signals/RuleCauseMetadata.php) a signal only
 * becomes a hypothesis if a human has approved a root_cause_family and a
 * hypothesis_confidence for its rule. A rule with no approved cause produces a
 * signal and stops there. That gate is the difference between "the Brain
 * detected something" and "the Brain invented an explanation for it", and it is
 * reproduced here rather than dropped.
 *
 * So Reasoner reads THIS, and a rule absent from it yields a signal with
 * evidence and no hypothesis — visibly undetermined, which is the honest state.
 *
 * `confidence` is the ceiling the rule's own causal claim can reach, not the
 * final score. The final number is computed by Reasoner from how much evidence
 * actually corroborates the signal and how fresh it is, exactly as
 * hp-enterprise-brain/app/Domain/Reasoning/ReasoningService.php computes it.
 */
final class RuleCatalogue
{
    /**
     * rule_key => [family, confidence, hypothesis, action, category]
     *
     * `category` is the recommendation category and is constrained to the
     * reference's vocabulary: remediate | investigate | optimize | watch.
     */
    const CAUSES = [
        /* ------------------------------------------------ organization design */
        'department_without_head' => [
            'family' => 'ownership_gap',
            'confidence' => 0.90,
            'hypothesis' => 'Departments were created as a structural hierarchy during setup without assigning an accountable owner to each node, so escalation and approval have no destination.',
            'action' => 'Assign a head to every active department, starting with the departments that already carry staff.',
            'category' => 'remediate',
        ],
        'department_without_description' => [
            'family' => 'definition_gap',
            'confidence' => 0.75,
            'hypothesis' => 'The department tree was imported from a reference taxonomy rather than authored locally, so node names exist but their scope and remit were never written down.',
            'action' => 'Write a one-line remit for each department that carries staff; leave leaf taxonomy nodes that carry none until they are used.',
            'category' => 'optimize',
        ],
        'department_without_staff' => [
            'family' => 'structural_dormancy',
            'confidence' => 0.80,
            'hypothesis' => 'The department taxonomy is far larger than the organization that occupies it, because it was seeded from an occupational framework rather than grown from the actual staff roster.',
            'action' => 'Archive or collapse dormant departments so the active structure reflects the organization that exists.',
            'category' => 'optimize',
        ],
        'department_inactive_with_staff' => [
            'family' => 'lifecycle_inconsistency',
            'confidence' => 0.88,
            'hypothesis' => 'A department was deactivated without first reassigning the people posted to it, so those staff now report into a node that the organization considers closed.',
            'action' => 'Reassign staff out of deactivated departments, or reactivate the department if the closure was premature.',
            'category' => 'remediate',
        ],
        'staff_concentration' => [
            'family' => 'span_of_control_imbalance',
            'confidence' => 0.70,
            'hypothesis' => 'Staff are pooled into a small number of departments while the rest of the tree is empty, which concentrates supervision load on very few owners.',
            'action' => 'Review the largest departments for sub-division, and confirm each has a head with a workable span of control.',
            'category' => 'investigate',
        ],

        /* ------------------------------------------------------------- people */
        'person_without_department' => [
            'family' => 'ownership_gap',
            'confidence' => 0.92,
            'hypothesis' => 'Staff records were created through a route that does not require a department, so some people exist in the roster without a place in the structure.',
            'action' => 'Assign a department to every active staff record; these people are currently outside every departmental report.',
            'category' => 'remediate',
        ],
        'person_without_job_title' => [
            'family' => 'role_definition_gap',
            'confidence' => 0.85,
            'hypothesis' => 'The job-title master was never populated for this institute, so no staff record can carry a role — which blocks every capability, competency and progression view that keys off position.',
            'action' => 'Populate the job-title master and map each staff member to a title before enabling capability assessment.',
            'category' => 'remediate',
        ],
        'person_without_reporting_manager' => [
            'family' => 'ownership_gap',
            'confidence' => 0.85,
            'hypothesis' => 'The reporting line was never captured during staff onboarding, so the organization has a department tree but no person-to-person accountability chain.',
            'action' => 'Record who each staff member reports to during onboarding, and backfill the existing roster from the department heads.',
            'category' => 'remediate',
        ],
        'person_never_logged_in' => [
            'family' => 'adoption_gap',
            'confidence' => 0.80,
            'hypothesis' => 'Staff accounts were provisioned in bulk but credentials were never distributed or never used, so the roster describes an intended user base rather than an active one.',
            'action' => 'Run a credential distribution and first-login campaign before treating system data as operationally complete.',
            'category' => 'investigate',
        ],
        'person_incomplete_contact' => [
            'family' => 'data_quality',
            'confidence' => 0.88,
            'hypothesis' => 'Contact fields are optional on the staff form, so records can be saved without a reachable email or mobile.',
            'action' => 'Make email and mobile mandatory on the staff form and backfill the incomplete records.',
            'category' => 'remediate',
        ],
        'person_inactive_still_assigned' => [
            'family' => 'lifecycle_inconsistency',
            'confidence' => 0.86,
            'hypothesis' => 'Staff are deactivated without an offboarding step that clears their departmental posting, so inactive people still count towards department headcount.',
            'action' => 'Clear departmental assignment as part of offboarding so headcount reports exclude inactive staff.',
            'category' => 'remediate',
        ],

        /* ----------------------------------------------------------- students */
        'student_missing_contact' => [
            'family' => 'data_quality',
            'confidence' => 0.85,
            'hypothesis' => 'Guardian contact is not enforced at admission, so a subset of enrolled students cannot be reached by SMS or email.',
            'action' => 'Enforce a guardian mobile at admission and run a contact-collection drive for the affected students.',
            'category' => 'remediate',
        ],
        'student_missing_identity' => [
            'family' => 'compliance_exposure',
            'confidence' => 0.82,
            'hypothesis' => 'Statutory identifiers are captured on paper at admission but never entered into the ERP, leaving the digital record unable to support UDISE/AISHE returns.',
            'action' => 'Digitise the statutory identifiers held on the admission file before the next compliance return.',
            'category' => 'remediate',
        ],
        'student_missing_dob' => [
            'family' => 'data_quality',
            'confidence' => 0.84,
            'hypothesis' => 'Date of birth is optional on the student form, so age-based eligibility and cohort banding cannot be computed for every student.',
            'action' => 'Backfill date of birth from admission records; it gates age-eligibility and every cohort report.',
            'category' => 'remediate',
        ],
        'student_missing_enrollment_no' => [
            'family' => 'data_quality',
            'confidence' => 0.90,
            'hypothesis' => 'Enrollment numbers are allocated outside the system for some intake routes, so those students have no stable business key.',
            'action' => 'Allocate enrollment numbers inside the ERP for every intake route so each student has one stable key.',
            'category' => 'remediate',
        ],

        /* --------------------------------------------------------- attendance */
        'attendance_coverage_gap' => [
            'family' => 'process_adoption_gap',
            'confidence' => 0.90,
            'hypothesis' => 'Attendance is being taken on paper for most classes and entered into the ERP for only a few, so the digital record covers a small fraction of the roll.',
            'action' => 'Extend daily digital attendance to every section; the current record cannot support absence intervention at scale.',
            'category' => 'investigate',
        ],
        'student_absence_rate' => [
            'family' => 'engagement_risk',
            'confidence' => 0.78,
            'hypothesis' => 'Absence is concentrated rather than evenly spread, which usually indicates a cohort or transport factor rather than individual truancy.',
            'action' => 'Review absence by section and cohort before treating it as an individual-student issue.',
            'category' => 'investigate',
        ],
        'student_chronic_absentee' => [
            'family' => 'engagement_risk',
            'confidence' => 0.86,
            'hypothesis' => 'A small group of students carries a disproportionate share of total absence, which is the pattern that precedes dropout.',
            'action' => 'Open a guardian contact for each chronically absent student before the next assessment cycle.',
            'category' => 'remediate',
        ],
        'staff_attendance_open_punch' => [
            'family' => 'data_quality',
            'confidence' => 0.87,
            'hypothesis' => 'Staff punch in on the device but the punch-out is missed or not synced, leaving open shifts that make worked-hours totals unreliable.',
            'action' => 'Close the open punch records and add an end-of-day check so worked-hours totals can be trusted for payroll.',
            'category' => 'remediate',
        ],

        /* ---------------------------------------------------------- academics */
        'result_coverage_gap' => [
            'family' => 'process_adoption_gap',
            'confidence' => 0.88,
            'hypothesis' => 'Marks are recorded in the ERP for a pilot group only, so academic intelligence has no representative base to reason over.',
            'action' => 'Complete marks entry for the current assessment cycle before relying on academic analytics.',
            'category' => 'investigate',
        ],
        'result_low_performance' => [
            'family' => 'academic_risk',
            'confidence' => 0.75,
            'hypothesis' => 'The mean recorded score sits below the passing band, which points at either a genuine attainment problem or an incomplete marks entry that under-counts.',
            'action' => 'Verify marks entry completeness for the affected subjects, then review teaching plans where the low mean is confirmed.',
            'category' => 'investigate',
        ],

        /* ---------------------------------------------------------- homework */
        'homework_non_submission' => [
            'family' => 'engagement_risk',
            'confidence' => 0.80,
            'hypothesis' => 'Non-submission is concentrated in particular subjects or sections rather than spread evenly, which points at workload or clarity rather than individual effort.',
            'action' => 'Review non-submission by subject and section, and follow up with the sections carrying the highest share.',
            'category' => 'investigate',
        ],

        /* -------------------------------------------------------------- fees */
        'fee_collection_coverage' => [
            'family' => 'process_adoption_gap',
            'confidence' => 0.85,
            'hypothesis' => 'Fee receipts are being issued outside the ERP for most students, so the collection record inside the system is not a basis for reconciliation.',
            'action' => 'Route all fee collection through the ERP so outstanding balances can be computed from one ledger.',
            'category' => 'investigate',
        ],

        /* -------------------------------------------------------- complaints */
        'complaint_unresolved' => [
            'family' => 'sla_breach',
            'confidence' => 0.88,
            'hypothesis' => 'Complaints are closed verbally without the resolution being written back, so the record shows work as outstanding when it may not be.',
            'action' => 'Record a resolution against each open complaint, or close it explicitly, so the backlog figure is real.',
            'category' => 'remediate',
        ],

        /* ------------------------------------------ change over time (trends) */
        'attendance_decline' => [
            'family' => 'attendance_risk',
            'confidence' => 0.82,
            'hypothesis' => 'A month-on-month attendance fall of this size is almost never school-wide; it usually concentrates in one or two classes, and a transport, timetable or cohort factor sits behind it.',
            'action' => 'Compare attendance by class for the two months and follow up the classes carrying the fall, rather than issuing a school-wide notice.',
            'category' => 'investigate',
        ],
        'class_below_attendance_baseline' => [
            'family' => 'attendance_risk',
            'confidence' => 0.85,
            'hypothesis' => 'A single class sitting well below the school baseline points at something specific to that class — its timetable slot, its teacher allocation, or the catchment its students travel from.',
            'action' => 'Review this class with its class teacher, and contact the guardians of its most absent students before the next assessment.',
            'category' => 'remediate',
        ],
        'homework_decline' => [
            'family' => 'engagement_risk',
            'confidence' => 0.78,
            'hypothesis' => 'Falling submission usually precedes falling attendance and results, and typically starts in one or two subjects rather than across the timetable.',
            'action' => 'Check submission by subject for the two months and speak to the subject teachers carrying the drop.',
            'category' => 'investigate',
        ],
        'subject_below_homework_baseline' => [
            'family' => 'engagement_risk',
            'confidence' => 0.80,
            'hypothesis' => 'One subject lagging the school baseline generally reflects the volume or clarity of what is being set, not the students receiving it.',
            'action' => 'Review the homework being set in this subject for volume and clarity with the subject teacher.',
            'category' => 'investigate',
        ],
        'fee_collection_decline' => [
            'family' => 'collection_risk',
            'confidence' => 0.80,
            'hypothesis' => 'Month-on-month collection falls are normally concentrated in a small number of overdue accounts rather than spread across the roll.',
            'action' => 'Pull the overdue accounts and prioritise follow-up on the largest balances first.',
            'category' => 'remediate',
        ],

        /* ------------------------------------------------------ capabilities */
        'capability_unassigned' => [
            'family' => 'activation_gap',
            'confidence' => 0.86,
            'hypothesis' => 'A large capability library was loaded from a reference framework but never mapped to departments or people, so it describes a possible organization rather than this one.',
            'action' => 'Map the capabilities that matter to the departments that hold them; an unmapped library produces no assessment and no gap.',
            'category' => 'optimize',
        ],
    ];

    /** @return array{family: string, confidence: float, hypothesis: string, action: string, category: string}|null */
    public static function for(string $ruleKey): ?array
    {
        return self::CAUSES[$ruleKey] ?? null;
    }

    /** @return array<int, string> */
    public static function approvedRuleKeys(): array
    {
        return array_keys(self::CAUSES);
    }
}
