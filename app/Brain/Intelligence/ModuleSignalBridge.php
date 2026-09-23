<?php

namespace App\Brain\Intelligence;

use Illuminate\Support\Facades\DB;

/**
 * Carries a module's findings into the signal ledger, so they reach the same
 * loop a fee finding reaches.
 *
 * ── WHY A BRIDGE AND NOT TWELVE MORE RULE CLASSES ───────────────────────────
 *
 * `LmsSignalRules` and `FeesSignalRules` compute a figure and call
 * `SignalWriter::raise()` in the same breath. The twelve module rule classes
 * already compute their findings — with evidence, severity, confidence, an
 * affected count and a recommended action — and hand them to a controller for
 * rendering. Rewriting each of them to raise signals directly would mean
 * maintaining the same domain logic in two shapes, and the second shape would
 * drift.
 *
 * So this translates instead. A module finding is ALREADY the thing a signal
 * carries; what it lacks is a row in `hpbrain_signals` and its evidence in
 * `hpbrain_evidence`. Once those exist, `Reasoner` reasons over them,
 * `AutomationCatalogue` links a procedure, and the recommendation → decision →
 * execution → outcome → learning path runs unchanged. NOTHING in the loop below
 * this point needed to know a new kind of finding had arrived.
 *
 * ── THE HUMAN-APPROVAL GATE IS PRESERVED ────────────────────────────────────
 *
 * `Reasoner` only forms a hypothesis for a rule that `RuleCatalogue` lists. A
 * module rule with no catalogue entry therefore produces a signal WITH evidence
 * and NO explanation — which is the honest state for a finding whose cause this
 * data cannot establish, and is exactly what those rules already say on the
 * screen by returning `likelyCause => null`.
 *
 * ── RULE KEYS CARRY A NAMESPACE THAT CANNOT COLLIDE ─────────────────────────
 *
 * `mod_attendance_class_attendance_gap`, not `class_attendance_gap` and not
 * `attendance_class_attendance_gap`.
 *
 * The signal ledger is shared with `LmsSignalRules`, and the module name alone
 * is NOT enough of a namespace: `LmsSignalRules` already owns
 * `student_missing_identity`, `student_absence_rate`, `attendance_decline`,
 * `result_low_performance` and `homework_non_submission`. A module reading its
 * own signals back with `rule_key LIKE 'student_%'` would pick up six rules it
 * never raised and could not explain — and the Student screen would show a
 * recommendation from the institute-wide catalogue as though it were its own.
 *
 * `mod_` is a prefix no existing rule uses, so the module namespace is now
 * disjoint from the LMS-wide one by construction rather than by inspection.
 */
final class ModuleSignalBridge
{
    /**
     * module key => [analytics class, rules class, source label].
     *
     * The source label is what `hpbrain_signals.source` records — the tables the
     * finding was actually read from, so a signal three months old can still be
     * traced back to what produced it.
     *
     * @var array<string, array{0:class-string,1:class-string,2:string}>
     */
    public const MODULES = [
        'attendance' => [
            AttendanceIntelligence::class,
            AttendanceSignalRules::class,
            'vivek_erp.result_student_attendance_master',
        ],
        'student' => [
            StudentIntelligence::class,
            StudentSignalRules::class,
            'vivek_erp.tblstudent_enrollment',
        ],
        'transport' => [
            TransportIntelligence::class,
            TransportSignalRules::class,
            'vivek_erp.transport_map_student',
        ],
        'library' => [
            LibraryIntelligence::class,
            LibrarySignalRules::class,
            'vivek_erp.library_book_circulations',
        ],
        'academic' => [
            AcademicIntelligence::class,
            AcademicSignalRules::class,
            'vivek_erp.timetable',
        ],
        'hr' => [
            HrIntelligence::class,
            HrSignalRules::class,
            'vivek_erp.tbluser',
        ],
        'communication' => [
            CommunicationIntelligence::class,
            CommunicationSignalRules::class,
            'vivek_erp.parent_communication',
        ],
        'homework' => [
            HomeworkIntelligence::class,
            HomeworkSignalRules::class,
            'vivek_erp.homework',
        ],
        'admissions' => [
            AdmissionsIntelligence::class,
            AdmissionsSignalRules::class,
            'vivek_erp.admission_registration_v1',
        ],
        'inventory' => [
            InventoryIntelligence::class,
            InventorySignalRules::class,
            'vivek_erp.item_scan_details',
        ],
        'hostel' => [
            HostelIntelligence::class,
            HostelSignalRules::class,
            'vivek_erp.hostel_room_allocation',
        ],
        'result' => [
            ResultIntelligence::class,
            ResultSignalRules::class,
            'vivek_erp.result_personalize_marks',
        ],
        'visitor' => [
            VisitorIntelligence::class,
            VisitorSignalRules::class,
            'vivek_erp.visitor_master',
        ],
        'correspondence' => [
            CorrespondenceIntelligence::class,
            CorrespondenceSignalRules::class,
            'vivek_erp.inward',
        ],
        // The curriculum content catalogue behind Teach/Learn's Course Catalog
        // and LMS Global Mapping screens — NOT PAL, homework or exams, each of
        // which is owned elsewhere in this table.
        'teach-learn' => [
            TeachLearnIntelligence::class,
            TeachLearnSignalRules::class,
            'vivek_erp.content_master',
        ],

        // People & Competency modules.
        'organization' => [
            OrganizationIntelligence::class,
            OrganizationSignalRules::class,
            'vivek_erp.tbluser',
        ],
        'task-management' => [
            TaskIntelligence::class,
            TaskSignalRules::class,
            'vivek_erp.task',
        ],
        'talent' => [
            TalentIntelligence::class,
            TalentSignalRules::class,
            'vivek_erp.talent_job_applications',
        ],
        'capability' => [
            CapabilityIntelligence::class,
            CapabilitySignalRules::class,
            'vivek_erp.s_user_jobrole',
        ],
        // Staff biometric/punch attendance — distinct from the pupil register
        // the 'attendance' key above covers.
        'staff-attendance' => [
            StaffAttendanceIntelligence::class,
            StaffAttendanceSignalRules::class,
            'vivek_erp.hrms_attendances',
        ],
        'lms-activity' => [
            LmsActivityIntelligence::class,
            LmsActivitySignalRules::class,
            'vivek_erp.homework',
        ],
    ];

    public function __construct(
        private readonly string $tenantId,
        private readonly SignalWriter $writer,
        private readonly ?string $syear = null,
    ) {
    }

    /**
     * The namespace that separates module rules from the institute-wide ones.
     *
     * No rule in `LmsSignalRules` or `FeesSignalRules` begins with it, which is
     * what makes the two key spaces disjoint by construction.
     */
    public const NAMESPACE = 'mod_';

    /** The namespaced ledger key for one module rule. */
    public static function ruleKey(string $module, string $rule): string
    {
        return self::NAMESPACE.$module.'_'.$rule;
    }

    /** Every rule key a module can raise, for reading its own signals back. */
    public static function ruleKeyPrefix(string $module): string
    {
        return self::NAMESPACE.$module.'_';
    }

    /**
     * One closure per module, in the shape `IntelligencePipeline` already runs.
     *
     * A module whose coverage is unavailable contributes nothing rather than an
     * empty signal — "no attendance was entered" is a coverage state, not a
     * finding, and the screen already says it.
     *
     * @return array<string, callable():array{created:bool,refreshed:bool,signalId:?string,reason:?string}>
     */
    public function applicable(): array
    {
        $rules = [];
        $chosen = [];

        foreach (self::MODULES as $module => [$analyticsClass, $rulesClass, $source]) {
            foreach ($this->findingsFor($analyticsClass, $rulesClass) as $finding) {
                $rule = (string) ($finding['rule'] ?? '');
                if ($rule === '') {
                    // A finding with no rule key cannot be deduped, so a second
                    // pipeline run would raise it again. Skipped rather than
                    // written under a generated key that nothing can match.
                    continue;
                }

                $key = self::ruleKey($module, $rule);

                // ONE SIGNAL PER RULE PER YEAR, and that is the ledger's own
                // invariant rather than a limitation of this bridge: a signal
                // answers "does this rule have an open finding", and the module's
                // own screen shows every instance behind it. Result's
                // class-subject rule raises three at one institute — three
                // classes, one pattern.
                //
                // WHICH instance is chosen is decided here rather than left to
                // iteration order: the most severe, then the one touching the
                // most people. Taking whichever happened to come last would make
                // the ledger's contents depend on a sort nobody intended.
                if (isset($chosen[$key]) && ! $this->outranks($finding, $chosen[$key])) {
                    continue;
                }

                $chosen[$key] = $finding;
                $rules[$key] = fn () => $this->raise($module, $source, $key, $chosen[$key]);
            }
        }

        return $rules;
    }

    /**
     * Read one module's findings, tolerating a module that cannot read its own
     * tables.
     *
     * @return array<int, array<string, mixed>>
     */
    private function findingsFor(string $analyticsClass, string $rulesClass): array
    {
        try {
            $analytics = new $analyticsClass($this->tenantId, $this->syear);

            // NOT gated on coverage here. Coverage answers "is there enough to
            // analyse"; some rules answer "do these records contradict each
            // other", which is true whether there are six rows or six hundred —
            // Hostel's structural rules are exactly that shape, and the one
            // institute holding 96 rooms that belong to no floor has no
            // allocations at all and would otherwise never reach the ledger.
            //
            // Each rule class makes its own decision: those that need a cohort
            // return early on unavailable coverage, as Homework, HR and the rest
            // still do at the top of their own run().
            $raised = (new $rulesClass($analytics, $this->syear))->run();

            return $raised['findings'] ?? [];
        } catch (\Throwable) {
            // The pipeline reports a failed rule rather than aborting the run;
            // a module that cannot read its tables contributes nothing here and
            // its own screen shows the error.
            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $finding
     * @return array{created:bool,refreshed:bool,signalId:?string,reason:?string}
     */
    private function raise(string $module, string $source, string $ruleKey, array $finding): array
    {
        // Evidence first: `hpbrain_evidence` carries a foreign key to the signal,
        // but SignalWriter holds the rows and writes them inside the same
        // transaction, so the ids are reserved here and committed there.
        $evidenceIds = [];
        foreach ((array) ($finding['evidence'] ?? []) as $point) {
            $point = (array) $point;
            $evidenceIds[] = $this->writer->recordEvidence([
                'label' => (string) ($point['label'] ?? ''),
                'value' => (string) ($point['value'] ?? ''),
                'note' => $point['note'] ?? null,
                'module' => $module,
                'rule' => $ruleKey,
                'syear' => $this->syear,
            ]);
        }

        $confidence = $finding['confidence'] ?? null;
        $affected = (array) ($finding['affected'] ?? []);

        return $this->writer->raise([
            'source' => $source,
            'classification' => $module.'_operations',
            'severity' => self::severity($finding['severity'] ?? null),
            'priority' => (string) ($finding['priority'] ?? $finding['severity'] ?? 'medium'),
            'confidence' => is_array($confidence) ? (float) ($confidence['value'] ?? 0.5) : 0.5,
            'relatedEntityType' => ucfirst($module),
            'metadata' => [
                'rule' => $ruleKey,
                'module' => $module,
                'title' => (string) ($finding['title'] ?? ''),
                'syear' => $this->syear,
                'whatHappened' => (string) ($finding['whatHappened'] ?? ''),
                'whyItMatters' => $finding['whyItMatters'] ?? null,
                // Carried so the recommendation card can show the same action
                // the module's own screen shows, rather than a second wording.
                'recommendedAction' => $finding['recommendation'] ?? null,
                'owner' => $finding['owner'] ?? null,
                'affectedCount' => $affected['count'] ?? null,
                'totalCount' => $affected['total'] ?? null,
                'affectedUnit' => $affected['unit'] ?? null,
                // The module's OWN impact figure and its noun. Generic on
                // purpose: Fees measures impact in rupees, Attendance in
                // students, Library in titles.
                'impactValue' => $finding['impact']['value'] ?? null,
                'impactDisplay' => $finding['impact']['display'] ?? null,
                'impactLabel' => $finding['impact']['label'] ?? null,
                'evidenceCount' => count($evidenceIds),
            ],
        ], $evidenceIds);
    }

    /**
     * Whether one finding should represent its rule in the ledger ahead of
     * another: more severe first, then whichever touches more people.
     *
     * @param  array<string, mixed>  $candidate
     * @param  array<string, mixed>  $incumbent
     */
    private function outranks(array $candidate, array $incumbent): bool
    {
        $rank = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3, 'info' => 4];

        $a = $rank[self::severity($candidate['severity'] ?? null)] ?? 5;
        $b = $rank[self::severity($incumbent['severity'] ?? null)] ?? 5;

        if ($a !== $b) {
            return $a < $b;
        }

        return (int) ($candidate['affected']['count'] ?? 0) > (int) ($incumbent['affected']['count'] ?? 0);
    }

    /** The ledger's severity vocabulary. Anything unrecognised becomes `low`. */
    private static function severity(mixed $raw): string
    {
        $key = strtolower(trim((string) $raw));

        return in_array($key, ['critical', 'high', 'medium', 'low'], true) ? $key : 'low';
    }

    /**
     * Signals this module has raised for this institute-year, whatever their
     * stage in the loop.
     *
     * @return array<int, object>
     */
    public static function signalsFor(string $tenantId, string $module, ?string $syear): array
    {
        if (! \App\Brain\Support\SchemaCache::hasTable('hpbrain_signals')) {
            return [];
        }

        return DB::table('hpbrain_signals')
            ->where('tenant_id', $tenantId)
            ->where('rule_key', 'like', self::ruleKeyPrefix($module).'%')
            ->when(
                $syear !== null && \App\Brain\Support\SchemaCache::hasColumn('hpbrain_signals', 'syear'),
                fn ($q) => $q->where(fn ($inner) => $inner->where('syear', $syear)->orWhereNull('syear')),
            )
            ->orderByDesc('created_date')
            ->get()
            ->all();
    }
}
