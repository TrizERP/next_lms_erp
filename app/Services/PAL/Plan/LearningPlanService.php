<?php

namespace App\Services\PAL\Plan;

use App\Models\PAL\MisconceptionLibrary;
use App\Services\PAL\Adaptive\ConceptPerformanceAnalyzer;
use App\Services\PAL\Diagnostic\DiagnosticScorer;
use App\Services\PAL\Questions\MasteryLadder;
use Illuminate\Support\Facades\DB;

/**
 * The learner's plan for one chapter: what to work on, in what order, and why.
 *
 * ---------------------------------------------------------------------------
 * THIS IS A PROJECTION, NOT A PLANNING ENGINE
 * ---------------------------------------------------------------------------
 * Every decision in here was already made somewhere else and is already
 * persisted:
 *
 *   - which concepts are weak            -> the diagnostic's concept_breakdown
 *   - what to practise next, and why     -> AdaptiveDifficultyRule (it already
 *                                           returns a human-readable rationale)
 *   - how far up the ladder they are     -> MasteryLadder over stored answers
 *   - what could go wrong on a concept   -> the curated misconception library
 *
 * So this class composes those answers into an ordered journey. It does not
 * re-decide any of them, and it deliberately owns no table: a stored plan would
 * be a second version of the truth that goes stale the moment the learner
 * answers one more question.
 *
 * ---------------------------------------------------------------------------
 * WHY READ-ONLY IS STRUCTURAL, NOT A UI RULE
 * ---------------------------------------------------------------------------
 * The brief says the plan must be read-only for the student. Hiding an edit
 * button would not achieve that. Because the plan is derived on every read and
 * has no row anywhere, there is nothing to edit and no write path to reach -
 * the route is registered GET-only and no method here mutates anything.
 *
 * The one call that CAN write (misconception detection) is deliberately not
 * made here; see the note on `misconceptions` below.
 */
class LearningPlanService
{
    /*
     * There is deliberately NO cap on how many concepts the plan speaks about.
     *
     * This used to slice the list to the first six, on the reasoning that a
     * plan listing everything is just the syllabus again. In practice it hid
     * work the learner had to do: concepts 7..n were dropped from `steps` and
     * `concepts` with no trace, so a chapter's outstanding concepts did not add
     * up to the counts in `summary` directly above them, and a learner who
     * finished the six visible concepts saw the plan silently grow new ones.
     *
     * The list is already narrowed by MEANING rather than by count - only
     * concepts that are servable and not yet mastered reach it - which is the
     * filter that makes it a plan. Concepts with no questions are reported
     * separately as `content_gaps`, and mastered ones are counted in `summary`.
     * Ordering still leads with the weakest, so "what to do next" is the first
     * row whether the list is three long or thirty.
     */

    public function __construct(
        private ConceptPerformanceAnalyzer $analyzer = new ConceptPerformanceAnalyzer(),
        private MasteryLadder $ladder = new MasteryLadder(),
        private DiagnosticScorer $scorer = new DiagnosticScorer(),
        private DiagnosticEsoBridge $bridge = new DiagnosticEsoBridge(),
    ) {
    }

    /**
     * Build the plan for one chapter.
     *
     * @return array{
     *     chapter_id: int, chapter_name: ?string, subject_id: ?int,
     *     has_diagnostic: bool, diagnostic_level: ?string, attempt_id: ?int,
     *     summary: array, steps: array, concepts: array,
     *     content_gaps: array, generated_at: string
     * }
     */
    public function forChapter($studentId, int $chapterId, $subInstituteId): array
    {
        $analysis = $this->analyzer->forChapter($studentId, $chapterId, $subInstituteId);
        $chapter = DB::table('chapter_master')->where('id', $chapterId)->first(['id', 'chapter_name', 'subject_id']);

        $concepts = $analysis['concepts'];

        // Practice history for the whole chapter in one query. Per-concept
        // lookups here would be dozens of round trips to a remote database.
        $practice = $this->practiceByConcept($studentId, array_column($concepts, 'concept_id'));

        $graded = [];
        foreach ($concepts as $concept) {
            $graded[] = $this->gradeConcept($concept, $practice[$concept['concept_id']] ?? []);
        }

        // Weakest first: the plan leads with what is actually blocking the
        // learner. Concepts with no questions sort last - they are reported as
        // content gaps, not as work the learner can do.
        usort($graded, function ($a, $b) {
            if ($a['servable'] !== $b['servable']) {
                return $a['servable'] ? -1 : 1;
            }

            return $a['priority'] <=> $b['priority'];
        });

        // Every concept the learner can actually act on and has not finished:
        // servable (the question bank can supply it) and not yet mastered.
        // Not truncated - see the note on the absent focus cap above.
        $focus = array_values(array_filter($graded, fn ($c) => $c['servable'] && ! $c['ladder']['mastered']));

        $misconceptions = $this->misconceptionsFor(array_column($focus, 'concept_id'), $chapterId, $subInstituteId);

        // Which concepts the engine can actually run. Carried per concept so
        // the UI can show Learn/Check on the ones that work and say "not ready"
        // on the rest, rather than rendering a button that leads nowhere.
        $readiness = $this->bridge->readiness($chapterId, $subInstituteId);
        $ready = array_flip($readiness['ready']);

        foreach ($focus as $i => $row) {
            $focus[$i]['misconceptions'] = $misconceptions[$row['concept_id']] ?? [];
            $focus[$i]['eso_ready'] = isset($ready[$row['concept_id']]);
        }

        return [
            'chapter_id' => $chapterId,
            'chapter_name' => $chapter->chapter_name ?? null,
            'subject_id' => isset($chapter->subject_id) ? (int) $chapter->subject_id : null,
            'has_diagnostic' => $analysis['has_diagnostic'],
            'diagnostic_level' => $analysis['level'],
            'attempt_id' => $analysis['attempt_id'],
            'summary' => $this->summarise($graded, $analysis),
            'steps' => $this->steps($focus, $analysis),
            'concepts' => $focus,
            'readiness' => $readiness,
            'content_gaps' => $this->contentGaps($graded),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Grade one concept against the ladder and give it a sort priority.
     *
     * @param  array<string,mixed>  $concept  a row from ConceptPerformanceAnalyzer::forChapter()
     * @param  array<string,mixed>  $practice
     * @return array<string,mixed>
     */
    private function gradeConcept(array $concept, array $practice): array
    {
        $availability = $concept['availability'] ?? [];
        $byDifficulty = $practice['by_difficulty'] ?? [];

        $ladder = $this->ladder->evaluate($availability, $byDifficulty);

        $diagnosticPct = $concept['diagnostic']['percentage'] ?? null;
        $practicePct = $concept['practice']['percentage'] ?? 0.0;
        $attempts = (int) ($concept['practice']['attempts'] ?? 0);

        // Priority orders the plan. The diagnostic is the strongest signal
        // because it is the only one gathered under uniform conditions; an
        // unprobed concept sits mid-table rather than jumping the queue on a
        // score it never earned.
        $priority = match (true) {
            $diagnosticPct !== null => (float) $diagnosticPct,
            $attempts > 0 => (float) $practicePct,
            default => 50.0,
        };

        return [
            'concept_id' => $concept['concept_id'],
            'name' => $concept['name'],
            'servable' => $concept['servable'],
            'concept_exact' => (bool) ($availability['exact'] ?? false),
            'availability' => $availability,
            'diagnostic' => $concept['diagnostic'],
            'practice' => $concept['practice'],
            'band' => $diagnosticPct !== null
                ? $this->scorer->band((float) $diagnosticPct)
                : null,
            'ladder' => $ladder,
            'next_difficulty' => $concept['next_difficulty'],
            'rule_fired' => $concept['rule_fired'],

            // The rule already explains itself in a sentence a learner can
            // read. Re-wording it here would let the plan and the practice
            // screen tell the learner two different stories.
            'rationale' => $concept['rationale'],
            'priority' => $priority,
            'misconceptions' => [],
        ];
    }

    /**
     * The journey, in the order the engine actually runs it:
     * weak concept -> misconception -> learn -> practice -> check -> mastery.
     *
     * @param  array<int,array<string,mixed>>  $focus
     * @param  array<string,mixed>  $analysis
     * @return array<int,array<string,mixed>>
     */
    private function steps(array $focus, array $analysis): array
    {
        if (! $analysis['has_diagnostic']) {
            return [[
                'key' => 'diagnostic',
                'title' => 'Take the chapter diagnostic',
                'detail' => 'Fifteen questions - five easy, five medium and five hard - to find where to start. Everything after this is built from the result.',
                'concept_id' => null,
            ]];
        }

        if ($focus === []) {
            return [[
                'key' => 'mastery',
                'title' => 'Every concept with practice questions is mastered',
                'detail' => 'There is nothing outstanding in this chapter. Recall reviews will appear as they fall due.',
                'concept_id' => null,
            ]];
        }

        $steps = [];

        foreach ($focus as $concept) {
            $misconception = $concept['misconceptions'][0] ?? null;

            $steps[] = [
                'key' => 'concept',
                'concept_id' => $concept['concept_id'],
                'title' => $concept['name'],
                'band' => $concept['band'],
                'detail' => $concept['rationale'],
                'next_difficulty' => $concept['next_difficulty'],
                'ladder' => $concept['ladder'],

                // Practice always works (it only needs questions). Learn and
                // Check run on the engine, so they are only offered where the
                // concept actually has nodes.
                'eso_ready' => (bool) ($concept['eso_ready'] ?? false),

                // The sub-journey for this one concept. Named stages rather
                // than prose so the UI can render them as a path and mark the
                // learner's position on it.
                'path' => array_values(array_filter([
                    $misconception !== null ? [
                        'stage' => 'misconception',
                        'label' => 'Clear up a common mix-up',
                        'detail' => $misconception['description'] ?? $misconception['error_pattern'] ?? null,
                    ] : null,
                    // Learn -> Practice -> Check, which is the order the engine
                    // actually runs (EsoPolicyService::phaseFor()). These were
                    // listed check-before-practice, so the plan promised a
                    // sequence the learner would then not be given.
                    ['stage' => 'learn', 'label' => 'Learn', 'detail' => 'Work through the material for this concept.'],
                    ['stage' => 'practice', 'label' => 'Practice', 'detail' => sprintf(
                        'Practise at %s.',
                        $concept['next_difficulty'] ?? 'easy'
                    )],
                    ['stage' => 'check', 'label' => 'Check', 'detail' => 'Answer a short check to prove it landed.'],
                    ['stage' => 'mastery', 'label' => 'Mastery', 'detail' => $concept['ladder']['reason']],
                ])),
            ];
        }

        return $steps;
    }

    /**
     * Known misconceptions for these concepts, keyed by concept id.
     *
     * ---------------------------------------------------------------------
     * WHY THIS READS THE LIBRARY RATHER THAN DETECTING
     * ---------------------------------------------------------------------
     * MisconceptionLibraryService::detectAndRoute() is the right call when a
     * learner gets something wrong - it matches the answer, increments
     * detection_count and routes a corrective. All of that WRITES.
     *
     * The plan is a GET. Calling a detector from a read would inflate
     * detection counts every time the learner opened the screen, which would
     * then feed back into prevalence_rate and teacher alerts. So this reads
     * what is on the shelf for the concept and leaves detection to the Check
     * loop, where an answer actually arrives.
     *
     * NOTE ON APPROVAL: the servable gate (config pal_content.servable_statuses
     * = ['approved']) is respected, not bypassed. On this estate only 6 of
     * 3,662 library rows are approved, so `available` will usually be empty
     * while `authored` is not - that gap is returned rather than hidden, so the
     * UI can say "known, awaiting review" instead of silently showing nothing.
     *
     * @param  array<int,int>  $conceptIds
     * @return array<int,array<int,array<string,mixed>>>
     */
    private function misconceptionsFor(array $conceptIds, int $chapterId, $subInstituteId): array
    {
        $conceptIds = array_values(array_filter(array_map('intval', $conceptIds)));

        if ($conceptIds === []) {
            return [];
        }

        $rows = MisconceptionLibrary::query()
            ->forTenant($subInstituteId !== null ? (int) $subInstituteId : null)
            ->servable()
            ->whereIn('concept_ref_id', $conceptIds)
            ->orderByDesc('priority_level')
            ->get(['id', 'tag', 'concept_ref_id', 'description', 'error_pattern', 'corrective_action', 'priority_level', 'quality_status']);

        $out = [];

        foreach ($rows as $row) {
            $out[(int) $row->concept_ref_id][] = [
                'id' => (int) $row->id,
                'tag' => $row->tag,
                'description' => $row->description,
                'error_pattern' => $row->error_pattern,
                'corrective_action' => $row->corrective_action,
                'priority_level' => (int) $row->priority_level,
            ];
        }

        return $out;
    }

    /**
     * Chapter-level counts. Derived from the graded list so they can never
     * disagree with the rows above them.
     *
     * @param  array<int,array<string,mixed>>  $graded
     * @param  array<string,mixed>  $analysis
     * @return array<string,mixed>
     */
    private function summarise(array $graded, array $analysis): array
    {
        $servable = array_values(array_filter($graded, fn ($c) => $c['servable']));

        $mastered = array_filter($servable, fn ($c) => $c['ladder']['mastered']);
        $weak = array_filter($servable, fn ($c) => ($c['band'] ?? null) === DiagnosticScorer::BAND_WEAK);
        $inProgress = array_filter($servable, fn ($c) => ! $c['ladder']['mastered'] && ($c['practice']['attempts'] ?? 0) > 0);

        return [
            'concepts_total' => count($graded),
            'concepts_servable' => count($servable),
            'concepts_without_questions' => count($graded) - count($servable),
            'mastered' => count($mastered),
            'in_progress' => count($inProgress),
            'weak' => count($weak),
            'diagnostic_level' => $analysis['level'],
        ];
    }

    /**
     * Concepts the learner cannot act on, and why.
     *
     * Returned rather than filtered away: a concept missing from the plan with
     * no explanation reads as a bug, and a teacher seeing this list is the only
     * route to getting the questions written.
     *
     * @param  array<int,array<string,mixed>>  $graded
     * @return array<int,array<string,mixed>>
     */
    private function contentGaps(array $graded): array
    {
        $gaps = [];

        foreach ($graded as $concept) {
            if (! $concept['servable']) {
                $gaps[] = [
                    'concept_id' => $concept['concept_id'],
                    'name' => $concept['name'],
                    'reason' => 'no_questions',
                    'detail' => 'No multiple-choice questions exist for this concept yet.',
                ];

                continue;
            }

            $missing = $concept['ladder']['bands_unavailable'] ?? [];

            if ($missing !== [] && $concept['ladder']['mastered']) {
                $gaps[] = [
                    'concept_id' => $concept['concept_id'],
                    'name' => $concept['name'],
                    'reason' => 'partial_ladder',
                    'detail' => sprintf(
                        'Mastered on the levels that exist. No %s question%s available.',
                        implode(' or ', $missing),
                        count($missing) === 1 ? '' : 's'
                    ),
                ];
            }
        }

        return $gaps;
    }

    /**
     * Per-band practice totals for many concepts in ONE query.
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
            ->groupBy('concept_id', 'difficulty_served')
            ->get([
                'concept_id',
                'difficulty_served',
                DB::raw('COUNT(*) as attempted'),
                DB::raw('SUM(is_correct) as correct'),
            ]);

        $out = [];

        foreach ($rows as $row) {
            $conceptId = (int) $row->concept_id;
            $attempted = (int) $row->attempted;
            $correct = (int) $row->correct;

            $out[$conceptId]['by_difficulty'][$row->difficulty_served] = [
                'attempted' => $attempted,
                'correct' => $correct,
                'accuracy' => $attempted > 0 ? round($correct / $attempted * 100, 2) : 0.0,
            ];
        }

        return $out;
    }
}
