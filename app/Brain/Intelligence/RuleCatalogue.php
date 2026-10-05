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

        /* ----------------------------------------------- fees intelligence */
        /*
         * Causes for App\Brain\Intelligence\FeesSignalRules.
         *
         * Every `action` below is a REVIEW OR A PROCESS CHANGE, never a promise
         * about money. "Prioritise", "check", "review" are what the evidence
         * supports; "recover ₹X" is not, because nothing in this database
         * establishes what a follow-up call collects. The Outcome stage records
         * what actually happened, and that is where recovery becomes a fact.
         *
         * Confidence is the CEILING on each causal claim, and these sit lower
         * than the structural rules on purpose: that a department has no head is
         * a fact about the row, whereas why fees are unpaid is an inference
         * about families, and the honest ceiling for an inference is lower.
         */
        'fee_collection_shortfall' => [
            'family' => 'collection_risk',
            'confidence' => 0.72,
            'hypothesis' => 'A year-wide collection shortfall of this size is rarely spread evenly: it usually combines a group of accounts that have paid nothing with cycles that were billed but never followed up.',
            'action' => 'Review the accounts in arrears against the cycles already billed, starting with the largest balances.',
            'category' => 'investigate',
        ],
        'fee_receipt_coverage' => [
            'family' => 'process_adoption_gap',
            'confidence' => 0.80,
            'hypothesis' => 'When most accounts carry no receipt at all, collection is usually happening outside the ERP or being entered late, so the system understates what has actually been collected.',
            'action' => 'Confirm whether collection is being recorded in the ERP before treating these balances as arrears.',
            'category' => 'investigate',
        ],
        'fee_outstanding_concentration' => [
            'family' => 'collection_risk',
            'confidence' => 0.85,
            'hypothesis' => 'Outstanding balances concentrate in a few accounts where a full year, or several heads, went unpaid rather than a single instalment being missed.',
            'action' => 'Prioritise the highest-value overdue accounts for structured follow-up before any school-wide reminder.',
            'category' => 'remediate',
        ],
        'fee_overdue_backlog' => [
            'family' => 'collection_aging',
            'confidence' => 0.78,
            'hypothesis' => 'Fees still unpaid several cycles after they were billed indicate follow-up is not running on a cycle-by-cycle schedule, so arrears accumulate rather than being cleared as they arise.',
            'action' => 'Work the oldest overdue cycles first, and set a follow-up point at the close of each cycle.',
            'category' => 'remediate',
        ],
        'fee_cycle_decline' => [
            'family' => 'collection_risk',
            'confidence' => 0.70,
            'hypothesis' => 'A fall between consecutive billed cycles usually reflects a missed follow-up round for the later cycle rather than a change in what families can pay.',
            'action' => 'Check whether the later cycle was followed up, and compare the two cycles by class before drawing a conclusion.',
            'category' => 'investigate',
        ],
        'fee_class_collection_gap' => [
            'family' => 'collection_distribution',
            'confidence' => 0.75,
            'hypothesis' => 'A class well below the school collection rate generally reflects something specific to that cohort — its fee structure, its quota mix, or follow-up that has not reached it — rather than a school-wide cause.',
            'action' => 'Review the classes named here with their class teachers, and confirm the fee structure applied to each.',
            'category' => 'investigate',
        ],
        'fee_head_collection_gap' => [
            'family' => 'collection_distribution',
            'confidence' => 0.70,
            'hypothesis' => 'A fee head lagging the school rate is often optional in practice, disputed, or billed to families who were never told it applied to them.',
            'action' => 'Review how the lagging fee heads are communicated and whether they are being billed to the right cohorts.',
            'category' => 'investigate',
        ],
        'fee_payment_mode_concentration' => [
            'family' => 'payment_handling_risk',
            'confidence' => 0.65,
            'hypothesis' => 'Fee money arriving almost entirely through one channel concentrates handling, reconciliation and continuity risk in that channel.',
            'action' => 'Review whether the alternative payment channels offered to families are actually usable.',
            'category' => 'watch',
        ],

        'fee_cancellation_pressure' => [
            'family' => 'ledger_quality',
            'confidence' => 0.70,
            'hypothesis' => 'Heavy cancellation relative to collection usually means receipts are being issued and reversed rather than corrected at entry, so the collection figure reflects activity rather than money received.',
            'action' => 'Review why receipts are being cancelled at this rate before relying on the collection figure.',
            'category' => 'investigate',
        ],
        'fee_reconciliation_gap' => [
            'family' => 'ledger_quality',
            'confidence' => 0.92,
            'hypothesis' => 'A receipt cancelled in one table but left live in another indicates the cancellation flow does not always write back the deletion flag the reports read.',
            'action' => 'Reconcile the cancelled receipts still marked live, and correct the cancellation flow that left them.',
            'category' => 'remediate',
        ],
        'fee_payment_failures' => [
            'family' => 'collection_risk',
            'confidence' => 0.90,
            'hypothesis' => 'Repeated payment failures indicate banking or mandate friction, often preceding prolonged arrears.',
            'action' => 'Contact the affected families to update their payment method or re-attempt auto-debit before penalties accrue.',
            'category' => 'remediate',
        ],
        'fee_gateway_reconciliation_gap' => [
            'family' => 'reconciliation',
            'confidence' => 0.92,
            'hypothesis' => 'Settlement discrepancies between online payment gateways and ERP ledger receipts indicate un-credited or pending transactions requiring finance audit.',
            'action' => 'Audit the gateway reconciliation log against bank settlement statements and credit confirmed student accounts.',
            'category' => 'remediate',
        ],
        'fee_nach_mandate_coverage' => [
            'family' => 'collection_readiness',
            'confidence' => 0.85,
            'hypothesis' => 'Low auto-debit and NACH mandate registration across eligible accounts limits automated collection and increases manual cash/cheque overhead.',
            'action' => 'Initiate an e-mandate registration drive for families currently paying through manual channels.',
            'category' => 'optimize',
        ],
        'fee_configured_late_backlog' => [
            'family' => 'aging',
            'confidence' => 0.90,
            'hypothesis' => 'Accounts exceeding the institutional late-fee deadline configured in the school rules carry active financial exposure.',
            'action' => 'Issue overdue fee circulars with the configured fine schedule to prioritize recovery.',
            'category' => 'remediate',
        ],
        'fee_cancellation_reasons' => [
            'family' => 'ledger_quality',
            'confidence' => 0.80,
            'hypothesis' => 'High concentration of receipt cancellations under specific root causes (e.g. cheque dishonor or clerical entries) indicates entry friction or dishonored instruments.',
            'action' => 'Review recurring cancellation reasons with the accounts desk to eliminate systemic entry errors or chase dishonored cheques.',
            'category' => 'investigate',
        ],
        'fee_revision_impact' => [
            'family' => 'structure',
            'confidence' => 0.85,
            'hypothesis' => 'Mid-session alterations to fee heads or structure create billing discrepancies that may confuse parents and delay fee remittances.',
            'action' => 'Verify that revised fee structures match published management circulars and that student ledgers reflect the approved adjustment.',
            'category' => 'watch',
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

        /* ═══════════════════════════════════════════════════════════════════
         * MODULE INTELLIGENCE
         *
         * Raised through ModuleSignalBridge, which namespaces every key with its
         * module so two modules cannot collide on a shared rule name.
         *
         * ONLY THE RULES THAT ASSERT A CAUSE APPEAR HERE. Eleven module rules
         * return `likelyCause => null` because their data shows WHAT happened and
         * not WHY — attendance below the board line, a crowded boarding stop, a
         * heavy teaching week, staff with no contact route. Those are absent from
         * this table on purpose: Reasoner then raises the signal with its
         * evidence and forms no hypothesis, which is the difference between the
         * Brain detecting something and the Brain inventing an explanation for it.
         *
         * `confidence` is the CEILING the causal claim may reach, not the
         * finding's own confidence. It is low wherever the rule's own wording
         * hedges — several of these name two possible causes and say the data
         * cannot separate them, and the ceiling reflects that.
         * ═══════════════════════════════════════════════════════════════════ */

        /* ---------------------------------------------------------- attendance */
        'mod_attendance_class_attendance_gap' => [
            'family' => 'attendance_risk',
            'confidence' => 0.55,
            'hypothesis' => 'A gap this size against the same institute in the same year is localised to the class rather than to the calendar, so its cause is local — a timetable or transport friction particular to it, a cohort with longer journeys, or a register kept differently by one teacher. This data shows the gap and cannot distinguish between those.',
            'action' => 'Compare this class’s week against one attending at the institute rate — the answer is usually in the first period or the journey to it.',
            'category' => 'investigate',
        ],
        'mod_attendance_roll_not_in_register' => [
            'family' => 'data_quality',
            'confidence' => 0.80,
            'hypothesis' => 'A class or section whose register has not been submitted for the year, or students enrolled after the register was generated. Either way their attendance is unknown rather than low, and every rate on the screen is computed over the students who do have a row.',
            'action' => 'Establish which classes the missing students belong to before reading any other attendance figure — a rate over three-quarters of the school is not the school’s rate.',
            'category' => 'remediate',
        ],
        'mod_attendance_term_movement' => [
            'family' => 'attendance_risk',
            'confidence' => 0.45,
            'hypothesis' => 'Seasonal absence, examination timing, or a later term that has simply not been entered as completely as the earlier one. The register carries a term and no date, so it cannot separate a fall in attendance from a fall in recording.',
            'action' => 'Check whether the later term is fully entered before treating this as a fall in attendance.',
            'category' => 'investigate',
        ],

        /* ------------------------------------------------------------- student */
        'mod_student_uneven_class_sizes' => [
            'family' => 'structure',
            'confidence' => 0.70,
            'hypothesis' => 'Students placed into sections as they were admitted rather than balanced at the start of the year, or a section carrying a subject stream the others do not. Sections of one standard share a syllabus and staffing, so a spread this wide is a placement outcome rather than a demand one.',
            'action' => 'Check whether the fullest and lightest sections follow the same subjects, then rebalance where they do — the seats already exist in the same standard.',
            'category' => 'optimize',
        ],
        'mod_student_gender_composition' => [
            'family' => 'structure',
            'confidence' => 0.40,
            'hypothesis' => 'A section carrying a subject stream with its own intake pattern, or students placed by order of admission. The roll records the composition and not the reason for it, so this is a question to ask rather than a conclusion.',
            'action' => 'Establish whether the composition of the named classes was a placement decision or an accident — only one of those is reviewable.',
            'category' => 'investigate',
        ],
        'mod_student_retention' => [
            'family' => 'engagement_risk',
            'confidence' => 0.50,
            'hypothesis' => 'The terminal standard graduating accounts for part of any year’s non-return and transfers account for the rest. The roll records that a student did not come back and not why, so a school whose leaving standard holds a hundred students sees a hundred non-returns in a year with no attrition at all.',
            'action' => 'Separate the leaving standard from the rest before reading this as attrition — the number worth acting on is the non-returns from standards that were not the last one.',
            'category' => 'investigate',
        ],
        'mod_student_unplaced_students' => [
            'family' => 'data_quality',
            'confidence' => 0.75,
            'hypothesis' => 'Students admitted after the classes were formed, or an enrolment created from an admission record before placement. A student with no class has no timetable, no register and no class teacher.',
            'action' => 'Place these students before the next register or mark entry runs, or they will be absent from both without appearing to be missing.',
            'category' => 'remediate',
        ],

        /* ----------------------------------------------------------- transport */
        'mod_transport_fleet_overcapacity' => [
            'family' => 'compliance_exposure',
            'confidence' => 0.60,
            'hypothesis' => 'Either more arrangements were accepted on these vehicles than seats exist, or the registered capacity on file is lower than the vehicle actually running the route. The first is an allocation decision and the second is a record to correct; this data cannot distinguish between them.',
            'action' => 'Check the registered capacity of the named vehicles against the vehicles actually on the route, then rebalance the arrangements that remain genuinely over.',
            'category' => 'remediate',
        ],
        'mod_transport_fleet_underuse' => [
            'family' => 'structure',
            'confidence' => 0.50,
            'hypothesis' => 'Routes shaped around where children live rather than around vehicle size, or a vehicle kept on a route whose demand has since fallen. A trip costs the same whether the vehicle is full or half full.',
            'action' => 'Read this beside the overcapacity finding — the two lists together show whether a reallocation between existing vehicles resolves the crowding without adding a trip.',
            'category' => 'optimize',
        ],
        'mod_transport_riders_off_roll' => [
            'family' => 'reconciliation',
            'confidence' => 0.80,
            'hypothesis' => 'Arrangements carried forward from a previous year without being closed when the student left, or enrolments recorded under a different student record. The mismatch is certain; which of the two produced it is not.',
            'action' => 'Reconcile the transport list against this year’s roll before using any rider or billing figure, and close the arrangements of students who have left.',
            'category' => 'remediate',
        ],
        'mod_transport_unbilled_seats' => [
            'family' => 'ledger_quality',
            'confidence' => 0.55,
            'hypothesis' => 'Fee waivers, staff children, or arrangements created before the distance-based rate was applied. The records show the absence of an amount and not the reason for it, so a waiver and an omission are currently indistinguishable.',
            'action' => 'Record a waiver where one was granted, so the ledger distinguishes a decision from an omission.',
            'category' => 'remediate',
        ],

        /* ------------------------------------------------------------- library */
        'mod_library_overdue_loan_volume' => [
            'family' => 'aging',
            'confidence' => 0.70,
            'hypothesis' => 'Loans overdue by months rather than days usually belong to students who have since left, whose books were never collected at the point they stopped being reachable. The circulation record shows the age and not whether the borrower is still on the roll.',
            'action' => 'Check the oldest loans against this year’s roll before sending reminders — a reminder to a student who left last year recovers nothing, and writing the stock off is the honest alternative.',
            'category' => 'remediate',
        ],
        'mod_library_dormant_catalogue' => [
            'family' => 'structural_dormancy',
            'confidence' => 0.65,
            'hypothesis' => 'Reference stock, prescribed texts kept for a syllabus that changed, and donated books all sit legitimately among titles that have never been issued. The figure counts titles that never left the shelf, not titles that should not have been bought.',
            'action' => 'Read the dormant list by section before the next acquisition round — the sections that never move are the ones the budget should stop going to.',
            'category' => 'optimize',
        ],
        'mod_library_high_demand_titles' => [
            'family' => 'demand_concentration',
            'confidence' => 0.60,
            'hypothesis' => 'Circulation concentrated in a few titles is usually a prescribed or recommended reading list meeting a catalogue that holds one or two copies of each.',
            'action' => 'Check the copy count on the most-borrowed titles against the size of the cohort that needs them.',
            'category' => 'optimize',
        ],

        /* ------------------------------------------------------------ academic */
        'mod_academic_teacher_clash' => [
            'family' => 'scheduling_conflict',
            'confidence' => 0.85,
            'hypothesis' => 'A timetable built per standard without a cross-check against each teacher’s own week, which is how the same teacher ends up allocated twice in one slot by two different people. Whichever class the teacher walks into, the other is unsupervised.',
            'action' => 'Resolve the named teachers’ clashing slots before the term’s timetable is issued — each one is a class that will otherwise be left alone.',
            'category' => 'remediate',
        ],
        'mod_academic_class_double_booked' => [
            'family' => 'scheduling_conflict',
            'confidence' => 0.45,
            'hypothesis' => 'Elective and split-batch teaching is recorded the same way as a clash in this table, so some of these are two groups of one class taught separately. That the timetable cannot distinguish them is itself the problem — nothing downstream can either.',
            'action' => 'Separate genuine electives from accidental double-bookings; until they are distinguishable, neither a substitution rota nor an attendance register can tell which lesson a child should be in.',
            'category' => 'investigate',
        ],

        /* ------------------------------------------------------------------ hr */
        'mod_hr_leave_concentration' => [
            'family' => 'workload_distribution',
            'confidence' => 0.40,
            'hypothesis' => 'Roles differ in entitlement, in how leave is recorded, and in how much is taken as single days. A difference in days a head is not by itself evidence about the staff in the role — but cover is found inside a role rather than across the institute, so the burden falls on fewer people than the headline suggests.',
            'action' => 'Check the entitlement attached to the named roles before reading this as a pattern of absence — a role with a larger allowance shows a larger figure by design.',
            'category' => 'investigate',
        ],
        'mod_hr_leave_without_pay' => [
            'family' => 'entitlement_exhaustion',
            'confidence' => 0.60,
            'hypothesis' => 'Entitlement exhausted before the year ended, or leave types that carry no paid allowance by design. The register records the status and not the reason it was applied.',
            'action' => 'Check when in the year the without-pay days fall — clustered at the end suggests the allowance is set too low; spread evenly suggests a leave type that was never paid.',
            'category' => 'investigate',
        ],
        'mod_hr_service_records' => [
            'family' => 'data_quality',
            'confidence' => 0.70,
            'hypothesis' => 'Staff records created for system access rather than as employment records, which is the usual pattern when the HR module was adopted after the accounts were. Length of service, gratuity and seniority cannot be answered from the system while the fields are empty.',
            'action' => 'Backfill joining dates from the appointment letters before the next increment cycle, starting with the longest-serving staff.',
            'category' => 'remediate',
        ],
        'mod_hr_punch_open_shifts' => [
            'family' => 'data_quality',
            'confidence' => 0.55,
            'hypothesis' => 'An exit without a reader, a device that drops the second punch, or staff who were never asked to punch out. The register records that the day never closed and not which of the three it was — and no span of hours exists for those days, so anything paid on hours reads them as nothing.',
            'action' => 'Take the role with the highest open rate and watch one day end. Whether the reader is being passed, is failing, or was never expected at the exit decides which of three different fixes this needs.',
            'category' => 'investigate',
        ],
        'mod_hr_punch_register_gap' => [
            'family' => 'coverage_gap',
            'confidence' => 0.60,
            'hypothesis' => 'Staff exempt from punching, based at another site, or appointed after the readers were issued. Every attendance figure is drawn from the register, so while this is unrecorded the figures describe whoever holds a card rather than the staff body.',
            'action' => 'Mark which roles are exempt from punching. It is the difference between an attendance figure that covers the staff body and one that covers whoever happens to hold a card.',
            'category' => 'remediate',
        ],
        'mod_hr_punch_attendance_spread' => [
            'family' => 'workload_distribution',
            'confidence' => 0.35,
            'hypothesis' => 'Part-time or shift hours, term-time-only appointments, duties carried out off site, or a reader these staff do not pass. The register records days with a punch and none of the four, and approved leave is indistinguishable from absence in it.',
            'action' => 'Check the contracted pattern for the named roles before reading this as attendance — a role appointed for three days a week sits here by design.',
            'category' => 'investigate',
        ],
        // DELIBERATELY ABSENT: `mod_hr_punch_stale_staff_master`. A member of
        // staff marked inactive who is still punching means either that the
        // master is stale or that the door is admitting somebody who has left.
        // The two need opposite responses and nothing in either table decides
        // between them, so that rule raises a signal with its evidence and no
        // hypothesis at all — which is the honest answer and reads as one.

        /* --------------------------------------------------------- communication */
        'mod_communication_unanswered_inquiries' => [
            'family' => 'sla_breach',
            'confidence' => 0.55,
            'hypothesis' => 'Inquiries arriving to a queue nobody owns, or replies given by telephone and never written back. The records show the absence of a written reply and not the absence of an answer — but a parent who writes through the portal and receives nothing learns that the portal is not the way to reach the school.',
            'action' => 'Check whether the unanswered inquiries concentrate in particular classes — a class whose teacher does not use the portal is a different problem from a general backlog.',
            'category' => 'investigate',
        ],
        'mod_communication_class_reply_gap' => [
            'family' => 'ownership_gap',
            'confidence' => 0.60,
            'hypothesis' => 'Parent messages are answered by the class teacher, so a reply rate that varies by class is about who is reading the portal rather than about how much mail arrives. A teacher answering in the diary instead would produce the same pattern as one not reading it.',
            'action' => 'Ask the named classes how they answer parents before treating this as a backlog — a reply given in the diary is an answer the portal cannot see.',
            'category' => 'investigate',
        ],
        'mod_communication_reply_rate_drift' => [
            'family' => 'sla_breach',
            'confidence' => 0.40,
            'hypothesis' => 'The most recent month is always partly unanswered because it is recent, which pulls the later half of the year down. Comparing halves rather than months limits that effect without eliminating it.',
            'action' => 'Discount the current month before acting on this — the rest of the movement is real.',
            'category' => 'watch',
        ],

        /* ---------------------------------------------------------- admissions */
        'mod_admissions_stage_dropoff' => [
            'family' => 'funnel_leakage',
            'confidence' => 0.45,
            'hypothesis' => 'A candidate who stopped at a stage may have gone elsewhere, may have been declined, or may simply not have been followed up. The funnel shows the stage they stopped at and nothing about which of those it was.',
            'action' => 'Take a sample of the candidates who stopped at the worst stage and establish which of the three it was — the answer decides whether this is a process problem or a market one.',
            'category' => 'investigate',
        ],
        'mod_admissions_undecided_candidates' => [
            'family' => 'sla_breach',
            'confidence' => 0.70,
            'hypothesis' => 'An outcome decided in conversation and never written back, or a candidate still genuinely under consideration. The record is blank either way, and a family with no answer is deciding between schools without knowing whether they have a place here.',
            'action' => 'Close the open candidates before the year’s intake is counted — a blank outcome costs the same as a rejection and tells the family less.',
            'category' => 'remediate',
        ],
        'mod_admissions_confirmed_unpaid' => [
            'family' => 'reconciliation',
            'confidence' => 0.50,
            'hypothesis' => 'Fees collected outside this system, a payment field filled in later, or places confirmed before payment is asked for. The records show the field is not set and not which of those left it that way.',
            'action' => 'Reconcile the confirmed-but-unpaid list against the fees module before treating any of them as unfunded seats.',
            'category' => 'investigate',
        ],
        'mod_admissions_conversion_rate' => [
            'family' => 'funnel_leakage',
            'confidence' => 0.50,
            'hypothesis' => 'Candidates going elsewhere, candidates being declined, and candidates never answered all reduce this figure identically. Registration is the point at which a family already chose to apply, so a low conversion from it is not a marketing problem.',
            'action' => 'Read this beside the undecided finding — closing the open candidates changes this number without changing a single decision.',
            'category' => 'investigate',
        ],

        /* ----------------------------------------------------------- inventory */
        'mod_inventory_stocktake_without_outcome' => [
            'family' => 'data_quality',
            'confidence' => 0.70,
            'hypothesis' => 'The outcome field is optional in the scanning workflow, or the scanning device writes the code without it. The work of walking the shelves was done and the answer was not captured, so the stock-take has no result.',
            'action' => 'Establish whether the outcome is captured anywhere outside this table before the next stock-take; if it is not, the scan records attendance at the shelf rather than the state of the asset.',
            'category' => 'remediate',
        ],
        'mod_inventory_items_not_found' => [
            'family' => 'asset_shrinkage',
            'confidence' => 0.50,
            'hypothesis' => 'An asset the register holds and the shelf does not is either misplaced or gone. Item codes group by prefix and the grouping is real, but this schema records nothing about what a prefix stands for, so where the losses concentrate cannot be turned into a location or a department from here.',
            'action' => 'Take the weakest code groups back to whoever assigns the prefixes — the grouping is the only structure in the data and the fastest route to where the items were.',
            'category' => 'investigate',
        ],
        'mod_inventory_repeat_scans' => [
            'family' => 'data_quality',
            'confidence' => 0.75,
            'hypothesis' => 'A stock-take that passes the same location more than once, which is ordinary practice and not itself a problem. It matters because the scan count is not the item count, and only the second is stock.',
            'action' => 'Count distinct item codes rather than scans in anything that reads this stock-take as a stock figure.',
            'category' => 'watch',
        ],


        /* -------------------------------------------------------- result --- */
        'mod_result_class_subject_cluster' => [
            'family' => 'academic_risk',
            'confidence' => 0.45,
            'hypothesis' => 'A gap this size against the SAME subject in other classes is not explained by the subject being difficult — it is localised to this class. A teaching-capacity gap, a harder paper set for this class, or a marking inconsistency would each produce it, and these marks cannot distinguish between them.',
            'action' => 'Review the papers and the marking for this class against another class taking the same subject, before drawing any conclusion about the cohort.',
            'category' => 'investigate',
        ],
        'mod_result_weak_subject' => [
            'family' => 'academic_risk',
            'confidence' => 0.40,
            'hypothesis' => 'Assessment design or departmental capacity. A subject can also read low simply because its paper is harder than the others it is being compared with, which is a property of the paper rather than of the teaching.',
            'action' => 'Compare the paper difficulty and marking scheme against a subject scoring near the school average before treating this as attainment.',
            'category' => 'investigate',
        ],
        'mod_result_class_outlier' => [
            'family' => 'academic_risk',
            'confidence' => 0.45,
            'hypothesis' => 'Attendance, cohort composition, or disruption affecting the whole class rather than any one subject. A class below the school average across every subject is rarely an academic problem in the first instance.',
            'action' => 'Check the class’s attendance against the school average before treating this as an academic problem.',
            'category' => 'investigate',
        ],
        'mod_result_students_at_risk' => [
            'family' => 'academic_risk',
            'confidence' => 0.50,
            'hypothesis' => 'Mixed. A year total this low is usually attendance or an unaddressed gap in an earlier year rather than the current year’s teaching, and the mark sheet cannot tell which.',
            'action' => 'Start with the class holding the largest concentration rather than the single lowest student — the cause is more likely to be shared than individual.',
            'category' => 'remediate',
        ],
        'mod_result_sparse_entry' => [
            'family' => 'data_quality',
            'confidence' => 0.75,
            'hypothesis' => 'Marks entered for some components and not others, which leaves every average computed over a different denominator per student.',
            'action' => 'Complete the outstanding mark entry before any per-student total is published or compared.',
            'category' => 'remediate',
        ],
        'mod_result_year_on_year' => [
            'family' => 'academic_risk',
            'confidence' => 0.35,
            'hypothesis' => 'A change in assessment design or in which components were entered, as often as a change in attainment. Two years are only comparable when the same components were recorded in both.',
            'action' => 'Before reading this as attainment, check whether the same exam components were entered in both years.',
            'category' => 'investigate',
        ],


        /* ------------------------------------------------------ homework --- */
        'mod_homework_subject_low_submission' => [
            'family' => 'academic_risk',
            'confidence' => 0.35,
            'hypothesis' => 'Comprehension difficulty in the subject, homework expectations that were not made clear, or several subjects setting work for the same night. The submission record shows the rate and none of the three — and it records only that work came back, never whether it was any good.',
            'action' => 'Compare the dates the weakest subject set work against the others before treating this as comprehension. Work set the same night as two other subjects comes back less for reasons that have nothing to do with the subject.',
            'category' => 'investigate',
        ],
        'mod_homework_outstanding_burden' => [
            'family' => 'academic_risk',
            'confidence' => 0.40,
            'hypothesis' => 'Work not done, work done on paper and never marked off in the system, or deadlines compressing across subjects in the same week. A backlog is as often a timetable outcome as a student one.',
            'action' => 'Read the outstanding work by the date it was set rather than by subject — a cluster on one date is a scheduling problem, an even spread is not.',
            'category' => 'investigate',
        ],
        'mod_homework_no_teacher_review' => [
            'family' => 'sla_breach',
            'confidence' => 0.55,
            'hypothesis' => 'Work marked on paper and never written back, or a review step nobody was asked to use. Either way the child sees no response through the system, and the record cannot tell the two apart — a mark in an exercise book is feedback the system simply cannot see.',
            'action' => 'Establish whether the marking exists on paper before treating this as a backlog. If it does, the question is whether the review step is worth asking anyone to duplicate; if it does not, these children have had no response at all.',
            'category' => 'investigate',
        ],
        'mod_homework_thin_adoption' => [
            'family' => 'adoption_gap',
            'confidence' => 0.60,
            'hypothesis' => 'A module being trialled by one teacher or one department, which is the usual shape of a new workflow in its first year. Every other figure on the screen is drawn from those classes, so it describes them and not the school.',
            'action' => 'Decide whether this is a trial or a rollout before anybody reads these figures as institute-wide. If it is a trial, the classes using it are the only ones these numbers describe.',
            'category' => 'investigate',
        ],
        'mod_homework_submission_status_unusable' => [
            'family' => 'data_quality',
            'confidence' => 0.70,
            'hypothesis' => 'A submission path that writes the date without setting the status, which is the usual shape of two fields updated by different code paths. The register holds both and reconciles neither — and the error runs in the direction that makes children look as though they did not do their work.',
            'action' => 'Settle which field the homework module treats as the submission signal and backfill the other from it. Until then no submission figure from this module should be acted on, and no child should be followed up for work the dates say they handed in.',
            'category' => 'remediate',
        ],
        'mod_homework_year_label_mismatch' => [
            'family' => 'data_quality',
            'confidence' => 0.70,
            'hypothesis' => 'A year tag carried over from whatever was selected when the work was created, rather than derived from its date. The two fields are written independently and nothing reconciles them.',
            'action' => 'Settle which of the two fields the homework module should trust before building any report on either. Until then a date-scoped report and a year-scoped one will disagree and both will look right.',
            'category' => 'remediate',
        ],

        /* -------------------------------------------------------- hostel --- */
        'mod_hostel_placements_name_unknown_rooms' => [
            'family' => 'lifecycle_inconsistency',
            'confidence' => 0.70,
            'hypothesis' => 'Rooms allocated from a list held outside the system, or a room master that was emptied or never filled after the placements were made. The allocation holds a room id and the master holds nothing at it; which came first is not recorded. Meanwhile no roll can be printed by room, because the room does not exist to print against.',
            'action' => 'Register the rooms these children are actually in before anything else in this module is used. Until the room master holds them, a boarding roll cannot be produced from this system at all.',
            'category' => 'remediate',
        ],
        'mod_hostel_rooms_hanging_off_nothing' => [
            'family' => 'lifecycle_inconsistency',
            'confidence' => 0.70,
            'hypothesis' => 'Rooms imported or entered before the floors above them, which is the usual order when a room list already exists on paper. The room master records a floor id and nothing guarantees that floor was ever created.',
            'action' => 'Create the floors these rooms name, or repoint the rooms at floors that exist. Leaving them means the boarding structure cannot be walked from the top at all.',
            'category' => 'remediate',
        ],
        'mod_hostel_hostel_without_warden' => [
            'family' => 'ownership_gap',
            'confidence' => 0.75,
            'hypothesis' => 'A hostel record created for the structure before anybody was assigned to it. The field exists and was left empty — which means no recorded person is responsible for the children in it overnight.',
            'action' => 'Name the warden against each hostel. It is one field and it is the first thing anybody asks for.',
            'category' => 'remediate',
        ],
        'mod_hostel_unassigned_bed_numbers' => [
            'family' => 'data_quality',
            'confidence' => 0.50,
            'hypothesis' => 'Beds allocated informally and never registered in the ledger, which is the usual pattern where the boarding house is small enough to be managed on paper.',
            'action' => 'Register the current occupancy once before relying on any hostel figure — the ledger is currently a partial copy of the boarding list.',
            'category' => 'remediate',
        ],

        /* ------------------------------------------------------- visitor --- */
        'mod_visitor_visits_never_closed' => [
            'family' => 'lifecycle_inconsistency',
            'confidence' => 0.75,
            'hypothesis' => 'Signing out is a second trip to the desk that nothing enforces, so it is done when the desk is quiet and skipped when it is not. The register records the arrival because the visitor is standing there; it records the departure only if somebody remembers. Meanwhile the register’s own answer to "who is on site" names people who went home hours ago.',
            'action' => 'Decide what the gate register is for. If it is to answer who is on site, sign-out has to be enforced at the point of exit rather than left to memory — and until it is, no figure from this register can be used to say who was in the building.',
            'category' => 'remediate',
        ],
        'mod_visitor_visitor_type_not_in_master' => [
            'family' => 'lifecycle_inconsistency',
            'confidence' => 0.70,
            'hypothesis' => 'A register seeded or copied from another institute’s configuration, so the visit rows carry that institute’s visitor-type ids while this one’s own types were created separately and never used. The visit holds an id and this institute’s master holds nothing at it; which came first is not recorded.',
            'action' => 'Map the type ids the register actually uses onto this institute’s own visitor types, then point new visits at the local ones. Until that is done the kind of visitor is not recoverable for any of these rows.',
            'category' => 'remediate',
        ],
        'mod_visitor_exit_before_entry' => [
            'family' => 'data_quality',
            'confidence' => 0.65,
            'hypothesis' => 'A desk entering times by hand with no enforced format, so some rows are written as they appear on a 12-hour clock face and some on a 24-hour one. Nothing in the schema records which convention a row used, which is why no visit duration can be computed from these columns at all.',
            'action' => 'Capture entry and exit as full timestamps taken from the system clock rather than as typed times. Correcting the existing rows is not possible, because the intended clock was never recorded.',
            'category' => 'remediate',
        ],
        'mod_visitor_appointment_type_unused' => [
            'family' => 'data_quality',
            'confidence' => 0.55,
            'hypothesis' => 'Appointments booked somewhere other than this system — a diary, a phone call or an email — so everybody arriving is entered at the desk as though unannounced, or the field is simply left at its default on a form filled in a hurry.',
            'action' => 'Either record the appointment type at sign-in and use it, or stop collecting it. A field that is always blank, or always the same value, makes the register look as though it distinguishes expected visitors when it does not.',
            'category' => 'remediate',
        ],
        'mod_visitor_unusable_visit_date' => [
            'family' => 'data_quality',
            'confidence' => 0.70,
            'hypothesis' => 'Rows created without the date field being set, or imported from a source that had no date for them. MySQL stores the result as a zero date rather than rejecting it, so the rows belong to no academic year and are invisible to every year-scoped view.',
            'action' => 'Find these rows and either date them from whatever else is on them or remove them. They are absent from every year-scoped report, so they will not be noticed any other way.',
            'category' => 'remediate',
        ],

        /* ------------------------------------------------ correspondence --- */
        'mod_correspondence_outward_register_unused' => [
            'family' => 'ownership_gap',
            'confidence' => 0.70,
            'hypothesis' => 'Logging something inward is forced by the act of receiving it — somebody is holding the envelope. Sending something out has no such moment, so the outward register is filled only when somebody remembers to go back to it after the letter has gone. The consequence is that whether anything was answered cannot be established at all.',
            'action' => 'Decide whether the outward register is meant to be kept. If it is, the moment to write the entry is when the reply is signed, not afterwards; if it is not, the inward register should not be relied on to show that anything was answered.',
            'category' => 'remediate',
        ],
        'mod_correspondence_unresolved_file_location' => [
            'family' => 'lifecycle_inconsistency',
            'confidence' => 0.70,
            'hypothesis' => 'File locations renamed, merged or deleted from the master after entries had already been filed against them, or entries imported carrying location ids from a previous system. The entry holds an id and the master holds nothing at it, so the record proves a letter arrived and gives no way to produce it.',
            'action' => 'Reconcile the location ids these entries actually use against the file-location master before the next audit asks for a document. Restoring the master rows is usually cheaper than re-filing the entries.',
            'category' => 'remediate',
        ],
        'mod_correspondence_no_file_location_recorded' => [
            'family' => 'data_quality',
            'confidence' => 0.70,
            'hypothesis' => 'The file location is not required to save an entry, so it is filled when the paper is filed straight away and left empty when it is put aside to file later. Nothing returns to it afterwards, so the register proves a letter exists and gives nobody anywhere to look for it.',
            'action' => 'Require a file location before an inward entry can be saved. Back-filling existing entries is only possible while somebody still remembers where the paper went, so it is worth doing for the current year before any earlier one.',
            'category' => 'remediate',
        ],
        'mod_correspondence_duplicate_inward_numbers' => [
            'family' => 'data_quality',
            'confidence' => 0.60,
            'hypothesis' => 'A number typed by hand rather than issued by the system, so two entries made on the same day or by two people can take the same one. Nothing in the schema enforces uniqueness, and the ambiguity only surfaces when somebody is trying to find a specific document.',
            'action' => 'Issue the inward number from the system rather than accepting it as typed input. The affected entries are few enough to renumber by hand once that is in place.',
            'category' => 'remediate',
        ],
        'mod_correspondence_missing_attachment' => [
            'family' => 'data_quality',
            'confidence' => 0.55,
            'hypothesis' => 'Scanning is a separate step from logging, so it is done when the office is quiet and skipped when it is not. Nothing requires an attachment before an entry can be saved.',
            'action' => 'Scan at the point of logging rather than as a later pass. Entries already logged without one are worth back-filling only where the file location resolves, since the rest cannot be found to scan.',
            'category' => 'remediate',
        ],

        /* -------------------------------------------------- teach/learn --- */
        'mod_teach-learn_curriculum_without_content' => [
            'family' => 'coverage_gap',
            'confidence' => 0.70,
            'hypothesis' => 'Content is published by whoever teaches a subject, so coverage follows the staff who have adopted the library rather than the shape of the curriculum. Nothing in the catalogue requires material before a course is offered, and the Course Catalog screen lists an empty course identically to a full one, so the gap is invisible until a learner opens it.',
            'action' => 'Take the uncovered courses by class and decide which are meant to carry material this year. Courses not taught from the library are worth marking so the gap stops being counted against them.',
            'category' => 'remediate',
        ],
        'mod_teach-learn_unresolved_chapter' => [
            'family' => 'lifecycle_inconsistency',
            'confidence' => 0.75,
            'hypothesis' => 'Chapter rows were created in a shared or central catalogue and content filed against their ids, while this institute’s own chapter master was never populated — chapter_master holds 446 rows across the whole database against 31,192 content rows carrying a chapter id. The item still opens; nothing can say where in the course it belongs.',
            'action' => 'Establish whether the chapters this content cites are meant to live in this institute’s own chapter master. Until they do, treat each course as a flat list and do not rely on chapter ordering in any report built on this data.',
            'category' => 'remediate',
        ],
        'mod_teach-learn_format_concentration' => [
            'family' => 'engagement_risk',
            'confidence' => 0.55,
            'hypothesis' => 'A document is the cheapest thing to produce and upload, so a library accumulates the format that is easiest to add rather than the mix that was intended. Nothing prompts for a second format once one exists.',
            'action' => 'Decide whether the balance is intended. Where it is not, the courses already holding the most material are the cheapest place to add a second format, because their structure exists already.',
            'category' => 'monitor',
        ],
        'mod_teach-learn_publishing_stalled' => [
            'family' => 'academic_risk',
            'confidence' => 0.65,
            'hypothesis' => 'Content is carried over informally rather than rolled forward explicitly: last year’s material still opens, so nothing forces a decision about whether it is still the right material. The drop registers in the records long before anyone teaching notices it.',
            'action' => 'Establish whether this year’s teaching is meant to run on last year’s material. Where it is, roll the content forward into this year so the intent is explicit; where it is not, the courses holding most of last year’s material are where the gap will be felt first.',
            'category' => 'remediate',
        ],
        'mod_teach-learn_hidden_content' => [
            'family' => 'ownership_gap',
            'confidence' => 0.55,
            'hypothesis' => 'Hiding an item is how material is staged before a class reaches it, and nothing returns to turn it back on once the class has passed. The preparation is complete and the result is unreachable.',
            'action' => 'Review hidden items against the courses they belong to. Anything hidden from a course the class has already passed is finished work nobody received.',
            'category' => 'monitor',
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
