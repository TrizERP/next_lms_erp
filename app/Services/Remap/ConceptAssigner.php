<?php

namespace App\Services\Remap;

use App\Services\Remap\Support\Bm25;
use App\Services\Remap\Support\TextNormalizer as T;
use Illuminate\Support\Facades\DB;

/**
 * Assigns a concept to a question, choosing only among the concepts of
 * that question's own chapter.
 *
 * Constraining candidates to the chapter is what makes this safe
 * without a model: the chapter is already established by the crosswalk,
 * and a chapter carries 8-29 concepts, so the task is a small, closed
 * ranking rather than open-ended classification.
 *
 * Two gates keep it honest:
 *   - a question whose best match rests on no distinctive term is left
 *     unassigned rather than attached to the chapter's most generic
 *     concept
 *   - the winning concept must belong to the question's own chapter,
 *     subject, standard and tenant, re-checked at write time
 */
class ConceptAssigner
{
    private array $scope;

    /** @var array<int, array{index: Bm25, rows: array}> chapter_id => candidates */
    private array $cache = [];

    /**
     * Global term statistics, built from all 115 std-10 chapters.
     *
     * Distinctiveness must be judged against the whole corpus, not
     * against one chapter's dozen concepts. Inside a 12-document index
     * ordinary words like "under", "three" and "year" score as rare and
     * would qualify as topical evidence; against the full corpus they
     * correctly do not.
     */
    private ?Bm25 $global = null;
    private int $maxDocFreq = 1;

    public function __construct(?array $scope = null, ?Bm25 $global = null)
    {
        $this->scope = $scope ?? config('remap.scope');

        if ($global === null) {
            $corpus  = (new ChapterCorpusBuilder($this->scope))->build();
            $global  = new Bm25();
            foreach ($corpus as $chapterId => $c) {
                $global->addDocument($chapterId, $c['terms']);
            }
        }

        $this->global = $global;

        // A term counts as topical when it appears in at most this
        // share of the 115 chapters. Using the median IDF instead was
        // far too strict: most corpus vocabulary is rare, so the median
        // sits near the top and only near-unique terms qualified,
        // leaving 86% of questions unassigned.
        $this->maxDocFreq = max(1, (int) ceil(
            (float) config('remap.lexical.concept_term_df_share', 0.10) * max(1, $global->documentCount())
        ));
    }

    /**
     * Candidate concepts for a chapter, with a BM25 index over them.
     */
    public function candidates(int $chapterId): array
    {
        if (isset($this->cache[$chapterId])) {
            return $this->cache[$chapterId];
        }

        $rows = DB::table('lms_concept')
            ->where('chapter_id', $chapterId)
            ->where('standard_id', $this->scope['standard_id'])
            ->where('sub_institute_id', $this->scope['sub_institute_id'])
            ->where('concept_show_hide', 1)
            ->orderBy('id')
            ->get(['id', 'name', 'description', 'definition', 'chapter_id', 'subject_id', 'standard_id', 'sub_institute_id', 'topic_id']);

        $minLen = (int) config('remap.lexical.min_token_len', 3);
        $index  = new Bm25(
            (float) config('remap.lexical.k1', 1.2),
            (float) config('remap.lexical.b', 0.75)
        );

        $byId = [];

        foreach ($rows as $row) {
            $terms = array_merge(
                T::tokens($row->name, $minLen),
                T::tokens($row->name, $minLen),
                T::tokens($row->name, $minLen),
                T::tokens(trim(($row->description ?? '') . ' ' . ($row->definition ?? '')), $minLen)
            );

            $index->addDocument((int) $row->id, $terms);
            $byId[(int) $row->id] = $row;
        }

        return $this->cache[$chapterId] = ['index' => $index, 'rows' => $byId];
    }

    /**
     * Best concept for one question, or null when the evidence is too
     * thin to choose.
     *
     * @return array{concept: object, score: float, margin: float, terms: array}|null
     */
    public function match(object $question, int $chapterId): ?array
    {
        $bundle = $this->candidates($chapterId);

        if (!$bundle['rows']) {
            return null;
        }

        $minLen = (int) config('remap.lexical.min_token_len', 3);

        // The legacy concept/subconcept varchars are human-authored
        // labels and carry far more signal than the question body, so
        // they are weighted well above it.
        $query = Bm25::weightedQuery([
            ['terms' => T::tokens($question->concept ?? '', $minLen),      'weight' => 4.0],
            ['terms' => T::tokens($question->subconcept ?? '', $minLen),   'weight' => 4.0],
            ['terms' => T::tokens($question->question_title ?? '', $minLen), 'weight' => 2.0],
            ['terms' => T::tokens($question->answer ?? '', $minLen),       'weight' => 1.0],
        ]);

        if (!$query) {
            return null;
        }

        $ranked = $bundle['index']->rank($query);

        if (!$ranked) {
            return null;
        }

        $ids    = array_keys($ranked);
        $scores = array_values($ranked);
        $topId  = (int) $ids[0];

        if ($scores[0] <= 0.0) {
            return null;
        }

        // Require at least one distinctive term, measured against this
        // chapter's own concept vocabulary.
        $contrib = $bundle['index']->contributions($query, $topId);
        $terms   = [];

        foreach ($contrib as $term => $value) {
            // Judged globally, so chapter-local rarity cannot promote an
            // ordinary word into evidence.
            $df = $this->global->documentFrequency($term);

            if ($df > 0 && $df <= $this->maxDocFreq) {
                $terms[] = $term;
            }
        }

        if (!$terms) {
            return null;
        }

        $margin = count($scores) > 1 && $scores[0] > 0
            ? ($scores[0] - $scores[1]) / $scores[0]
            : 1.0;

        // A near-tie between two concepts of the same chapter is not
        // evidence; it usually means the question is a stray that was
        // already misfiled before the remap.
        if ($margin < (float) config('remap.thresholds.concept_min_margin', 0.15)) {
            return null;
        }

        return [
            'concept' => $bundle['rows'][$topId],
            'score'   => $scores[0],
            'margin'  => $margin,
            'terms'   => array_slice($terms, 0, 8),
        ];
    }
}
