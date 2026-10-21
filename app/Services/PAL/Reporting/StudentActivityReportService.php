<?php

namespace App\Services\PAL\Reporting;

use App\Models\Eso\LearnerNodeState;
use App\Models\PAL\AdaptiveResponse;
use App\Models\PAL\DiagnosticAttempt;
use App\Models\PAL\Gamification\Badge;
use App\Models\PAL\Gamification\LearnerBadge;
use App\Models\PAL\Gamification\LearnerStreak;
use App\Models\PAL\MisconceptionLibrary;
use App\Services\PAL\Intelligence\LearningVelocityEngine;
use App\Services\PAL\Intelligence\MisconceptionIntelligenceEngine;
use App\Services\PAL\Intelligence\PredictiveInterventionEngine;
use App\Services\PAL\Questions\DifficultyBands;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PAL Student Activity & Performance Report.
 *
 * Sibling of AttainmentReportService, same "Reporting" namespace and the same
 * read-only contract: nothing here writes to learner_node_state or any other
 * ESO table. Where AttainmentReportService asks "was it taught, and did the
 * cohort demonstrate it" against the curriculum, this asks "what has this
 * learner (or this class, aggregated) actually DONE in PAL" — mastery state,
 * diagnostic/adaptive performance, misconceptions, gamification.
 *
 * WHY learner_node_state AND NOT pal_competencies
 * learner_node_state is the table EsoPolicyService::masteryVerdict() itself
 * writes — the authoritative, primary signal. pal_competencies /
 * Competency::atFinestGrain() is a DERIVED, secondary signal with a known
 * null-bloom_level data quality issue, and is deliberately not read here.
 *
 * WHY bothSign(engagement) IS A CANNED SENTENCE, NEVER A COMPUTED NUMBER
 * lms_student_engagement and lms_quiz_attempts carry zero rows platform-wide,
 * and pal_learning_sessions about one. A percentage computed over that would
 * not describe engagement, it would describe noise — so this report refuses
 * to produce one, every time, for both the student and the class shape.
 *
 * WHY GAMIFICATION DEGRADES PER-SCOPE RATHER THAN AVERAGING
 * pal_learner_streaks/pal_learner_badges are real but thin (tens of rows,
 * platform-wide). A class average built mostly from absent rows would read as
 * a number about the class when it is actually a number about how few
 * students have ANY row at all — so the class shape reports "N of M students
 * have data" and nothing is averaged.
 *
 * DATE SCOPING
 * `from`/`to` bound the event-log sections (diagnostic attempts, adaptive
 * responses) — they default to the trailing 90 days when omitted. They do
 * NOT bound mastery_overview: learner_node_state.status is a current-state
 * snapshot, not an event log, so there is no "within this window" reading of
 * it that would not simply be a stale and misleading restriction.
 *
 * SUBJECT SCOPING
 * An optional subject narrows every section, resolved the same way
 * everywhere: lms_concept.chapter_id -> chapter_master.subject_id, never
 * guessed from a column that does not exist on the event tables themselves
 * (pal_adaptive_response has no subject_id; it is reached through
 * concept_id -> lms_concept -> chapter_master instead).
 *
 * $subInstituteId IS A LEADING PARAMETER, NOT PART OF THE "e.g." SIGNATURE
 * The brief's example signatures omit it, but AttainmentReportService's own
 * forCohort() takes it explicitly for the same reason it is needed here:
 * standard_id is NOT globally unique (chapter_master/tblstudent_enrollment
 * both carry it alongside sub_institute_id), so a class report without an
 * explicit institute would silently aggregate across every school that
 * happens to share that standard id. The controller resolves it (session for
 * staff, tblstudent lookup for a learner) and passes it in, exactly as
 * AttainmentReportController passes $staff into forCohort().
 */
class StudentActivityReportService
{
    /** Trailing window used when the caller gives no explicit from/to. */
    private const DEFAULT_WINDOW_DAYS = 90;

    public function __construct(
        private LearningVelocityEngine $velocityEngine,
        private PredictiveInterventionEngine $interventionEngine,
        private MisconceptionIntelligenceEngine $misconceptionEngine
    ) {
    }

    /**
     * One learner's activity & performance report.
     *
     * @return array<string, mixed>
     */
    public function buildStudentReport(
        int $subInstituteId,
        string $learnerId,
        string $syear,
        ?string $subjectId = null,
        ?string $from = null,
        ?string $to = null
    ): array {
        $learnerIdInt = (int) $learnerId;
        $subjectIdInt = $this->nullableInt($subjectId);
        [$fromDate, $toDate] = $this->dateRange($from, $to);

        [$nodeToChapter, $chapterNames] = $this->chapterMaps($subInstituteId, $subjectIdInt);
        $nodeIdsFilter = $subjectIdInt !== null ? array_keys($nodeToChapter) : null;
        $conceptIdsFilter = $this->conceptIdsForSubject($subInstituteId, $subjectIdInt);

        $enrollment = DB::table('tblstudent_enrollment')
            ->where('student_id', $learnerIdInt)
            ->where('syear', $syear)
            ->whereNull('end_date')
            ->first(['standard_id', 'section_id']);

        $student = DB::table('tblstudent')->where('id', $learnerIdInt)->first(['first_name', 'middle_name', 'last_name']);
        $learnerName = $student !== null
            ? trim(preg_replace('/\s+/', ' ', trim(($student->first_name ?? '') . ' ' . ($student->middle_name ?? '') . ' ' . ($student->last_name ?? ''))) ?? '')
            : null;

        $mastery = $this->masteryOverview([$learnerIdInt], $nodeToChapter, $chapterNames, $nodeIdsFilter);
        $diagnostic = $this->diagnosticPerformance([$learnerIdInt], $subjectIdInt, $fromDate, $toDate);
        $adaptive = $this->adaptivePerformance([$learnerIdInt], $conceptIdsFilter, $fromDate, $toDate);
        $misconception = $this->misconceptionAnalysis([$learnerIdInt], $nodeIdsFilter);
        $gamification = $this->gamificationForStudent($learnerIdInt);
        $engagement = $this->engagementSection();
        $intelligence = $this->buildIntelligence($learnerIdInt, $misconception);

        return [
            'scope' => [
                'learner_id' => $learnerIdInt,
                'learner_name' => $learnerName !== '' ? $learnerName : null,
                'standard_id' => isset($enrollment->standard_id) ? (int) $enrollment->standard_id : null,
                'section_id' => isset($enrollment->section_id) ? (int) $enrollment->section_id : null,
                'subject_id' => $subjectIdInt,
                'syear' => $syear,
                'from' => $fromDate->toDateString(),
                'to' => $toDate->toDateString(),
            ],
            'mastery_overview' => $mastery,
            'diagnostic_performance' => $diagnostic,
            'adaptive_performance' => $adaptive,
            'misconception_analysis' => $misconception,
            'gamification' => $gamification,
            'engagement' => $engagement,
            'recommendations' => $this->studentRecommendations($mastery, $diagnostic, $adaptive, $misconception, $gamification),
            'intelligence' => $intelligence,
        ];
    }

    /**
     * A whole class's activity & performance report — aggregated, never
     * itemised per learner.
     *
     * @return array<string, mixed>
     */
    public function buildClassReport(
        int $subInstituteId,
        string $standardId,
        string $syear,
        ?string $sectionId = null,
        ?string $subjectId = null,
        ?string $from = null,
        ?string $to = null
    ): array {
        $standardIdInt = (int) $standardId;
        $sectionIdInt = $this->nullableInt($sectionId);
        $subjectIdInt = $this->nullableInt($subjectId);
        [$fromDate, $toDate] = $this->dateRange($from, $to);

        $studentIds = $this->cohortStudentIds($subInstituteId, $standardIdInt, $syear, $sectionIdInt);

        [$nodeToChapter, $chapterNames] = $this->chapterMaps($subInstituteId, $subjectIdInt);
        $nodeIdsFilter = $subjectIdInt !== null ? array_keys($nodeToChapter) : null;
        $conceptIdsFilter = $this->conceptIdsForSubject($subInstituteId, $subjectIdInt);

        $mastery = $this->masteryOverview($studentIds, $nodeToChapter, $chapterNames, $nodeIdsFilter);
        $diagnostic = $this->diagnosticPerformance($studentIds, $subjectIdInt, $fromDate, $toDate);
        $adaptive = $this->adaptivePerformance($studentIds, $conceptIdsFilter, $fromDate, $toDate);
        $misconception = $this->misconceptionAnalysis($studentIds, $nodeIdsFilter);
        $gamification = $this->gamificationForClass($studentIds);
        $engagement = $this->engagementSection();

        return [
            'scope' => [
                'standard_id' => $standardIdInt,
                'section_id' => $sectionIdInt,
                'subject_id' => $subjectIdInt,
                'syear' => $syear,
                'student_count' => count($studentIds),
                'from' => $fromDate->toDateString(),
                'to' => $toDate->toDateString(),
            ],
            'mastery_overview' => $mastery,
            'diagnostic_performance' => $diagnostic,
            'adaptive_performance' => $adaptive,
            'misconception_analysis' => $misconception,
            'gamification' => $gamification,
            'engagement' => $engagement,
            'action_items' => $this->classActionItems($mastery, $diagnostic, $adaptive, $misconception, $gamification, count($studentIds)),
        ];
    }

    // -------------------------------------------------------------------
    // Shared scope resolution
    // -------------------------------------------------------------------

    private function nullableInt(?string $value): ?int
    {
        return ($value !== null && $value !== '') ? (int) $value : null;
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function dateRange(?string $from, ?string $to): array
    {
        $toDate = ($to !== null && $to !== '') ? Carbon::parse($to)->endOfDay() : Carbon::now()->endOfDay();
        $fromDate = ($from !== null && $from !== '')
            ? Carbon::parse($from)->startOfDay()
            : $toDate->copy()->subDays(self::DEFAULT_WINDOW_DAYS)->startOfDay();

        return [$fromDate, $toDate];
    }

    /** Enrolled, currently-active students for a standard (+ optional section) in one year. */
    private function cohortStudentIds(int $subInstituteId, int $standardId, string $syear, ?int $sectionId): array
    {
        return DB::table('tblstudent_enrollment')
            ->where('sub_institute_id', $subInstituteId)
            ->where('standard_id', $standardId)
            ->where('syear', $syear)
            ->when($sectionId !== null, fn ($q) => $q->where('section_id', $sectionId))
            ->whereNull('end_date')
            ->pluck('student_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * node_id -> chapter_id, and chapter_id -> chapter_name, for every
     * ESO node whose concept resolves to a chapter in this institute (and,
     * when given, this subject). The join mirrors
     * EsoPolicyService::chapterDashboard()'s own path from a node to its
     * chapter: pal_concept_nodes.concept_id -> lms_concept.id ->
     * lms_concept.chapter_id -> chapter_master.id.
     *
     * @return array{0: array<int,int>, 1: array<int,string>}
     */
    private function chapterMaps(int $subInstituteId, ?int $subjectId): array
    {
        $rows = DB::table('pal_concept_nodes as n')
            ->join('lms_concept as c', 'c.id', '=', 'n.concept_id')
            ->join('chapter_master as ch', 'ch.id', '=', 'c.chapter_id')
            ->where('ch.sub_institute_id', $subInstituteId)
            ->when($subjectId !== null, fn ($q) => $q->where('ch.subject_id', $subjectId))
            ->select('n.id as node_id', 'ch.id as chapter_id', 'ch.chapter_name')
            ->get();

        $nodeToChapter = [];
        $chapterNames = [];

        foreach ($rows as $row) {
            $nodeToChapter[(int) $row->node_id] = (int) $row->chapter_id;
            $chapterNames[(int) $row->chapter_id] = $row->chapter_name;
        }

        return [$nodeToChapter, $chapterNames];
    }

    /**
     * Every lms_concept id under this subject's chapters, for tables that
     * carry concept_id but no subject_id of their own (pal_adaptive_response).
     * Null means "no subject filter", not "no concepts".
     */
    private function conceptIdsForSubject(int $subInstituteId, ?int $subjectId): ?array
    {
        if ($subjectId === null) {
            return null;
        }

        return DB::table('lms_concept as c')
            ->join('chapter_master as ch', 'ch.id', '=', 'c.chapter_id')
            ->where('ch.sub_institute_id', $subInstituteId)
            ->where('ch.subject_id', $subjectId)
            ->pluck('c.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    // -------------------------------------------------------------------
    // Section builders — one student id or many; the aggregation is
    // identical, only the cardinality of $studentIds differs between the
    // student and class reports.
    // -------------------------------------------------------------------

    /**
     * @param  array<int,int>  $studentIds
     * @param  array<int,int>  $nodeToChapter
     * @param  array<int,string>  $chapterNames
     * @param  array<int,int>|null  $nodeIdsFilter
     */
    private function masteryOverview(array $studentIds, array $nodeToChapter, array $chapterNames, ?array $nodeIdsFilter): array
    {
        $query = LearnerNodeState::query()->whereIn('student_id', $studentIds);
        if ($nodeIdsFilter !== null) {
            $query->whereIn('node_id', $nodeIdsFilter);
        }
        $states = $query->get(['student_id', 'node_id', 'status', 'mastery_estimate']);

        $masteredStatuses = [LearnerNodeState::STATUS_MASTERED, LearnerNodeState::STATUS_RETAINED];
        $estimates = $states->pluck('mastery_estimate')->filter(fn ($v) => $v !== null);

        $grouped = $states->groupBy(fn ($s) => $nodeToChapter[(int) $s->node_id] ?? 0);

        $byChapter = $grouped->map(function ($rows, $chapterId) use ($chapterNames, $masteredStatuses) {
            $chapterId = (int) $chapterId;
            $chapterEstimates = $rows->pluck('mastery_estimate')->filter(fn ($v) => $v !== null);

            return [
                'chapter_id' => $chapterId === 0 ? null : $chapterId,
                'chapter_name' => $chapterId === 0 ? 'Unassigned' : ($chapterNames[$chapterId] ?? 'Unassigned'),
                'nodes_total' => $rows->count(),
                'mastered' => $rows->filter(fn ($s) => in_array($s->status, $masteredStatuses, true))->count(),
                'learning' => $rows->where('status', LearnerNodeState::STATUS_LEARNING)->count(),
                'unseen' => $rows->where('status', LearnerNodeState::STATUS_UNSEEN)->count(),
                'misconception_flagged' => $rows->where('status', LearnerNodeState::STATUS_MISCONCEPTION_FLAGGED)->count(),
                'mastery_estimate_avg' => $this->asPercentage($chapterEstimates),
            ];
        })->sortByDesc('nodes_total')->values()->all();

        return [
            'source' => 'learner_node_state',
            'nodes_total' => $states->count(),
            'mastered' => $states->filter(fn ($s) => in_array($s->status, $masteredStatuses, true))->count(),
            'learning' => $states->where('status', LearnerNodeState::STATUS_LEARNING)->count(),
            'unseen' => $states->where('status', LearnerNodeState::STATUS_UNSEEN)->count(),
            'misconception_flagged' => $states->where('status', LearnerNodeState::STATUS_MISCONCEPTION_FLAGGED)->count(),
            'mastery_estimate_avg' => $this->asPercentage($estimates),
            'by_chapter' => $byChapter,
        ];
    }

    /**
     * learner_node_state.mastery_estimate is stored as a 0.0-1.0 fraction
     * (EsoPolicyService compares it directly against thresholds like 0.8/0.75
     * — see ConceptNode::effectiveThreshold), but every other figure in this
     * report (diagnostic avg_percentage, adaptive accuracy, the difficulty
     * band breakdown) is 0-100. Scaling here, once, is what keeps
     * "mastery_estimate_avg" from reading as "0.3%" when the true figure is
     * 30%.
     *
     * @param  \Illuminate\Support\Collection<int, float>  $fractions
     */
    private function asPercentage($fractions): ?float
    {
        return $fractions->isEmpty() ? null : round((float) $fractions->avg() * 100, 1);
    }

    /** @param array<int,int> $studentIds */
    private function diagnosticPerformance(array $studentIds, ?int $subjectId, Carbon $from, Carbon $to): array
    {
        $query = DiagnosticAttempt::query()
            ->whereIn('student_id', $studentIds)
            ->submitted()
            ->whereBetween('submitted_at', [$from, $to]);

        if ($subjectId !== null) {
            $query->forSubject($subjectId);
        }

        $attempts = $query->orderBy('submitted_at')->get(['student_id', 'percentage', 'difficulty_breakdown', 'submitted_at']);

        $trend = $attempts
            ->groupBy(fn ($a) => $a->submitted_at?->toDateString() ?? 'unknown')
            ->map(fn ($rows) => round((float) $rows->avg('percentage'), 1))
            ->sortKeys()
            ->map(fn ($pct, $date) => ['date' => $date, 'percentage' => $pct])
            ->values()
            ->all();

        $bands = DifficultyBands::BANDS;
        $byBand = array_fill_keys($bands, ['served' => 0, 'correct' => 0, 'incorrect' => 0, 'unanswered' => 0, 'percentage' => null]);

        foreach ($attempts as $attempt) {
            $breakdown = $attempt->difficulty_breakdown ?? [];
            foreach ($bands as $band) {
                $b = $breakdown[$band] ?? null;
                if (! $b) {
                    continue;
                }
                $byBand[$band]['served'] += (int) ($b['served'] ?? 0);
                $byBand[$band]['correct'] += (int) ($b['correct'] ?? 0);
                $byBand[$band]['incorrect'] += (int) ($b['incorrect'] ?? 0);
                $byBand[$band]['unanswered'] += (int) ($b['unanswered'] ?? 0);
            }
        }

        foreach ($bands as $band) {
            $byBand[$band]['percentage'] = $byBand[$band]['served'] > 0
                ? round($byBand[$band]['correct'] / $byBand[$band]['served'] * 100, 1)
                : null;
        }

        return [
            'attempts_count' => $attempts->count(),
            'avg_percentage' => $attempts->isEmpty() ? null : round((float) $attempts->avg('percentage'), 1),
            'trend' => $trend,
            'by_difficulty_band' => $byBand,
        ];
    }

    /**
     * @param  array<int,int>  $studentIds
     * @param  array<int,int>|null  $conceptIds
     */
    private function adaptivePerformance(array $studentIds, ?array $conceptIds, Carbon $from, Carbon $to): array
    {
        $query = AdaptiveResponse::query()
            ->whereIn('student_id', $studentIds)
            ->whereBetween('created_at', [$from, $to]);

        if ($conceptIds !== null) {
            $query->whereIn('concept_id', $conceptIds);
        }

        $responses = $query->get(['is_correct', 'rule_fired']);
        $count = $responses->count();
        $correct = $responses->where('is_correct', true)->count();

        $ruleBreakdown = $responses
            ->groupBy(fn ($r) => ($r->rule_fired !== null && $r->rule_fired !== '') ? $r->rule_fired : 'unknown')
            ->map(fn ($g) => $g->count())
            ->sortDesc()
            ->all();

        return [
            'responses_count' => $count,
            'accuracy' => $count > 0 ? round($correct / $count * 100, 1) : null,
            'rule_fired_breakdown' => $ruleBreakdown,
        ];
    }

    /**
     * @param  array<int,int>  $studentIds
     * @param  array<int,int>|null  $nodeIdsFilter
     */
    private function misconceptionAnalysis(array $studentIds, ?array $nodeIdsFilter): array
    {
        $query = LearnerNodeState::query()
            ->whereIn('student_id', $studentIds)
            ->whereNotNull('active_misconception_id');

        if ($nodeIdsFilter !== null) {
            $query->whereIn('node_id', $nodeIdsFilter);
        }

        $active = $query->get(['active_misconception_id']);
        $counts = $active->countBy(fn ($row) => (int) $row->active_misconception_id);

        $top = MisconceptionLibrary::query()
            ->whereIn('id', $counts->keys()->all())
            ->get(['id', 'tag', 'description', 'corrective_action'])
            ->map(fn ($lib) => [
                // Carried through so buildIntelligence() can call
                // MisconceptionIntelligenceEngine::getRemediation() for the
                // top-ranked entry without a second lookup — this id is a
                // pal_misconception_library id, so remediation calls MUST
                // pass source='library' (ids are only unique within a
                // registry; see that engine's own cluster()/getRemediation()
                // docblocks).
                'id' => (int) $lib->id,
                'tag' => $lib->tag,
                'description' => $lib->description,
                'corrective_action' => $lib->corrective_action,
                // How many of THIS report's scoped rows are currently
                // flagged with this misconception — not the library's
                // lifetime, platform-wide detection_count, which would not
                // describe this learner/class at all.
                'detection_count' => (int) ($counts[(int) $lib->id] ?? 0),
            ])
            ->sortByDesc('detection_count')
            ->take(10)
            ->values()
            ->all();

        return [
            'active_count' => $active->count(),
            'top_misconceptions' => $top,
        ];
    }

    private function gamificationForStudent(int $learnerId): array
    {
        $streak = LearnerStreak::where('learner_id', $learnerId)->first();
        $badges = LearnerBadge::where('learner_id', $learnerId)->held()->get(['badge_id', 'scope_key', 'awarded_at']);

        $catalog = $badges->isEmpty()
            ? collect()
            : Badge::whereIn('badge_id', $badges->pluck('badge_id')->unique()->all())->get()->keyBy('badge_id');

        $badgeList = $badges->map(function ($badge) use ($catalog) {
            $meta = $catalog->get($badge->badge_id);

            return [
                'badge_id' => $badge->badge_id,
                'name' => $meta->name ?? $badge->badge_id,
                'category' => $meta->category ?? null,
                'scope_key' => $badge->scope_key,
                'awarded_at' => optional($badge->awarded_at)->toDateString(),
            ];
        })->values()->all();

        return [
            'has_sufficient_data' => $streak !== null || $badgeList !== [],
            'streak' => $streak !== null ? [
                'current_streak' => $streak->current_streak,
                'longest_streak' => $streak->longest_streak,
                'total_active_days' => $streak->total_active_days,
                'last_active_date' => optional($streak->last_active_date)->toDateString(),
            ] : null,
            'badges' => $badgeList,
        ];
    }

    /** @param array<int,int> $studentIds */
    private function gamificationForClass(array $studentIds): array
    {
        $total = count($studentIds);

        if ($total === 0) {
            return [
                'has_sufficient_data' => false,
                'students_with_data' => 0,
                'students_total' => 0,
                'message' => 'No students are enrolled in this scope.',
            ];
        }

        $withStreak = LearnerStreak::whereIn('learner_id', $studentIds)->pluck('learner_id')->map(fn ($id) => (int) $id);
        $withBadge = LearnerBadge::whereIn('learner_id', $studentIds)->held()->distinct()->pluck('learner_id')->map(fn ($id) => (int) $id);
        $withData = $withStreak->merge($withBadge)->unique()->count();

        return [
            'has_sufficient_data' => $withData > 0,
            'students_with_data' => $withData,
            'students_total' => $total,
            'message' => sprintf('%d of %d students have gamification data (streak or badge rows).', $withData, $total),
        ];
    }

    /**
     * Always the same canned shape — see the class docblock for why a
     * computed number is refused here rather than derived from near-empty
     * tables.
     */
    private function engagementSection(): array
    {
        return [
            'has_sufficient_data' => false,
            'message' => 'Not enough session/activity data has been recorded yet for meaningful engagement metrics.',
        ];
    }

    // -------------------------------------------------------------------
    // Learning Intelligence — velocity/plateau/regression/risk/remediation.
    // Student-report only (see buildStudentReport() vs buildClassReport()):
    // every one of these reads learner_node_state/pal_competencies/
    // pal_learning_sessions/pal_session_events at the single-learner grain,
    // and PredictiveInterventionEngine in particular has no cohort-aggregate
    // form to fall back on, so there is no honest "class" shape to build here
    // — unlike mastery_overview etc, which are the same aggregation whether
    // $studentIds has one id or many.
    // -------------------------------------------------------------------

    /**
     * Reuses three existing PAL Intelligence engines — the same ones already
     * serving the Intelligence dashboard via PALAPIController/IntelligenceService
     * (getLearningVelocity/detectPlateau/detectRegression,
     * predictDisengagement/predictFailure/predictBurnout, getRemediation) —
     * rather than recomputing any of this report-side.
     *
     * @param  array<string,mixed>  $misconceptionAnalysis  this report's own
     *         misconceptionAnalysis() output, used only to find the
     *         top-ranked misconception's library id for remediation().
     */
    private function buildIntelligence(int $learnerId, array $misconceptionAnalysis): array
    {
        return [
            'staleness_note' => 'Velocity, plateau and regression figures come from the last derived competency snapshot. The recalculation job (pal:derive-competencies) is not on a schedule today, so "as of" below reflects evidence age, not when it was last run.',
            'velocity' => $this->safeEngineCall('velocity', fn () => $this->velocityEngine->calculate($learnerId, 'week')),
            'plateau' => $this->safeEngineCall('plateau', fn () => $this->velocityEngine->detectPlateau($learnerId)),
            'regression' => $this->safeEngineCall('regression', fn () => $this->velocityEngine->detectRegression($learnerId)),
            'risk' => [
                'disengagement' => $this->safeEngineCall('risk.disengagement', fn () => $this->interventionEngine->predictDisengagement($learnerId)),
                'failure' => $this->safeEngineCall('risk.failure', fn () => $this->interventionEngine->predictFailure($learnerId)),
                'burnout' => $this->safeEngineCall('risk.burnout', fn () => $this->interventionEngine->predictBurnout($learnerId)),
            ],
            'remediation' => $this->remediationForTopMisconception($learnerId, $misconceptionAnalysis),
        ];
    }

    /**
     * Runs one engine call, degrading to a bare has_data=false shape instead
     * of letting an exception break the whole report. These engines read
     * thin, often-empty tables (pal_learning_sessions has ~1 row,
     * pal_session_events ~18, platform-wide) — has_data:false for most/all
     * learners is the CORRECT, expected reading of that, not a bug this
     * report should try to paper over.
     */
    private function safeEngineCall(string $label, callable $callback): array
    {
        try {
            $result = $callback();

            return is_array($result) ? $result : ['has_data' => false];
        } catch (\Throwable $e) {
            Log::warning("StudentActivityReportService: intelligence engine call [{$label}] failed", [
                'error' => $e->getMessage(),
            ]);

            return ['has_data' => false];
        }
    }

    /**
     * Remediation for the top-ranked ACTIVE misconception only — never a
     * general lookup. misconceptionAnalysis()'s top_misconceptions entries
     * carry a pal_misconception_library id (see that method), so this always
     * passes source='library' to MisconceptionIntelligenceEngine::getRemediation();
     * the engine's own default ('runtime') points at a different, numerically
     * overlapping id space and would silently fetch the wrong row.
     */
    private function remediationForTopMisconception(int $learnerId, array $misconceptionAnalysis): ?array
    {
        $top = $misconceptionAnalysis['top_misconceptions'][0] ?? null;
        $misconceptionId = $top['id'] ?? null;

        if ($misconceptionId === null) {
            return null;
        }

        try {
            $raw = $this->misconceptionEngine->getRemediation($learnerId, (int) $misconceptionId, 'library');
        } catch (\Throwable $e) {
            Log::warning('StudentActivityReportService: remediation lookup failed', [
                'learner_id' => $learnerId,
                'misconception_id' => $misconceptionId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        return $this->simplifyRemediation($raw);
    }

    /**
     * Flattens MisconceptionIntelligenceEngine::getRemediation()'s raw,
     * nested shape (misconception{}/pre_defined_remediations[]/
     * attempts_by_learner, or {error: ...} when not found) into the lean
     * {tag, content_excerpt} contract — the one shape both the JSON API
     * response and the .docx renderer consume, so the engine's internal
     * structure never leaks into the public API and there is exactly one
     * place that decides what counts as "the excerpt" rather than two
     * separate extractions (JSON vs. docx) silently drifting apart.
     */
    private function simplifyRemediation(?array $raw): ?array
    {
        if ($raw === null || isset($raw['error'])) {
            return null;
        }

        $misconception = (array) ($raw['misconception'] ?? []);
        $tag = (string) ($misconception['tag'] ?? '');
        if ($tag === '') {
            return null;
        }

        // `pre_defined_remediations` comes back as an Illuminate\Support\Collection,
        // not a plain array — (array) casting a Collection reads its internal
        // properties (" * items", " * escapeWhenCastingToString"), not its
        // contents, and silently produces an empty-looking array. collect(...)
        // ->toArray() is the correct normalization regardless of whether the
        // source is already an array or a Collection.
        $predefined = collect($raw['pre_defined_remediations'] ?? [])->toArray();
        $excerptSource = (string) ($predefined[0]['body'] ?? $misconception['corrective_action'] ?? $misconception['description'] ?? '');

        return [
            'tag' => $tag,
            'content_excerpt' => $this->excerpt($excerptSource, 220),
        ];
    }

    private function excerpt(string $text, int $maxLength): string
    {
        $text = trim((string) preg_replace('/\s+/', ' ', strip_tags($text)));
        if ($text === '') {
            return '';
        }

        return mb_strlen($text) > $maxLength ? mb_substr($text, 0, $maxLength - 1).'…' : $text;
    }

    // -------------------------------------------------------------------
    // Recommendations — deterministic, grounded only in this report's own
    // numbers (no LLM call: unlike attainmentNarrative() there is nothing to
    // narrate in prose, only a short rule-based punch list).
    // -------------------------------------------------------------------

    private function studentRecommendations(array $mastery, array $diagnostic, array $adaptive, array $misconception, array $gamification): array
    {
        $out = [];

        if ($mastery['nodes_total'] === 0) {
            $out[] = 'No PAL mastery activity recorded yet for this scope — start a chapter diagnostic to establish a baseline.';
        } else {
            if ($misconception['active_count'] > 0) {
                $top = $misconception['top_misconceptions'][0]['tag'] ?? null;
                $out[] = $top !== null
                    ? sprintf('Review the %d active misconception(s), starting with "%s".', $misconception['active_count'], $top)
                    : sprintf('Review the %d active misconception(s) flagged below.', $misconception['active_count']);
            }

            if ($mastery['mastery_estimate_avg'] !== null && $mastery['mastery_estimate_avg'] < 50.0) {
                $weakest = collect($mastery['by_chapter'])->sortBy('mastery_estimate_avg')->first();
                $out[] = $weakest !== null
                    ? sprintf('Mastery estimate is below 50%% overall; prioritise "%s" (%s).', $weakest['chapter_name'], $weakest['mastery_estimate_avg'] !== null ? number_format($weakest['mastery_estimate_avg'], 1) . '%' : 'no data')
                    : 'Mastery estimate is below 50% overall; schedule focused practice.';
            }

            if ($mastery['unseen'] > 0) {
                $out[] = sprintf('%d of %d node(s) have not been attempted at all — plan to introduce them.', $mastery['unseen'], $mastery['nodes_total']);
            }
        }

        if ($diagnostic['attempts_count'] === 0) {
            $out[] = 'No diagnostic attempts in this period — take at least one chapter diagnostic to get a difficulty-band breakdown.';
        } elseif ($diagnostic['avg_percentage'] !== null && $diagnostic['avg_percentage'] < 60.0) {
            $out[] = sprintf('Diagnostic average is %s%%, below the 60%% mark — revisit the weakest difficulty band.', number_format($diagnostic['avg_percentage'], 1));
        }

        if ($adaptive['responses_count'] === 0) {
            $out[] = 'No adaptive practice recorded in this period — regular practice is what moves nodes from learning to mastered.';
        }

        if (! $gamification['has_sufficient_data']) {
            $out[] = 'Not enough streak/badge activity yet to show gamification progress.';
        }

        return $out === [] ? ['No action needed — mastery, diagnostic and adaptive performance are all on track for this period.'] : $out;
    }

    private function classActionItems(array $mastery, array $diagnostic, array $adaptive, array $misconception, array $gamification, int $studentCount): array
    {
        if ($studentCount === 0) {
            return ['No students are enrolled in this scope for the selected year.'];
        }

        $out = [];

        if ($mastery['nodes_total'] === 0) {
            $out[] = 'No PAL mastery activity recorded yet for this class/subject — confirm students have started their diagnostics.';
        } else {
            if ($misconception['active_count'] > 0) {
                $top = $misconception['top_misconceptions'][0]['tag'] ?? null;
                $out[] = $top !== null
                    ? sprintf('%d active misconception flag(s) across the class; prioritise re-teaching "%s".', $misconception['active_count'], $top)
                    : sprintf('%d active misconception flag(s) across the class.', $misconception['active_count']);
            }

            if ($mastery['mastery_estimate_avg'] !== null && $mastery['mastery_estimate_avg'] < 50.0) {
                $weakest = collect($mastery['by_chapter'])->sortBy('mastery_estimate_avg')->first();
                $out[] = $weakest !== null
                    ? sprintf('Class mastery estimate is below 50%%; "%s" is the weakest chapter (%s).', $weakest['chapter_name'], $weakest['mastery_estimate_avg'] !== null ? number_format($weakest['mastery_estimate_avg'], 1) . '%' : 'no data')
                    : 'Class mastery estimate is below 50% overall.';
            }
        }

        if ($diagnostic['attempts_count'] === 0) {
            $out[] = 'No diagnostic attempts recorded for this class in this period.';
        } elseif ($diagnostic['avg_percentage'] !== null && $diagnostic['avg_percentage'] < 60.0) {
            $out[] = sprintf('Class diagnostic average is %s%%, below the 60%% mark.', number_format($diagnostic['avg_percentage'], 1));
        }

        if ($adaptive['responses_count'] === 0) {
            $out[] = 'No adaptive practice recorded for this class in this period.';
        }

        $out[] = $gamification['message'];

        return $out;
    }
}
