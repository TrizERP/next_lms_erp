<?php

namespace App\Services\PAL\Diagnostic;

use App\Services\PAL\Questions\DifficultyBands;
use App\Services\PAL\Questions\McqPool;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Draws the 15-question diagnostic paper: 5 easy, 5 medium, 5 hard, MCQ only,
 * all from ONE chapter.
 *
 * ---------------------------------------------------------------------------
 * THE LADDER
 * ---------------------------------------------------------------------------
 * Each band is filled by walking stages until it has 5, and the walk NEVER
 * leaves the chapter - a diagnostic that quietly borrowed from a neighbouring
 * chapter would report mastery of material the learner was never asked about.
 *
 *   S1 chapter_dok        the Depth-of-Knowledge tag says this band
 *   S2 chapter_generated  no DoK tag at all, and g_difficulty says this band
 *   S3 adjacent:<band>    the band is genuinely short, so borrow from its
 *                         nearest neighbour inside the same chapter
 *   S4 untagged           nothing says anything; fills a remaining gap
 *
 * S2 is gated on excludeAnyDok() rather than simply "g_difficulty = band".
 * Where a question carries both signals they disagree about 47% of the time,
 * so g_difficulty is allowed to speak only where DoK is silent. Without that
 * gate the two sources would fight and difficulty_served would stop meaning
 * anything.
 *
 * Every question records which stage produced it in difficulty_source, and the
 * whole walk is written to selection_report. When a paper looks wrong, that
 * report is the answer - it is the only place the borrowing is visible.
 *
 * ---------------------------------------------------------------------------
 * WHY A SEED RATHER THAN inRandomOrder()
 * ---------------------------------------------------------------------------
 * The seed is generated once per attempt and stored. Re-running the selector
 * with it reproduces the paper exactly, which is what makes a bad attempt
 * debuggable after the fact. A fresh attempt gets a fresh seed, so a retake is
 * a different paper.
 */
class DiagnosticQuestionSelector
{
    /** Configuration loaded from config/pal_diagnostic.php with sensible defaults. */
    private int $perBand;
    private int $minViable;
    private int $recencyAttempts;

    public function __construct()
    {
        $this->perBand       = (int) Config::get('pal_diagnostic.paper.questions_per_band', 5);
        $this->minViable     = (int) Config::get('pal_diagnostic.paper.min_viable_questions', 9);
        $this->recencyAttempts = (int) Config::get('pal_diagnostic.recency_attempts', 2);
    }

    public function minViable(): int
    {
        return $this->minViable;
    }

    /**
     * @return array{questions: array<int,array<string,mixed>>, report: array<string,mixed>}
     */
    public function select(int $chapterId, $subInstituteId, $studentId, int $seed): array
    {
        $target = array_fill_keys(DifficultyBands::BANDS, $this->perBand);
        $recent = $this->recentQuestionIds($studentId, $chapterId);

        $taken = [];        // global across bands - an item borrowed into easy
        $picked = [];       // cannot reappear in medium
        $stages = array_fill_keys(DifficultyBands::BANDS, []);
        $relaxed = [];

        foreach (DifficultyBands::BANDS as $band) {
            $need = $target[$band];

            foreach ($this->stagesFor($band) as $stageKey => $apply) {
                if ($need <= 0) {
                    break;
                }

                $rows = $this->draw($chapterId, $subInstituteId, $seed, $apply, $taken, $recent, $need);
                $stages[$band][] = ['stage' => $stageKey, 'took' => count($rows)];

                foreach ($rows as $row) {
                    $taken[] = (int) $row->id;
                    $picked[] = [
                        'question_id' => (int) $row->id,
                        'chapter_id' => (int) $row->chapter_id,
                        'raw_concept_id' => $row->concept_id !== null ? (int) $row->concept_id : null,
                        'difficulty' => $band,
                        'difficulty_source' => $this->sourceFor($stageKey),
                    ];
                    $need--;
                }
            }

            // Still short only because we refused to repeat recent questions?
            // A shorter paper is a worse outcome than a repeated question, so
            // drop the recency filter for this band and say so in the report.
            if ($need > 0 && $recent !== []) {
                $before = $need;

                foreach ($this->stagesFor($band) as $stageKey => $apply) {
                    if ($need <= 0) {
                        break;
                    }

                    $rows = $this->draw($chapterId, $subInstituteId, $seed, $apply, $taken, [], $need);

                    foreach ($rows as $row) {
                        $taken[] = (int) $row->id;
                        $picked[] = [
                            'question_id' => (int) $row->id,
                            'chapter_id' => (int) $row->chapter_id,
                            'raw_concept_id' => $row->concept_id !== null ? (int) $row->concept_id : null,
                            'difficulty' => $band,
                            'difficulty_source' => $this->sourceFor($stageKey),
                        ];
                        $need--;
                    }
                }

                if ($need < $before) {
                    $relaxed[] = $band;
                    $stages[$band][] = ['stage' => 'recency_relaxed', 'took' => $before - $need];
                }
            }
        }

        $picked = $this->attachConcepts($picked, $subInstituteId);

        // Interleave the bands rather than serving five easy then five hard:
        // a solid block of hard items reads as a wall and depresses effort on
        // everything after it.
        $picked = $this->interleave($picked);

        foreach ($picked as $i => $row) {
            $picked[$i]['sequence'] = $i + 1;
        }

        return [
            'questions' => $picked,
            'report' => $this->report($chapterId, $subInstituteId, $seed, $target, $picked, $stages, $recent, $relaxed),
        ];
    }

    /**
     * The ladder for one band, in order. Each entry mutates a fresh query.
     *
     * @return array<string,callable>
     */
    private function stagesFor(string $band): array
    {
        $stages = [
            'chapter_dok' => fn ($q) => DifficultyBands::constrainByDok($q, 'q.id', $band),
            'chapter_generated' => function ($q) use ($band) {
                DifficultyBands::constrainByGenerated($q, 'q', $band);

                return DifficultyBands::excludeAnyDok($q, 'q.id');
            },
        ];

        // Nearest neighbour first. A borrowed item still counts in the slot it
        // filled, so the buckets sum to total_questions; difficulty_source is
        // what records that the bank never proved the difficulty.
        foreach (DifficultyBands::adjacent($band) as $neighbour) {
            $stages['adjacent:' . $neighbour] = function ($q) use ($neighbour) {
                return $q->where(function ($w) use ($neighbour) {
                    DifficultyBands::constrainByDok($w, 'q.id', $neighbour);
                    $w->orWhereRaw('LOWER(q.g_difficulty) = ?', [$neighbour]);
                });
            };
        }

        $stages['untagged'] = fn ($q) => DifficultyBands::constrainUntagged($q, 'q', 'q.id');

        return $stages;
    }

    /** difficulty_source value for a ladder stage. */
    private function sourceFor(string $stageKey): string
    {
        return match (true) {
            $stageKey === 'chapter_dok' => 'dok',
            $stageKey === 'chapter_generated' => 'g_difficulty',
            $stageKey === 'untagged' => 'untagged',
            default => $stageKey, // 'adjacent:medium' etc, already the right token
        };
    }

    /**
     * @param  array<int,int>  $taken
     * @param  array<int,int>  $recent
     */
    private function draw(int $chapterId, $subInstituteId, int $seed, callable $apply, array $taken, array $recent, int $limit)
    {
        if ($limit <= 0) {
            return collect();
        }

        $query = McqPool::base($subInstituteId)->where('q.chapter_id', $chapterId);

        $apply($query);

        if ($taken !== []) {
            $query->whereNotIn('q.id', $taken);
        }

        if ($recent !== []) {
            $query->whereNotIn('q.id', $recent);
        }

        return McqPool::deterministic($query, $seed)
            ->limit($limit)
            ->get(['q.id', 'q.chapter_id', 'q.concept_id']);
    }

    /**
     * Questions this learner saw in their last few submitted attempts here.
     *
     * A soft filter: re-measuring someone on the questions they just answered
     * measures recall of that sitting, not of the chapter.
     *
     * @return array<int,int>
     */
    private function recentQuestionIds($studentId, int $chapterId): array
    {
        $attemptIds = DB::table('pal_diagnostic_attempt')
            ->where('student_id', (int) $studentId)
            ->where('chapter_id', $chapterId)
            ->where('status', 'submitted')
            ->orderByDesc('id')
            ->limit($this->recencyAttempts)
            ->pluck('id')
            ->all();

        if ($attemptIds === []) {
            return [];
        }

        return DB::table('pal_diagnostic_response')
            ->whereIn('attempt_id', $attemptIds)
            ->pluck('question_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Resolve each question to a concept, in ONE query for the whole paper.
     *
     * lms_question_master.concept_id is populated on roughly 2k of the 28k
     * servable MCQs on this estate, so the chapter path is the normal case.
     * concept_exact records which one was used, so the result screen can say
     * "via chapter" rather than overstating the precision of the breakdown.
     *
     * @param  array<int,array<string,mixed>>  $picked
     * @return array<int,array<string,mixed>>
     */
    private function attachConcepts(array $picked, $subInstituteId): array
    {
        if ($picked === []) {
            return [];
        }

        $chapterIds = array_values(array_unique(array_column($picked, 'chapter_id')));
        $conceptIds = array_values(array_filter(array_column($picked, 'raw_concept_id')));

        $byChapter = [];
        $byId = [];

        foreach (DB::table('lms_concept')->whereIn('chapter_id', $chapterIds)->orderBy('id')->get(['id', 'chapter_id']) as $row) {
            $byId[(int) $row->id] = (int) $row->id;
            $byChapter[(int) $row->chapter_id] ??= (int) $row->id;
        }

        // A question can carry a concept_id whose chapter is not in this
        // paper; still a valid exact match, so look those up too.
        if ($conceptIds !== []) {
            $missing = array_diff($conceptIds, array_keys($byId));

            if ($missing !== []) {
                foreach (DB::table('lms_concept')->whereIn('id', $missing)->get(['id']) as $row) {
                    $byId[(int) $row->id] = (int) $row->id;
                }
            }
        }

        foreach ($picked as $i => $row) {
            $exact = $row['raw_concept_id'] !== null && isset($byId[$row['raw_concept_id']]);

            $picked[$i]['concept_id_snapshot'] = $exact
                ? $row['raw_concept_id']
                : ($byChapter[$row['chapter_id']] ?? null);
            $picked[$i]['chapter_id_snapshot'] = $row['chapter_id'];
            $picked[$i]['concept_exact'] = $exact;

            unset($picked[$i]['raw_concept_id']);
        }

        return $picked;
    }

    /**
     * Round-robin the bands so difficulty is spread through the paper.
     *
     * @param  array<int,array<string,mixed>>  $picked
     * @return array<int,array<string,mixed>>
     */
    private function interleave(array $picked): array
    {
        $buckets = array_fill_keys(DifficultyBands::BANDS, []);

        foreach ($picked as $row) {
            $buckets[$row['difficulty']][] = $row;
        }

        $out = [];
        while (array_filter($buckets)) {
            foreach (DifficultyBands::BANDS as $band) {
                if ($buckets[$band] !== []) {
                    $out[] = array_shift($buckets[$band]);
                }
            }
        }

        return $out;
    }

    /**
     * @param  array<string,int>  $target
     * @param  array<int,array<string,mixed>>  $picked
     * @param  array<string,array<int,array<string,mixed>>>  $stages
     * @param  array<int,int>  $recent
     * @param  array<int,string>  $relaxed
     * @return array<string,mixed>
     */
    private function report(int $chapterId, $subInstituteId, int $seed, array $target, array $picked, array $stages, array $recent, array $relaxed): array
    {
        $served = array_fill_keys(DifficultyBands::BANDS, 0);
        $sources = [];

        foreach ($picked as $row) {
            $served[$row['difficulty']]++;
            $sources[$row['difficulty_source']] = ($sources[$row['difficulty_source']] ?? 0) + 1;
        }

        // Stages that contributed nothing are noise in the report; a stage
        // that ran and found nothing is signal, so keep those with took = 0
        // only where the band ended up short.
        foreach ($stages as $band => $entries) {
            if ($served[$band] >= $target[$band]) {
                $stages[$band] = array_values(array_filter($entries, fn ($e) => $e['took'] > 0));
            }
        }

        return [
            'seed' => $seed,
            'chapter_id' => $chapterId,
            'sub_institute_id' => $subInstituteId,
            'target' => $target,
            'served' => $served,
            'total' => count($picked),
            'shortfall' => max(0, array_sum($target) - count($picked)),
            'sources' => $sources,
            'stages' => $stages,
            'recency_excluded' => count($recent),
            'recency_relaxed' => $relaxed,
            'generated_at' => now()->toIso8601String(),
        ];
    }
}
