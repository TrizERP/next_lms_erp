<?php

namespace App\Services\PAL\Adaptive;

use App\Models\PAL\DiagnosticAttempt;
use App\Services\PAL\Diagnostic\DiagnosticScorer;
use App\Services\PAL\Questions\DifficultyBands;
use App\Services\PAL\Questions\McqPool;
use Illuminate\Support\Facades\DB;

/**
 * Assembles what the adaptive engine knows about a learner, per concept.
 *
 * Two jobs: build the concept list the Adaptive Learning screen renders, and
 * gather the signal bundle AdaptiveDifficultyRule reasons from.
 *
 * ---------------------------------------------------------------------------
 * EVERY COUNT HERE IS A GROUPED QUERY
 * ---------------------------------------------------------------------------
 * A chapter carries up to 37 concepts and the estate carries 2,571. Asking
 * "how many easy questions does this concept have" once per concept turns one
 * page load into dozens of round trips against a database that is remote in
 * every environment this runs in. So availability, diagnostic history and
 * practice history are each fetched for the whole chapter in one query and
 * then keyed in PHP.
 *
 * ---------------------------------------------------------------------------
 * WHY CONCEPTS WITH NOTHING STAY IN THE LIST
 * ---------------------------------------------------------------------------
 * A concept with no servable MCQs is returned with servable = false rather
 * than filtered out. Dropping it would silently remove half a syllabus from
 * the screen and leave the learner wondering where a topic went; showing it
 * greyed out says plainly that practice is not ready for it yet.
 */
class ConceptPerformanceAnalyzer
{
    /** Recent practice answers the streak rules look at. */
    private const RECENT_WINDOW = 5;

    public function __construct(private DiagnosticScorer $scorer = new DiagnosticScorer())
    {
    }

    /** The learner's most recent finished diagnostic for a chapter. */
    public function latestAttempt($studentId, int $chapterId): ?DiagnosticAttempt
    {
        return DiagnosticAttempt::forStudent($studentId)
            ->forChapter($chapterId)
            ->submitted()
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Everything the Adaptive Learning screen needs for one chapter.
     *
     * @return array{concepts: array, has_diagnostic: bool, level: ?string, attempt_id: ?int, availability: array}
     */
    public function forChapter($studentId, int $chapterId, $subInstituteId): array
    {
        $attempt = $this->latestAttempt($studentId, $chapterId);
        $level = $attempt?->level;

        $diagnosticByConcept = $this->diagnosticByConcept($attempt);
        $baseline = $attempt !== null
            ? $this->scorer->baselineDifficulty($level, $attempt->difficulty_breakdown ?: [])['difficulty']
            : null;

        $concepts = DB::table('lms_concept')
            ->where('chapter_id', $chapterId)
            ->whereIn('sub_institute_id', [$subInstituteId, 0])
            ->orderBy('id')
            ->get(['id', 'name', 'chapter_id']);

        $chapterAvailability = McqPool::availability([$chapterId], $subInstituteId)[$chapterId]
            ?? ['easy' => 0, 'medium' => 0, 'hard' => 0, 'untagged' => 0, 'total' => 0];

        $exact = $this->exactAvailability($concepts->pluck('id')->all(), $subInstituteId);
        $practice = $this->practiceByConcept($studentId, $concepts->pluck('id')->all());

        $rule = new AdaptiveDifficultyRule();
        $out = [];

        foreach ($concepts as $concept) {
            $conceptId = (int) $concept->id;

            // A concept only uses its own questions when it actually has any;
            // otherwise it inherits the chapter's, which is the normal case.
            $hasExact = ($exact[$conceptId]['total'] ?? 0) > 0;
            $availability = $hasExact ? $exact[$conceptId] : $chapterAvailability;

            $diag = $diagnosticByConcept[$conceptId] ?? null;
            $prac = $practice[$conceptId] ?? null;

            $diagnosticPct = $diag['percentage'] ?? null;
            $practicePct = $prac['percentage'] ?? 0.0;
            $attempts = (int) ($prac['attempts'] ?? 0);

            // Same priority formula LearningPlanService::gradeConcept() uses:
            // diagnostic is the strongest signal, an unprobed concept sits
            // mid-table rather than jumping the queue on a score it never
            // earned. Kept identical so the Adaptive screen and the Learning
            // Plan agree on which concepts are weak.
            $priority = match (true) {
                $diagnosticPct !== null => (float) $diagnosticPct,
                $attempts > 0 => (float) $practicePct,
                default => 50.0,
            };

            $decision = $rule->decide([
                'diagnostic_level' => $level,
                'baseline_difficulty' => $baseline,
                'concept_diagnostic_pct' => $diag['percentage'] ?? null,
                'concept_diagnostic_served' => (int) ($diag['served'] ?? 0),
                'last_served' => $prac['last_served'] ?? null,
                'recent' => $prac['recent'] ?? [],
                'practice_count' => (int) ($prac['attempts'] ?? 0),
                'available' => $this->bandCounts($availability),
            ]);

            $out[] = [
                'concept_id' => $conceptId,
                'name' => $concept->name,
                'chapter_id' => (int) $concept->chapter_id,
                'availability' => $availability + ['exact' => $hasExact],
                'diagnostic' => $diag,
                'practice' => [
                    'attempts' => (int) ($prac['attempts'] ?? 0),
                    'correct' => (int) ($prac['correct'] ?? 0),
                    'percentage' => $prac['percentage'] ?? 0.0,
                ],
                'next_difficulty' => $decision['difficulty'],
                'rule_fired' => $decision['rule_fired'],
                'rationale' => $decision['rationale'],
                'servable' => ($availability['total'] ?? 0) > 0,
                'priority' => $priority,
            ];
        }

        // Weakest first, same convention as LearningPlanService::forChapter():
        // unservable concepts sort last, since there's nothing the learner can
        // actually do on them yet.
        usort($out, function ($a, $b) {
            if ($a['servable'] !== $b['servable']) {
                return $a['servable'] ? -1 : 1;
            }

            return $a['priority'] <=> $b['priority'];
        });

        return [
            'concepts' => $out,
            'has_diagnostic' => $attempt !== null,
            'level' => $level,
            'attempt_id' => $attempt?->id,
            'availability' => $chapterAvailability + [
                'concepts_total' => count($out),
                'concepts_servable' => count(array_filter($out, fn ($c) => $c['servable'])),
            ],
        ];
    }

    /**
     * The signal bundle for ONE concept, used when serving questions.
     *
     * @return array<string,mixed>
     */
    public function signalsForConcept($studentId, int $conceptId, $subInstituteId, array $availability): array
    {
        $concept = DB::table('lms_concept')->where('id', $conceptId)->first(['id', 'chapter_id']);
        $attempt = $concept ? $this->latestAttempt($studentId, (int) $concept->chapter_id) : null;

        $diag = $this->diagnosticByConcept($attempt)[$conceptId] ?? null;
        $prac = $this->practiceByConcept($studentId, [$conceptId])[$conceptId] ?? null;

        return [
            'diagnostic_level' => $attempt?->level,
            'baseline_difficulty' => $attempt !== null
                ? $this->scorer->baselineDifficulty($attempt->level, $attempt->difficulty_breakdown ?: [])['difficulty']
                : null,
            'concept_diagnostic_pct' => $diag['percentage'] ?? null,
            'concept_diagnostic_served' => (int) ($diag['served'] ?? 0),
            'last_served' => $prac['last_served'] ?? null,
            'recent' => $prac['recent'] ?? [],
            'practice_count' => (int) ($prac['attempts'] ?? 0),
            'available' => $this->bandCounts($availability),
        ];
    }

    /** Strip the non-band keys so the rule only sees easy/medium/hard. */
    private function bandCounts(array $availability): array
    {
        $out = [];

        foreach (DifficultyBands::BANDS as $band) {
            $out[$band] = (int) ($availability[$band] ?? 0);
        }

        return $out;
    }

    /**
     * The per-concept rows of the attempt's stored concept_breakdown, keyed.
     *
     * Read off the attempt rather than recomputed: the breakdown was written
     * against the concept snapshot taken when the paper was drawn, and
     * re-deriving it now could land on different concepts if the curriculum
     * has been re-tagged since.
     *
     * @return array<int,array<string,mixed>>
     */
    private function diagnosticByConcept(?DiagnosticAttempt $attempt): array
    {
        if ($attempt === null) {
            return [];
        }

        $out = [];

        foreach ($attempt->concept_breakdown ?: [] as $row) {
            if (! empty($row['concept_id'])) {
                $out[(int) $row['concept_id']] = $row;
            }
        }

        return $out;
    }

    /**
     * Per-concept counts of questions carrying that concept_id directly.
     *
     * One grouped query for every concept asked about.
     *
     * @param  array<int,int>  $conceptIds
     * @return array<int,array<string,int>>
     */
    private function exactAvailability(array $conceptIds, $subInstituteId): array
    {
        $conceptIds = array_values(array_filter(array_map('intval', $conceptIds)));

        if ($conceptIds === []) {
            return [];
        }

        $ids = DifficultyBands::mappingValueIds();
        $easy = (int) $ids[DifficultyBands::EASY];
        $medium = (int) $ids[DifficultyBands::MEDIUM];
        $hard = (int) $ids[DifficultyBands::HARD];

        $inner = McqPool::base($subInstituteId)
            ->whereIn('q.concept_id', $conceptIds)
            ->leftJoin('lms_question_mapping as dok', function ($join) use ($ids) {
                $join->on('dok.questionmaster_id', '=', 'q.id')
                    ->where('dok.mapping_type_id', '=', DifficultyBands::DOK_PARENT_ID)
                    ->whereIn('dok.mapping_value_id', array_values($ids));
            })
            ->groupBy('q.id', 'q.concept_id', 'q.g_difficulty')
            ->select([
                'q.id',
                'q.concept_id',
                DB::raw("CASE
                    WHEN MAX(CASE WHEN dok.mapping_value_id = {$easy} THEN 1 END) = 1 THEN 'easy'
                    WHEN MAX(CASE WHEN dok.mapping_value_id = {$medium} THEN 1 END) = 1 THEN 'medium'
                    WHEN MAX(CASE WHEN dok.mapping_value_id = {$hard} THEN 1 END) = 1 THEN 'hard'
                    WHEN LOWER(q.g_difficulty) IN ('easy','medium','hard') THEN LOWER(q.g_difficulty)
                    ELSE NULL END as band"),
            ]);

        $rows = DB::query()->fromSub($inner, 'r')->groupBy('r.concept_id')->get([
            'r.concept_id',
            DB::raw("SUM(r.band = 'easy') as easy"),
            DB::raw("SUM(r.band = 'medium') as medium"),
            DB::raw("SUM(r.band = 'hard') as hard"),
            DB::raw('SUM(r.band IS NULL) as untagged'),
            DB::raw('COUNT(*) as total'),
        ]);

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->concept_id] = [
                'easy' => (int) $row->easy,
                'medium' => (int) $row->medium,
                'hard' => (int) $row->hard,
                'untagged' => (int) $row->untagged,
                'total' => (int) $row->total,
            ];
        }

        return $out;
    }

    /**
     * Practice history per concept: totals, the band last served, and the
     * recent run AT THAT BAND.
     *
     * The recent run is filtered to last_served on purpose. A streak has to
     * mean "three right at this difficulty" - counting across a band change
     * would let three easy answers push a learner up from hard.
     *
     * @param  array<int,int>  $conceptIds
     * @return array<int,array<string,mixed>>
     */
    private function practiceByConcept($studentId, array $conceptIds): array
    {
        $conceptIds = array_values(array_filter(array_map('intval', $conceptIds)));

        if ($conceptIds === []) {
            return [];
        }

        $rows = DB::table('pal_adaptive_response')
            ->where('student_id', (int) $studentId)
            ->whereIn('concept_id', $conceptIds)
            ->orderByDesc('id')
            ->get(['concept_id', 'is_correct', 'difficulty_served']);

        $out = [];

        foreach ($rows as $row) {
            $conceptId = (int) $row->concept_id;

            $out[$conceptId] ??= [
                'attempts' => 0, 'correct' => 0, 'percentage' => 0.0,
                'last_served' => $row->difficulty_served,   // rows are newest first
                'recent' => [],
            ];

            $out[$conceptId]['attempts']++;

            if ((int) $row->is_correct === 1) {
                $out[$conceptId]['correct']++;
            }

            if ($row->difficulty_served === $out[$conceptId]['last_served']
                && count($out[$conceptId]['recent']) < self::RECENT_WINDOW) {
                $out[$conceptId]['recent'][] = (int) $row->is_correct === 1;
            }
        }

        foreach ($out as $conceptId => $stats) {
            $out[$conceptId]['percentage'] = $stats['attempts'] > 0
                ? round($stats['correct'] / $stats['attempts'] * 100, 2)
                : 0.0;
        }

        return $out;
    }
}
