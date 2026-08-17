<?php

namespace App\Services\PAL\Gamification;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * New PAL → Gamification: the single read layer over REAL learner activity.
 *
 * Every number the gamification module shows resolves, eventually, to a method
 * on this class. Nothing above it invents a value, and nothing above it queries
 * the estate directly — which is what makes the "100% dynamic" requirement
 * checkable rather than aspirational.
 *
 * WHERE THE DATA COMES FROM
 * -------------------------
 * The estate's real PAL learning record is the PAL quiz stream:
 *
 *   question_paper (exam_type = 'PAL')  the generated practice paper; its
 *                                       `paper_desc` carries the chapter id,
 *                                       exactly as PalWorkspaceController reads it
 *   lms_online_exam                     one attempt: right / wrong / obtain_marks /
 *                                       accuracy_rate / avg_time / start_time
 *   lms_online_exam_answer              the per-item answers behind an attempt
 *
 * On top of that, whichever PAL V4 tables this estate has populated are folded
 * in where they exist: pal_competencies (authored BKT mastery),
 * pal_learning_sessions, pal_session_events, pal_telemetry_events,
 * pal_learning_evidence, pal_framework_progress, pal_learner_misconceptions,
 * pal_collaboration_activities, pal_unified_learning_units.
 *
 * A table that is absent or empty contributes nothing — it never contributes a
 * placeholder. That is why a fresh estate renders honest empty states instead
 * of a demo dataset.
 *
 * THE CONCEPT REFERENCE
 * ---------------------
 * PAL's learnable unit has two possible identities on this estate, so both are
 * normalised into one opaque string:
 *
 *   "chapter:8104"      a chapter_master row — the unit PAL practice papers
 *                       are actually generated against
 *   "pal_concept:45"    a pal_concepts row — used when pal_competencies has
 *                       authored mastery for the learner
 *
 * Everything downstream (badges, personal bests, challenges, career quest)
 * keys off that string and never needs to know which estate it is running on.
 */
class LearnerActivitySource
{
    /** Per-request memoisation — one learner's page hits these repeatedly. */
    private array $memo = [];

    // =====================================================================
    // Learner + class scope
    // =====================================================================

    /**
     * The learner's identity and class placement.
     *
     * `grade_number` is parsed from the standard's name because the Career
     * Quest stages and the Challenge Mode grade floor are defined in grades,
     * and this estate stores the grade as a label. When it cannot be parsed it
     * stays null and every grade-gated feature reports "grade unknown" rather
     * than guessing a stage for a child.
     */
    public function learner(int $learnerId): ?array
    {
        return $this->once("learner:{$learnerId}", function () use ($learnerId) {
            $row = DB::table('tblstudent as s')
                ->leftJoin('tblstudent_enrollment as se', function ($join) {
                    $join->on('s.id', '=', 'se.student_id')->whereNull('se.end_date');
                })
                ->leftJoin('standard as st', 'st.id', '=', 'se.standard_id')
                ->leftJoin('academic_section as ac', 'ac.id', '=', 'se.grade_id')
                ->leftJoin('division as dv', 'dv.id', '=', 'se.section_id')
                ->where('s.id', $learnerId)
                ->orderByDesc('se.syear')
                ->selectRaw("s.id, CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS name,
                    s.first_name, s.enrollment_no, s.sub_institute_id,
                    se.syear, se.grade_id, se.standard_id, se.section_id AS division_id,
                    st.name AS standard_name, st.short_name AS standard_short,
                    ac.title AS grade_name, dv.name AS division_name")
                ->first();

            if ($row === null) {
                return null;
            }

            return [
                'learner_id' => (int) $row->id,
                'name' => trim((string) $row->name),
                'first_name' => trim((string) $row->first_name),
                'enrollment_no' => (string) ($row->enrollment_no ?? ''),
                'sub_institute_id' => (int) ($row->sub_institute_id ?? 0),
                'syear' => $row->syear !== null ? (int) $row->syear : null,
                'grade_id' => $row->grade_id !== null ? (int) $row->grade_id : null,
                'standard_id' => $row->standard_id !== null ? (int) $row->standard_id : null,
                'division_id' => $row->division_id !== null ? (int) $row->division_id : null,
                'standard_name' => (string) ($row->standard_name ?? ''),
                'grade_name' => (string) ($row->grade_name ?? ''),
                'division_name' => (string) ($row->division_name ?? ''),
                'grade_number' => $this->parseGradeNumber(
                    (string) ($row->standard_name ?? ''),
                    (string) ($row->standard_short ?? '')
                ),
            ];
        });
    }

    /**
     * The other learners in the same class. Used only for AGGREGATES — team
     * challenge progress and the opt-in Challenge Mode leaderboard. No caller
     * is ever handed a peer's mastery, badges or streak for a student-facing
     * payload; GamificationVisibility enforces that separately.
     */
    public function classmates(array $learner): array
    {
        $standardId = $learner['standard_id'] ?? null;
        if ($standardId === null) {
            return [];
        }

        $key = 'classmates:' . implode(':', [
            $learner['sub_institute_id'] ?? 0,
            $standardId,
            $learner['division_id'] ?? 0,
            $learner['syear'] ?? 0,
        ]);

        return $this->once($key, function () use ($learner, $standardId) {
            return DB::table('tblstudent as s')
                ->join('tblstudent_enrollment as se', function ($join) {
                    $join->on('s.id', '=', 'se.student_id')->whereNull('se.end_date');
                })
                ->where('se.standard_id', $standardId)
                ->when(! empty($learner['sub_institute_id']), fn ($q) => $q->where('s.sub_institute_id', $learner['sub_institute_id']))
                ->when(! empty($learner['division_id']), fn ($q) => $q->where('se.section_id', $learner['division_id']))
                ->when(! empty($learner['syear']), fn ($q) => $q->where('se.syear', $learner['syear']))
                ->selectRaw("s.id, s.first_name, CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS name")
                ->groupBy('s.id')
                ->limit(500)
                ->get()
                ->map(fn ($r) => [
                    'learner_id' => (int) $r->id,
                    'name' => trim((string) $r->name),
                    'first_name' => trim((string) $r->first_name),
                ])
                ->all();
        });
    }

    /** Class members for an arbitrary class scope (teacher-configured challenges). */
    public function classRoster(array $scope): array
    {
        $key = 'roster:' . md5(json_encode($scope));

        return $this->once($key, function () use ($scope) {
            $standardId = $scope['standard_id'] ?? null;
            if (! $standardId) {
                return [];
            }

            return DB::table('tblstudent as s')
                ->join('tblstudent_enrollment as se', function ($join) {
                    $join->on('s.id', '=', 'se.student_id')->whereNull('se.end_date');
                })
                ->where('se.standard_id', $standardId)
                ->when(! empty($scope['sub_institute_id']), fn ($q) => $q->where('s.sub_institute_id', $scope['sub_institute_id']))
                ->when(! empty($scope['division_id']), fn ($q) => $q->where('se.section_id', $scope['division_id']))
                ->when(! empty($scope['syear']), fn ($q) => $q->where('se.syear', $scope['syear']))
                ->selectRaw("s.id, s.first_name, CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS name")
                ->groupBy('s.id')
                ->limit(500)
                ->get()
                ->map(fn ($r) => [
                    'learner_id' => (int) $r->id,
                    'name' => trim((string) $r->name),
                    'first_name' => trim((string) $r->first_name),
                ])
                ->all();
        });
    }

    // =====================================================================
    // Attempts — the primary activity stream
    // =====================================================================

    /**
     * Every PAL practice attempt this learner has made, oldest first, with
     * accuracy and net fluency derived per attempt.
     *
     * @return Collection<int,array<string,mixed>>
     */
    public function attempts(int $learnerId): Collection
    {
        return $this->once("attempts:{$learnerId}", fn () => $this->attemptsFor([$learnerId])->get($learnerId, collect()));
    }

    /**
     * Attempts for many learners at once — one query for a whole class.
     *
     * @return Collection<int,Collection<int,array<string,mixed>>> keyed by learner id
     */
    public function attemptsFor(array $learnerIds): Collection
    {
        $learnerIds = array_values(array_unique(array_filter(array_map('intval', $learnerIds))));
        if ($learnerIds === []) {
            return collect();
        }

        $key = 'attemptsFor:' . md5(implode(',', $learnerIds));

        return $this->once($key, function () use ($learnerIds) {
            $rows = DB::table('question_paper as q')
                ->join('lms_online_exam as l', 'l.question_paper_id', '=', 'q.id')
                ->whereIn('l.student_id', $learnerIds)
                ->where('q.exam_type', 'PAL')
                ->orderBy('l.start_time')
                ->selectRaw('l.id AS attempt_id, l.student_id, l.question_paper_id,
                    l.total_right, l.total_wrong, l.obtain_marks, l.accuracy_rate, l.avg_time,
                    l.start_time, l.created_at,
                    q.paper_desc, q.paper_name, q.subject_id, q.standard_id, q.grade_id,
                    q.sub_institute_id, q.syear, q.total_ques, q.total_marks')
                ->limit(20000)
                ->get();

            if ($rows->isEmpty()) {
                return collect();
            }

            $paperIds = $rows->pluck('question_paper_id')->unique()->filter()->values()->all();
            $baselines = $this->paperPaceBaselines($paperIds);

            $chapterIds = $rows->pluck('paper_desc')
                ->map(fn ($v) => (int) $v)
                ->filter()
                ->unique()
                ->values()
                ->all();
            $chapterNames = $chapterIds === [] ? [] : DB::table('chapter_master')
                ->whereIn('id', $chapterIds)
                ->pluck('chapter_name', 'id')
                ->all();

            $subjectIds = $rows->pluck('subject_id')->filter()->unique()->values()->all();
            $subjectNames = $this->subjectNames($subjectIds);

            $cfg = (array) config('pal_gamification.fluency', []);
            $minItems = (int) config('pal_gamification.safeguards.exclude_attempts_with_fewer_items_than', 3);

            $grouped = [];
            foreach ($rows as $row) {
                $right = (int) $row->total_right;
                $wrong = (int) $row->total_wrong;
                $items = $right + $wrong;
                if ($items <= 0) {
                    continue;
                }

                $accuracy = $row->accuracy_rate !== null
                    ? round(((float) $row->accuracy_rate) / 100, 4)
                    : round($right / $items, 4);
                $accuracy = max(0.0, min(1.0, $accuracy));

                $avgTime = $row->avg_time !== null ? (float) $row->avg_time : null;
                $baseline = $baselines[(int) $row->question_paper_id] ?? null;
                $speedIndex = $this->speedIndex($avgTime, $baseline, $cfg);

                $chapterId = (int) $row->paper_desc;
                $conceptRef = $chapterId > 0 ? "chapter:{$chapterId}" : "paper:{$row->question_paper_id}";
                $conceptLabel = $chapterId > 0
                    ? (string) ($chapterNames[$chapterId] ?? ('Chapter #' . $chapterId))
                    : (string) ($row->paper_name ?: ('Paper #' . $row->question_paper_id));

                $occurredAt = $row->start_time ?: $row->created_at;

                $grouped[(int) $row->student_id][] = [
                    'attempt_id' => (int) $row->attempt_id,
                    'learner_id' => (int) $row->student_id,
                    'question_paper_id' => (int) $row->question_paper_id,
                    'concept_ref' => $conceptRef,
                    'concept_label' => $conceptLabel,
                    'chapter_id' => $chapterId > 0 ? $chapterId : null,
                    'subject_id' => $row->subject_id !== null ? (int) $row->subject_id : null,
                    'subject_name' => (string) ($subjectNames[(int) $row->subject_id] ?? ''),
                    'standard_id' => $row->standard_id !== null ? (int) $row->standard_id : null,
                    'sub_institute_id' => $row->sub_institute_id !== null ? (int) $row->sub_institute_id : null,
                    'syear' => $row->syear !== null ? (int) $row->syear : null,
                    'right' => $right,
                    'wrong' => $wrong,
                    'items' => $items,
                    'accuracy' => $accuracy,
                    'avg_time_seconds' => $avgTime,
                    'baseline_time_seconds' => $baseline,
                    'speed_index' => $speedIndex,
                    'net_fluency' => round(max(0.0, min(1.0, $accuracy * $speedIndex)), 4),
                    'has_pace' => $avgTime !== null && $avgTime > 0,
                    'duration_seconds' => $avgTime !== null ? (int) round($avgTime * $items) : 0,
                    'counts_toward_mastery' => $items >= $minItems,
                    'occurred_at' => $occurredAt ? Carbon::parse($occurredAt) : null,
                ];
            }

            return collect($grouped)->map(fn ($list) => collect($list));
        });
    }

    /**
     * The typical pace on a paper, taken from its own attempt history.
     *
     * This is what makes "fast" mean something real: the learner is compared
     * against how long this exact paper actually takes people, never against an
     * invented target. Papers with too little history return null and their
     * attempts fall back to plain accuracy.
     */
    private function paperPaceBaselines(array $paperIds): array
    {
        if ($paperIds === []) {
            return [];
        }

        $minAttempts = (int) config('pal_gamification.fluency.baseline_min_attempts', 3);

        $rows = DB::table('lms_online_exam')
            ->whereIn('question_paper_id', $paperIds)
            ->whereNotNull('avg_time')
            ->where('avg_time', '>', 0)
            ->select('question_paper_id', 'avg_time')
            ->limit(50000)
            ->get()
            ->groupBy('question_paper_id');

        $baselines = [];
        foreach ($rows as $paperId => $group) {
            if ($group->count() < $minAttempts) {
                continue;
            }
            $values = $group->pluck('avg_time')->map(fn ($v) => (float) $v)->sort()->values();
            $mid = intdiv($values->count(), 2);
            $baselines[(int) $paperId] = $values->count() % 2 === 0
                ? round((($values[$mid - 1] + $values[$mid]) / 2), 4)
                : round((float) $values[$mid], 4);
        }

        return $baselines;
    }

    private function speedIndex(?float $avgTime, ?float $baseline, array $cfg): float
    {
        if ($avgTime === null || $avgTime <= 0 || $baseline === null || $baseline <= 0) {
            return 1.0;
        }

        $min = (float) ($cfg['speed_index_min'] ?? 0.5);
        $max = (float) ($cfg['speed_index_max'] ?? 1.25);

        return round(max($min, min($max, $baseline / $avgTime)), 4);
    }

    // =====================================================================
    // Concept records — mastery, tier, fluency, per learnable unit
    // =====================================================================

    /**
     * One record per concept the learner has touched, carrying the mastery
     * estimate, its Stream / Mountain / Sky tier, best and latest net fluency,
     * how many sessions it took, and where the mastery number came from.
     *
     * Mastery resolution order:
     *   1. pal_competencies.mastery_score — authored BKT, used verbatim
     *   2. otherwise an estimate from the learner's own attempt history:
     *      an exponentially weighted mean of attempt accuracy, most recent
     *      attempt weighted heaviest. Reported with source = 'attempt_history'
     *      so the UI can say where the number came from rather than implying a
     *      BKT run that did not happen.
     *
     * @return array<string,array<string,mixed>> keyed by concept_ref
     */
    public function conceptRecords(int $learnerId): array
    {
        return $this->once("concepts:{$learnerId}", function () use ($learnerId) {
            $records = [];

            // --- 1. Authored BKT mastery, where the estate has it -----------
            foreach ($this->authoredCompetencies($learnerId) as $row) {
                $ref = 'pal_concept:' . $row['concept_id'];
                $records[$ref] = [
                    'concept_ref' => $ref,
                    'concept_label' => $row['concept_name'],
                    'subject_id' => $row['subject_id'],
                    'subject_name' => $row['subject_name'],
                    'mastery' => $row['mastery'],
                    'mastery_source' => 'pal_competencies',
                    'tier' => $this->tierFor($row['mastery']),
                    'sessions' => 0,
                    'items' => 0,
                    'best_net_fluency' => null,
                    'latest_net_fluency' => null,
                    'first_seen_at' => $row['created_at'],
                    'last_seen_at' => $row['last_assessed'],
                    'bloom_level' => $row['bloom_level'],
                    'trend' => $row['trend'],
                ];
            }

            // --- 2. Attempt-derived mastery for practised chapters ----------
            $byConcept = $this->attempts($learnerId)->groupBy('concept_ref');
            foreach ($byConcept as $ref => $attempts) {
                $counting = $attempts->filter(fn ($a) => $a['counts_toward_mastery'])->values();
                $usable = $counting->isNotEmpty() ? $counting : $attempts->values();

                $mastery = $this->masteryFromAttempts($usable);
                $fluencies = $usable->filter(fn ($a) => $a['items'] >= (int) config('pal_gamification.fluency.min_items_for_fluency', 3))
                    ->pluck('net_fluency');

                $first = $usable->first();
                $last = $usable->last();

                $existing = $records[$ref] ?? null;

                $records[$ref] = [
                    'concept_ref' => (string) $ref,
                    'concept_label' => $existing['concept_label'] ?? $last['concept_label'],
                    'subject_id' => $existing['subject_id'] ?? $last['subject_id'],
                    'subject_name' => $existing['subject_name'] ?? $last['subject_name'],
                    // An authored mastery score always wins over an estimate.
                    'mastery' => $existing['mastery'] ?? $mastery,
                    'mastery_source' => $existing['mastery_source'] ?? 'attempt_history',
                    'tier' => $this->tierFor($existing['mastery'] ?? $mastery),
                    'sessions' => $usable->count(),
                    'items' => (int) $usable->sum('items'),
                    'best_net_fluency' => $fluencies->isNotEmpty() ? round((float) $fluencies->max(), 4) : null,
                    'latest_net_fluency' => $fluencies->isNotEmpty() ? round((float) $fluencies->last(), 4) : null,
                    'first_seen_at' => $first['occurred_at'] ?? null,
                    'last_seen_at' => $last['occurred_at'] ?? null,
                    'bloom_level' => $existing['bloom_level'] ?? null,
                    'trend' => $existing['trend'] ?? null,
                ];
            }

            return $records;
        });
    }

    /**
     * Exponentially weighted mean of attempt accuracy — the estimate used when
     * no authored BKT score exists. Recent evidence dominates, which is the
     * behaviour a mastery number needs: a learner who has improved should not
     * be held down by how they started.
     */
    private function masteryFromAttempts(Collection $attempts): float
    {
        if ($attempts->isEmpty()) {
            return 0.0;
        }

        $learnRate = 0.5;
        $mastery = (float) $attempts->first()['accuracy'];
        foreach ($attempts->slice(1) as $attempt) {
            $mastery += $learnRate * (((float) $attempt['accuracy']) - $mastery);
        }

        return round(max(0.0, min(1.0, $mastery)), 4);
    }

    /** @return array<int,array<string,mixed>> */
    private function authoredCompetencies(int $learnerId): array
    {
        if (! Schema::hasTable('pal_competencies')) {
            return [];
        }

        return DB::table('pal_competencies as c')
            ->leftJoin('pal_concepts as pc', 'pc.id', '=', 'c.concept_id')
            ->leftJoin('pal_subjects as ps', 'ps.id', '=', 'c.subject_id')
            ->where('c.learner_id', $learnerId)
            ->whereNotNull('c.concept_id')
            ->select('c.concept_id', 'c.subject_id', 'c.mastery_score', 'c.bloom_level',
                'c.proficiency_trend', 'c.last_assessed', 'c.created_at',
                'pc.name as concept_name', 'ps.name as subject_name')
            ->get()
            ->map(fn ($r) => [
                'concept_id' => (int) $r->concept_id,
                'concept_name' => (string) ($r->concept_name ?: ('Concept #' . $r->concept_id)),
                'subject_id' => $r->subject_id !== null ? (int) $r->subject_id : null,
                'subject_name' => (string) ($r->subject_name ?? ''),
                // pal_competencies stores 0..1; a 0..100 estate is normalised.
                'mastery' => $this->normaliseMastery((float) $r->mastery_score),
                'bloom_level' => (int) $r->bloom_level,
                'trend' => $r->proficiency_trend,
                'last_assessed' => $r->last_assessed ? Carbon::parse($r->last_assessed) : null,
                'created_at' => $r->created_at ? Carbon::parse($r->created_at) : null,
            ])
            ->all();
    }

    private function normaliseMastery(float $value): float
    {
        return round(max(0.0, min(1.0, $value > 1 ? $value / 100 : $value)), 4);
    }

    /** Stream / Mountain / Sky for a 0..1 mastery value (§1, config-driven). */
    public function tierFor(float $mastery): string
    {
        $tiers = (array) config('pal_gamification.mastery_tiers', []);
        $best = 'stream';
        foreach ($tiers as $key => $tier) {
            if ($mastery >= (float) ($tier['min_mastery'] ?? 0)) {
                if (($tiers[$best]['min_mastery'] ?? 0) <= (float) ($tier['min_mastery'] ?? 0)) {
                    $best = (string) $key;
                }
            }
        }

        return $best;
    }

    // =====================================================================
    // Day-level engagement — what the streak system measures
    // =====================================================================

    /**
     * Productive engagement per calendar day (§7.1).
     *
     * Every source that can evidence real work is folded in, and each day
     * carries the minutes and the qualifying-activity counts that produced it,
     * so the streak rules can be applied transparently — and so a day that had
     * activity but did NOT qualify can still be explained rather than silently
     * dropped.
     *
     * @return array<string,array<string,mixed>> keyed by Y-m-d
     */
    public function dailyActivity(int $learnerId): array
    {
        return $this->once("daily:{$learnerId}", function () use ($learnerId) {
            $days = [];

            $touch = function (?Carbon $at) use (&$days): ?string {
                if ($at === null) {
                    return null;
                }
                $key = $at->toDateString();
                $days[$key] ??= [
                    'date' => $key,
                    'productive_minutes' => 0.0,
                    'sources' => [],
                    'activities' => [
                        'learning_cell' => 0,
                        'spaced_review' => 0,
                        'peer_teaching' => 0,
                        'team_challenge' => 0,
                    ],
                    'concepts' => [],
                ];

                return $key;
            };

            // --- PAL practice attempts -------------------------------------
            foreach ($this->attempts($learnerId) as $attempt) {
                $key = $touch($attempt['occurred_at']);
                if ($key === null) {
                    continue;
                }
                $days[$key]['productive_minutes'] += $attempt['duration_seconds'] / 60;
                $days[$key]['activities']['learning_cell'] += 1;
                $days[$key]['activities']['spaced_review'] += $attempt['items'];
                $days[$key]['sources']['pal_attempts'] = ($days[$key]['sources']['pal_attempts'] ?? 0) + 1;
                $days[$key]['concepts'][$attempt['concept_ref']] = true;
            }

            // --- PAL V4 learning sessions ----------------------------------
            if (Schema::hasTable('pal_learning_sessions')) {
                $sessions = DB::table('pal_learning_sessions')
                    ->where('learner_id', $learnerId)
                    ->select('duration_minutes', 'interaction_count', 'created_at', 'status')
                    ->limit(5000)
                    ->get();
                foreach ($sessions as $session) {
                    $key = $touch($session->created_at ? Carbon::parse($session->created_at) : null);
                    if ($key === null) {
                        continue;
                    }
                    $days[$key]['productive_minutes'] += (float) $session->duration_minutes;
                    if ((int) $session->interaction_count > 0) {
                        $days[$key]['activities']['learning_cell'] += 1;
                    }
                    $days[$key]['sources']['pal_learning_sessions'] = ($days[$key]['sources']['pal_learning_sessions'] ?? 0) + 1;
                }
            }

            // --- xAPI telemetry --------------------------------------------
            if (Schema::hasTable('pal_telemetry_events')) {
                $events = DB::table('pal_telemetry_events')
                    ->where('actor_id', $learnerId)
                    ->select('duration_seconds', 'timestamp', 'verb')
                    ->limit(20000)
                    ->get();
                foreach ($events as $event) {
                    $key = $touch($event->timestamp ? Carbon::parse($event->timestamp) : null);
                    if ($key === null) {
                        continue;
                    }
                    $days[$key]['productive_minutes'] += ((int) $event->duration_seconds) / 60;
                    $days[$key]['sources']['pal_telemetry_events'] = ($days[$key]['sources']['pal_telemetry_events'] ?? 0) + 1;
                }
            }

            // --- Peer teaching / collaboration ------------------------------
            foreach ($this->collaborationActivities($learnerId) as $activity) {
                $key = $touch($activity['at']);
                if ($key === null) {
                    continue;
                }
                if ($activity['is_peer_teaching']) {
                    $days[$key]['activities']['peer_teaching'] += 1;
                }
                $days[$key]['sources']['pal_collaboration_activities'] = ($days[$key]['sources']['pal_collaboration_activities'] ?? 0) + 1;
            }

            // --- Team challenge contributions -------------------------------
            if (Schema::hasTable('pal_team_challenge_contributions')) {
                $contributions = DB::table('pal_team_challenge_contributions')
                    ->where('learner_id', $learnerId)
                    ->whereNotNull('first_contributed_at')
                    ->select('first_contributed_at')
                    ->limit(2000)
                    ->get();
                foreach ($contributions as $contribution) {
                    $key = $touch(Carbon::parse($contribution->first_contributed_at));
                    if ($key === null) {
                        continue;
                    }
                    $days[$key]['activities']['team_challenge'] += 1;
                    $days[$key]['sources']['team_challenges'] = ($days[$key]['sources']['team_challenges'] ?? 0) + 1;
                }
            }

            foreach ($days as $key => $day) {
                $days[$key]['productive_minutes'] = round($day['productive_minutes'], 2);
                $days[$key]['concept_count'] = count($day['concepts']);
                unset($days[$key]['concepts']);
            }

            ksort($days);

            return $days;
        });
    }

    // =====================================================================
    // Framework evidence — CASEL / NCDG / RIASEC / finance
    // =====================================================================

    /** @return array<int,array<string,mixed>> */
    public function frameworkProgress(int $learnerId): array
    {
        return $this->once("framework:{$learnerId}", function () use ($learnerId) {
            if (! Schema::hasTable('pal_framework_progress')) {
                return [];
            }

            return DB::table('pal_framework_progress')
                ->where('learner_id', $learnerId)
                ->select('framework_type', 'framework_tag', 'progress_score', 'evidence_count', 'status', 'last_evidenced_at')
                ->get()
                ->map(fn ($r) => [
                    'framework_type' => (string) $r->framework_type,
                    'framework_tag' => (string) $r->framework_tag,
                    'progress_score' => (float) $r->progress_score,
                    'evidence_count' => (int) $r->evidence_count,
                    'status' => (string) $r->status,
                    'last_evidenced_at' => $r->last_evidenced_at ? Carbon::parse($r->last_evidenced_at) : null,
                ])
                ->all();
        });
    }

    /**
     * RIASEC evidence counts, from every place this estate records a signal:
     * pal_framework_progress rows, learning evidence, learning events, and the
     * ULUs the learner has actually engaged with.
     *
     * @return array{counts:array<string,int>,total:int,sources:array<string,int>}
     */
    public function riasecSignals(int $learnerId): array
    {
        return $this->once("riasec:{$learnerId}", function () use ($learnerId) {
            $counts = array_fill_keys((array) config('pal_v4.riasec', []), 0);
            $sources = [];

            foreach ($this->frameworkProgress($learnerId) as $row) {
                if (strtolower($row['framework_type']) !== 'riasec') {
                    continue;
                }
                $tag = strtoupper($row['framework_tag']);
                if (array_key_exists($tag, $counts)) {
                    $counts[$tag] += max(1, $row['evidence_count']);
                    $sources['pal_framework_progress'] = ($sources['pal_framework_progress'] ?? 0) + 1;
                }
            }

            if (Schema::hasTable('pal_learning_events') && Schema::hasColumn('pal_learning_events', 'riasec_signal')) {
                $rows = DB::table('pal_learning_events')
                    ->where('learner_id', $learnerId)
                    ->whereNotNull('riasec_signal')
                    ->selectRaw('riasec_signal, COUNT(*) AS n')
                    ->groupBy('riasec_signal')
                    ->get();
                foreach ($rows as $row) {
                    $tag = strtoupper((string) $row->riasec_signal);
                    if (array_key_exists($tag, $counts)) {
                        $counts[$tag] += (int) $row->n;
                        $sources['pal_learning_events'] = ($sources['pal_learning_events'] ?? 0) + (int) $row->n;
                    }
                }
            }

            foreach ($this->engagedUlus($learnerId) as $ulu) {
                $tag = strtoupper((string) ($ulu['riasec_signal'] ?? ''));
                if ($tag !== '' && array_key_exists($tag, $counts)) {
                    $counts[$tag] += 1;
                    $sources['pal_unified_learning_units'] = ($sources['pal_unified_learning_units'] ?? 0) + 1;
                }
            }

            return [
                'counts' => $counts,
                'total' => array_sum($counts),
                'sources' => $sources,
            ];
        });
    }

    /**
     * The ULUs this learner has genuinely engaged with, matched through the
     * concepts they have practised. A ULU nobody has touched contributes no
     * career signal — which is why the career quest of a new learner is empty
     * rather than pre-filled from the ULU catalogue.
     *
     * @return array<int,array<string,mixed>>
     */
    public function engagedUlus(int $learnerId): array
    {
        return $this->once("ulus:{$learnerId}", function () use ($learnerId) {
            if (! Schema::hasTable('pal_unified_learning_units')) {
                return [];
            }

            $labels = collect($this->conceptRecords($learnerId))
                ->pluck('concept_label')
                ->filter()
                ->map(fn ($v) => trim((string) $v))
                ->unique()
                ->values();

            if ($labels->isEmpty()) {
                return [];
            }

            $query = DB::table('pal_unified_learning_units')
                ->where('status', 'published')
                ->select('id', 'ulu_id', 'title', 'subject', 'academic_concept', 'sub_concept',
                    'casel_domain', 'ncdg_goal', 'riasec_signal', 'career_cluster',
                    'real_skill_name', 'career_layer', 'grade');

            $query->where(function ($outer) use ($labels) {
                foreach ($labels->take(40) as $label) {
                    $outer->orWhere('academic_concept', 'like', '%' . $label . '%')
                        ->orWhere('title', 'like', '%' . $label . '%');
                }
            });

            return $query->limit(200)->get()->map(fn ($r) => [
                'id' => (int) $r->id,
                'ulu_id' => (string) $r->ulu_id,
                'title' => (string) $r->title,
                'subject' => (string) $r->subject,
                'academic_concept' => (string) $r->academic_concept,
                'casel_domain' => $r->casel_domain,
                'ncdg_goal' => $r->ncdg_goal,
                'riasec_signal' => $r->riasec_signal,
                'career_cluster' => $r->career_cluster,
                'real_skill_name' => $r->real_skill_name,
                'career_layer' => $r->career_layer ? json_decode((string) $r->career_layer, true) : null,
                'grade' => (int) $r->grade,
            ])->all();
        });
    }

    // =====================================================================
    // Discrete behavioural evidence used by the badge rules
    // =====================================================================

    /**
     * Misconceptions the learner recovered from on the same day they hit them
     * (§3 "Bounced back"). Same-session is approximated by same-day where the
     * estate records no session id on the misconception row.
     */
    public function misconceptionRecoveries(int $learnerId): int
    {
        return $this->once("misconRecover:{$learnerId}", function () use ($learnerId) {
            if (! Schema::hasTable('pal_learner_misconceptions')) {
                return 0;
            }

            return (int) DB::table('pal_learner_misconceptions')
                ->where('learner_id', $learnerId)
                ->where('status', 'resolved')
                ->whereRaw('DATE(created_at) = DATE(updated_at)')
                ->count();
        });
    }

    /** @return array<int,array<string,mixed>> */
    public function collaborationActivities(int $learnerId): array
    {
        return $this->once("collab:{$learnerId}", function () use ($learnerId) {
            if (! Schema::hasTable('pal_collaboration_activities')) {
                return [];
            }

            return DB::table('pal_collaboration_activities')
                ->where('learner_id', $learnerId)
                ->select('activity_type', 'peer_count', 'engagement_score', 'created_at')
                ->limit(2000)
                ->get()
                ->map(function ($r) {
                    $type = strtolower((string) $r->activity_type);

                    return [
                        'activity_type' => $type,
                        'peer_count' => (int) $r->peer_count,
                        'engagement_score' => (float) $r->engagement_score,
                        'at' => $r->created_at ? Carbon::parse($r->created_at) : null,
                        // §10.3 — peer teaching only counts when the estate
                        // recorded an assessed explanation, not mere completion.
                        'is_peer_teaching' => str_contains($type, 'peer_teach')
                            || str_contains($type, 'peer teaching')
                            || str_contains($type, 'explain'),
                    ];
                })
                ->all();
        });
    }

    public function peerTeachingSessions(int $learnerId): int
    {
        $requiresAssessment = (bool) config('pal_gamification.safeguards.peer_teaching_requires_assessment', true);

        return collect($this->collaborationActivities($learnerId))
            ->filter(fn ($a) => $a['is_peer_teaching'])
            ->filter(fn ($a) => ! $requiresAssessment || $a['engagement_score'] > 0)
            ->count();
    }

    /**
     * Content the learner opened that the system did not assign (§3 "Explorer").
     * A recommendation row that was viewed without ever having been recommended
     * is exactly that signal.
     */
    public function selfDirectedOpens(int $learnerId): int
    {
        return $this->once("selfDirected:{$learnerId}", function () use ($learnerId) {
            $count = 0;

            if (Schema::hasTable('pal_content_recommendations')) {
                $recommended = DB::table('pal_content_recommendations')
                    ->where('learner_id', $learnerId)
                    ->where('event_type', 'recommended')
                    ->pluck('content_id')
                    ->unique();

                $count += DB::table('pal_content_recommendations')
                    ->where('learner_id', $learnerId)
                    ->whereIn('event_type', ['viewed', 'completed'])
                    ->when($recommended->isNotEmpty(), fn ($q) => $q->whereNotIn('content_id', $recommended->all()))
                    ->distinct()
                    ->count('content_id');
            }

            if (Schema::hasTable('pal_learning_events') && Schema::hasColumn('pal_learning_events', 'source')) {
                $count += DB::table('pal_learning_events')
                    ->where('learner_id', $learnerId)
                    ->whereIn('source', ['self_directed', 'learner', 'explore'])
                    ->count();
            }

            return $count;
        });
    }

    /** Completed content carrying a cross-curricular link (§3 "Cross connector"). */
    public function crossCurricularCompletions(int $learnerId): array
    {
        return $this->once("crossCurricular:{$learnerId}", function () use ($learnerId) {
            if (! Schema::hasTable('pal_content_recommendations') || ! Schema::hasTable('pal_contents')) {
                return [];
            }
            if (! Schema::hasColumn('pal_contents', 'cross_curricular_links')) {
                return [];
            }

            return DB::table('pal_content_recommendations as r')
                ->join('pal_contents as c', 'c.id', '=', 'r.content_id')
                ->join('pal_concepts as pc', 'pc.id', '=', 'c.concept_id')
                ->where('r.learner_id', $learnerId)
                ->where('r.event_type', 'completed')
                ->whereNotNull('c.cross_curricular_links')
                ->where('c.cross_curricular_links', '!=', '[]')
                ->select('c.id', 'c.title', 'c.cross_curricular_links', 'pc.name as concept_name')
                ->limit(200)
                ->get()
                ->map(fn ($r) => [
                    'content_id' => (int) $r->id,
                    'title' => (string) $r->title,
                    'concept_name' => (string) ($r->concept_name ?? ''),
                    'links' => json_decode((string) $r->cross_curricular_links, true) ?: [],
                ])
                ->all();
        });
    }

    /**
     * The longest stretch spent on a single concept inside one day, in minutes
     * (§3 "Deep diver"). Derived from real attempt durations plus any recorded
     * session minutes.
     */
    public function longestSingleConceptMinutes(int $learnerId): float
    {
        return $this->once("deepdive:{$learnerId}", function () use ($learnerId) {
            $byDayConcept = [];
            foreach ($this->attempts($learnerId) as $attempt) {
                if ($attempt['occurred_at'] === null) {
                    continue;
                }
                $key = $attempt['occurred_at']->toDateString() . '|' . $attempt['concept_ref'];
                $byDayConcept[$key] = ($byDayConcept[$key] ?? 0) + $attempt['duration_seconds'] / 60;
            }

            return $byDayConcept === [] ? 0.0 : round(max($byDayConcept), 2);
        });
    }

    /**
     * Did the learner ever get the first items of a set wrong and keep going
     * (§3 "Keeps going")? Answered from the real per-item answer stream, in
     * submission order within one attempt.
     */
    public function persistedAfterEarlyErrors(int $learnerId, int $leadingWrong, int $thenItems): bool
    {
        $key = "persist:{$learnerId}:{$leadingWrong}:{$thenItems}";

        return $this->once($key, function () use ($learnerId, $leadingWrong, $thenItems) {
            if (! Schema::hasTable('lms_online_exam_answer')) {
                return false;
            }

            $attemptIds = $this->attempts($learnerId)->pluck('attempt_id')->take(200)->all();
            if ($attemptIds === []) {
                return false;
            }

            $rows = DB::table('lms_online_exam_answer')
                ->whereIn('online_exam_id', $attemptIds)
                ->orderBy('online_exam_id')
                ->orderBy('id')
                ->select('online_exam_id', 'ans_status')
                ->limit(20000)
                ->get()
                ->groupBy('online_exam_id');

            foreach ($rows as $answers) {
                $ordered = $answers->values();
                if ($ordered->count() < $leadingWrong + $thenItems) {
                    continue;
                }
                $leading = $ordered->take($leadingWrong);
                $allWrong = $leading->every(fn ($a) => ! $this->isCorrectAnswer($a->ans_status));
                if ($allWrong && $ordered->count() - $leadingWrong >= $thenItems) {
                    return true;
                }
            }

            return false;
        });
    }

    private function isCorrectAnswer($status): bool
    {
        $value = strtolower(trim((string) $status));

        return in_array($value, ['1', 'right', 'correct', 'true', 'yes'], true);
    }

    /**
     * Career scenarios completed, evidenced by engagement with ULUs that carry
     * a career layer (§3 "Career explorer", §5.3 point 2).
     *
     * @return array<int,array<string,mixed>>
     */
    public function careerScenarioCompletions(int $learnerId): array
    {
        return $this->once("careerScenarios:{$learnerId}", function () use ($learnerId) {
            return collect($this->engagedUlus($learnerId))
                ->filter(fn ($u) => ! empty($u['career_cluster']) || ! empty($u['career_layer']))
                ->values()
                ->all();
        });
    }

    /** AI-tutor questions this estate recorded as unresolved (§3 "Good questioner"). */
    public function unresolvedTutorQuestions(int $learnerId): int
    {
        return $this->once("tutorQ:{$learnerId}", function () use ($learnerId) {
            if (! Schema::hasTable('pal_learning_events')) {
                return 0;
            }

            return (int) DB::table('pal_learning_events')
                ->where('learner_id', $learnerId)
                ->whereIn('event_type', ['tutor_question_unresolved', 'unanswered_question'])
                ->count();
        });
    }

    // =====================================================================
    // Small shared helpers
    // =====================================================================

    /** @return array<int,string> subject id => display name */
    public function subjectNames(array $subjectIds): array
    {
        $subjectIds = array_values(array_unique(array_filter(array_map('intval', $subjectIds))));
        if ($subjectIds === []) {
            return [];
        }

        return $this->once('subjects:' . md5(implode(',', $subjectIds)), function () use ($subjectIds) {
            $names = DB::table('sub_std_map')
                ->whereIn('subject_id', $subjectIds)
                ->whereNotNull('display_name')
                ->pluck('display_name', 'subject_id')
                ->all();

            $missing = array_values(array_diff($subjectIds, array_keys($names)));
            if ($missing !== []) {
                $names += DB::table('subject')->whereIn('id', $missing)->pluck('name', 'id')->all();
            }

            return array_map(fn ($v) => (string) $v, $names);
        });
    }

    /**
     * "Class 7" / "VII" / "7th" → 7. Returns null when the label carries no
     * grade the system can be confident about, so nothing downstream guesses.
     */
    public function parseGradeNumber(string ...$labels): ?int
    {
        $roman = [
            'XII' => 12, 'XI' => 11, 'IX' => 9, 'VIII' => 8, 'VII' => 7, 'VI' => 6,
            'IV' => 4, 'V' => 5, 'III' => 3, 'II' => 2, 'X' => 10, 'I' => 1,
        ];

        foreach ($labels as $label) {
            $label = trim($label);
            if ($label === '') {
                continue;
            }

            if (preg_match('/\b(\d{1,2})\b/', $label, $m)) {
                $grade = (int) $m[1];
                if ($grade >= 1 && $grade <= 12) {
                    return $grade;
                }
            }

            $upper = strtoupper(preg_replace('/[^A-Z]/i', '', $label));
            foreach ($roman as $numeral => $grade) {
                if ($upper === $numeral || str_ends_with($upper, $numeral)) {
                    return $grade;
                }
            }
        }

        return null;
    }

    /** @param callable():mixed $resolver */
    private function once(string $key, callable $resolver)
    {
        if (! array_key_exists($key, $this->memo)) {
            $this->memo[$key] = $resolver();
        }

        return $this->memo[$key];
    }
}
