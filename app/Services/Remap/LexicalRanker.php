<?php

namespace App\Services\Remap;

use App\Services\Remap\Support\Bm25;
use App\Services\Remap\Support\TextNormalizer as T;

/**
 * Deterministic candidate ranking. No LLM.
 *
 * Produces the shortlist the adjudicator sees, plus three signals the
 * banding logic depends on:
 *
 *   lex_norm          how strong the best match is, normalised against
 *                     a calibrated reference rather than a guessed constant
 *   lex_margin        how much better the best is than the runner-up,
 *                     i.e. how discriminative the evidence is
 *   syllabus_coverage how much of the legacy vocabulary survives ANYWHERE
 *                     in the current corpus -- the out-of-syllabus probe,
 *                     deliberately independent of per-chapter ranking
 */
class LexicalRanker
{
    private Bm25 $index;
    private array $corpus;
    private array $cfg;

    /** @var array<string,true> every term present anywhere in the corpus */
    private array $corpusVocab = [];

    public function __construct(array $corpus, ?array $cfg = null)
    {
        $this->corpus = $corpus;
        $this->cfg    = $cfg ?? config('remap.lexical');

        $this->index = new Bm25(
            (float) ($this->cfg['k1'] ?? 1.2),
            (float) ($this->cfg['b'] ?? 0.75)
        );

        foreach ($corpus as $chapterId => $profile) {
            $this->index->addDocument($chapterId, $profile['terms']);

            foreach ($profile['terms'] as $term) {
                $this->corpusVocab[$term] = true;
            }
        }
    }

    public function index(): Bm25
    {
        return $this->index;
    }

    public function corpus(): array
    {
        return $this->corpus;
    }

    /**
     * Rank every eligible candidate for a legacy profile.
     *
     * @param  array    $profile        from LegacyProfileBuilder
     * @param  int|null $excludeChapter hide one chapter (leave-one-out calibration)
     * @param  bool     $wide           ignore tiering and consider the whole subject
     * @return array{ranked: array, shortlist: array, lex_norm: float, lex_margin: float, syllabus_coverage: float}
     */
    public function rank(array $profile, ?int $excludeChapter = null, bool $wide = false): array
    {
        $query     = $profile['query'];
        $subjectId = (int) $profile['subject_id'];
        $gate      = (float) ($this->cfg['script_gate'] ?? 0.30);
        $profDev   = (float) ($profile['devanagari'] ?? 0.0);

        // Pass 1 -- raw BM25 only.
        //
        // Raw scores vary by an order of magnitude between groups (a
        // 5-item teacher group scores ~60, a 120-item question group
        // ~900), so bonuses must be scaled against the strongest raw
        // match for THIS group. Absolute bonuses would be negligible
        // for large groups and decisive for small ones.
        $raw = [];

        foreach ($this->corpus as $chapterId => $candidate) {
            if ($excludeChapter !== null && $chapterId === $excludeChapter) {
                continue;
            }

            $sameSubject = $candidate['subject_id'] === $subjectId;

            // Script gate: a Devanagari-heavy profile only considers
            // cross-subject candidates that are also Devanagari-heavy,
            // and vice versa. Measured from the text, never assumed.
            if (!$sameSubject) {
                $candDev = (float) $candidate['devanagari'];
                if (($profDev > $gate) !== ($candDev > $gate)) {
                    continue;
                }
            }

            $raw[$chapterId] = [
                'bm25' => $this->index->score($query, $chapterId),
                'same' => $sameSubject,
                'cand' => $candidate,
            ];
        }

        if (!$raw) {
            return [
                'ranked' => [], 'shortlist' => [], 'lex_norm' => 0.0,
                'lex_margin' => 0.0, 'syllabus_coverage' => $this->syllabusCoverage($profile),
            ];
        }

        $maxRaw = max(array_column($raw, 'bm25'));
        if ($maxRaw <= 0.0) {
            $maxRaw = 1.0;
        }

        $hitFrac   = (float) ($this->cfg['exact_hit_bonus'] ?? 0.08);
        $hitCap    = (float) ($this->cfg['exact_hit_cap'] ?? 0.24);
        $bigFrac   = (float) ($this->cfg['bigram_bonus'] ?? 0.03);
        $bigCap    = (float) ($this->cfg['bigram_cap'] ?? 0.15);
        $priorFrac = (float) ($this->cfg['same_subject_prior'] ?? 0.05);

        $scored = [];

        foreach ($raw as $chapterId => $r) {
            $candidate = $r['cand'];

            $hits    = $this->exactConceptHits($profile, $candidate);
            $overlap = $this->bigramOverlap($profile, $candidate);

            $hitBonus = min($hitCap, $hits['count'] * $hitFrac) * $maxRaw;
            $bigBonus = min($bigCap, $overlap * $bigFrac) * $maxRaw;
            $prior    = $r['same'] ? $priorFrac * $maxRaw : 0.0;

            $scored[$chapterId] = [
                'chapter_id'     => $chapterId,
                'subject_id'     => $candidate['subject_id'],
                'subject_name'   => $candidate['subject_name'],
                'chapter_name'   => $candidate['chapter_name'],
                'is_placeholder' => $candidate['is_placeholder'],
                'concept_count'  => $candidate['concept_count'],
                'same_subject'   => $r['same'],
                'bm25'           => $r['bm25'],
                'exact_hits'     => $hits['count'],
                'matched_labels' => $hits['pairs'],
                'bigram_overlap' => $overlap,
                'score'          => $r['bm25'] + $hitBonus + $bigBonus + $prior,
            ];
        }

        uasort($scored, fn ($a, $b) => [$b['score'], $a['chapter_id']] <=> [$a['score'], $b['chapter_id']]);

        $ranked = $this->applyCrossSubjectGate(array_values($scored));

        $topId = $ranked ? (int) $ranked[0]['chapter_id'] : null;

        return [
            'ranked'            => $ranked,
            'shortlist'         => $this->shortlist($ranked, $subjectId, $wide),
            'lex_norm'          => $this->lexNorm($ranked),
            'lex_margin'        => $this->lexMargin($ranked),
            'syllabus_coverage' => $this->syllabusCoverage($profile),
            'specificity'       => $topId === null ? 0.0 : $this->specificity($query, $topId),
            'match_terms'       => $topId === null ? [] : $this->matchTerms($query, $topId),
        ];
    }

    /**
     * A cross-subject candidate only takes first place if it beats the
     * best same-subject candidate by a clear margin.
     *
     * Cross-subject moves are wanted -- legacy 1039 really does hold
     * History content filed under Civics -- but they must rest on real
     * evidence, not on a marginally higher score against a chapter that
     * happens to be a vocabulary magnet for generic words like "video"
     * or "presentation".
     */
    private function applyCrossSubjectGate(array $ranked): array
    {
        if (count($ranked) < 2 || $ranked[0]['same_subject']) {
            return $ranked;
        }

        $margin   = (float) ($this->cfg['cross_subject_margin'] ?? 0.15);
        $bestSame = null;
        $bestIdx  = null;

        foreach ($ranked as $i => $row) {
            if ($row['same_subject']) {
                $bestSame = $row;
                $bestIdx  = $i;
                break;
            }
        }

        if ($bestSame === null || $bestSame['score'] <= 0.0) {
            return $ranked;
        }

        if ($ranked[0]['score'] < $bestSame['score'] * (1.0 + $margin)) {
            array_splice($ranked, $bestIdx, 1);
            array_unshift($ranked, $bestSame);
        }

        return $ranked;
    }

    /**
     * Legacy concept labels that match a real concept of the candidate.
     *
     * These pairs are carried into the prompt and the audit trail as
     * evidence, so a decision can be explained without re-running.
     */
    private function exactConceptHits(array $profile, array $candidate): array
    {
        $labels = $profile['labels'] ?? [];
        if (!$labels) {
            return ['count' => 0, 'pairs' => []];
        }

        $jaccardMin = (float) ($this->cfg['jaccard_min'] ?? 0.8);
        $similarMin = (float) ($this->cfg['similar_text_min'] ?? 0.85);

        $pairs = [];

        foreach ($labels as $label) {
            foreach ($candidate['concept_labels'] as $concept) {
                if (T::labelsMatch($label, $concept['name'], $jaccardMin, $similarMin)) {
                    $pairs[] = ['legacy' => $label, 'concept' => $concept['name']];
                    break; // one hit per legacy label
                }
            }
        }

        return ['count' => count($pairs), 'pairs' => $pairs];
    }

    private function bigramOverlap(array $profile, array $candidate): int
    {
        $profBigrams = $profile['bigrams'] ?? [];
        if (!$profBigrams) {
            return 0;
        }

        static $candCache = [];
        $key = $candidate['chapter_id'];

        if (!isset($candCache[$key])) {
            $candCache[$key] = array_flip(T::bigrams($candidate['terms']));
        }

        $overlap = 0;
        foreach ($profBigrams as $bigram) {
            if (isset($candCache[$key][$bigram])) {
                $overlap++;
            }
        }

        return $overlap;
    }

    /**
     * Top-N within the legacy subject, unioned with top-N across all
     * subjects, so a cross-subject move is always representable.
     */
    private function shortlist(array $ranked, int $subjectId, bool $wide): array
    {
        $perTier = (int) ($this->cfg['shortlist_per_tier'] ?? 5);
        $max     = (int) ($this->cfg['shortlist_max'] ?? 10);

        if ($wide) {
            // Retrieval is untrustworthy for this subject: hand the LLM
            // every chapter of the subject plus the global top few, and
            // let it do the ranking instead of trusting BM25.
            $sameSubject = array_values(array_filter($ranked, fn ($r) => $r['subject_id'] === $subjectId));
            $global      = array_slice($ranked, 0, $perTier);

            return $this->dedupe(array_merge($sameSubject, $global), max($max, count($sameSubject) + $perTier));
        }

        $tier1 = array_slice(array_values(array_filter($ranked, fn ($r) => $r['same_subject'])), 0, $perTier);
        $tier2 = array_slice($ranked, 0, $perTier);

        return $this->dedupe(array_merge($tier1, $tier2), $max);
    }

    private function dedupe(array $rows, int $max): array
    {
        $seen = [];
        $out  = [];

        foreach ($rows as $row) {
            if (isset($seen[$row['chapter_id']])) {
                continue;
            }
            $seen[$row['chapter_id']] = true;
            $out[] = $row;

            if (count($out) >= $max) {
                break;
            }
        }

        return $out;
    }

    /**
     * Absolute match strength, normalised by a reference that
     * remap:calibrate derives from correct matches on the Science
     * control set.
     */
    private function lexNorm(array $ranked): float
    {
        if (!$ranked) {
            return 0.0;
        }

        $reference = (float) ($this->cfg['reference'] ?? 12.0);

        return $reference <= 0 ? 0.0 : min(1.0, max(0.0, $ranked[0]['score'] / $reference));
    }

    /** How much better the winner is than the runner-up. */
    private function lexMargin(array $ranked): float
    {
        if (count($ranked) < 2 || $ranked[0]['score'] <= 0) {
            return count($ranked) === 1 ? 1.0 : 0.0;
        }

        return max(0.0, min(1.0, ($ranked[0]['score'] - $ranked[1]['score']) / $ranked[0]['score']));
    }

    /**
     * Fraction of the legacy profile's distinctive keyphrases that
     * appear anywhere in the surviving 115-chapter corpus.
     *
     * A topic dropped by the NCERT rationalisation has vocabulary that
     * is absent from EVERY surviving chapter, which is a different
     * question from "which chapter ranks highest" -- that independence
     * is what makes it safe to gate soft-deletion on.
     */
    public function syllabusCoverage(array $profile): float
    {
        $phrases = $profile['keyphrases'] ?? [];
        if (!$phrases) {
            return 0.0;
        }

        $present = 0;

        foreach ($phrases as $phrase) {
            $terms = explode(' ', $phrase);
            $all   = true;

            foreach ($terms as $term) {
                if (!isset($this->corpusVocab[$term])) {
                    $all = false;
                    break;
                }
            }

            if ($all) {
                $present++;
            }
        }

        return $present / count($phrases);
    }

    /**
     * Share of a match that rests on DISTINCTIVE vocabulary.
     *
     * The decisive quality gate for this dataset. Many classroom
     * resources have no topic text at all -- Maths content titles are
     * YouTube ids ("edtfk_7uviq.mp4") and teacher resources are generic
     * boilerplate ("Teacher Training PPT", "KSA Framework"). BM25 will
     * still rank those against some chapter, but the score comes
     * entirely from common words, so the ranking is noise.
     *
     * Specificity measures what fraction of the score comes from terms
     * whose IDF is above the corpus median. A real topic match scores
     * on "triangle" and "trigonometry"; a filename match scores on
     * "video" and "class". The threshold is derived from the corpus,
     * not assumed.
     */
    public function specificity(array $query, int $chapterId): float
    {
        $contrib = $this->index->contributions($query, $chapterId);
        if (!$contrib) {
            return 0.0;
        }

        $median = $this->index->medianIdf();
        $total  = array_sum($contrib);

        if ($total <= 0.0) {
            return 0.0;
        }

        $distinctive = 0.0;
        foreach ($contrib as $term => $value) {
            if ($this->index->idf($term) >= $median) {
                $distinctive += $value;
            }
        }

        return $distinctive / $total;
    }

    /**
     * The distinctive terms that drove a match, strongest first. Carried
     * into the audit trail so a decision can be read back in words.
     *
     * @return array<int, array{term: string, contribution: float}>
     */
    public function matchTerms(array $query, int $chapterId, int $limit = 12): array
    {
        $contrib = $this->index->contributions($query, $chapterId);
        if (!$contrib) {
            return [];
        }

        $median = $this->index->medianIdf();

        $rows = [];
        foreach ($contrib as $term => $value) {
            if ($this->index->idf($term) >= $median) {
                $rows[] = ['term' => $term, 'contribution' => round($value, 4)];
            }
        }

        usort($rows, fn ($a, $b) => $b['contribution'] <=> $a['contribution']);

        return array_slice($rows, 0, $limit);
    }

    /**
     * Rank by per-item vote instead of one pooled query.
     *
     * A pooled query lets a small number of items with rare vocabulary
     * outweigh a large number with common vocabulary. Geography group
     * 6237 is the worked example: 18 questions about forests and
     * wildlife, 7 about water resources. Pooled BM25 chose Water,
     * because "narmada", "tehri" and "drip" are rare while "forest" and
     * "species" appear in many chapters. Voting chooses Forest, which
     * is what the content titles and the questions themselves say.
     *
     * An item whose best match rests on no distinctive term abstains
     * rather than voting for noise, so filename-only rows cannot swing
     * a group.
     *
     * @param  array $items items from LegacyProfileBuilder
     * @return array{votes: array, winner: ?int, winner_share: float, voters: int, abstained: int, spread: array}
     */
    public function voteOnItems(array $items, int $subjectId, float $devanagari = 0.0): array
    {
        $minLen = (int) ($this->cfg['min_token_len'] ?? 3);

        $votes     = [];
        $abstained = 0;
        $voters    = 0;

        foreach ($items as $item) {
            $terms = T::tokens($item['text'] ?? '', $minLen);
            if (!$terms) {
                $abstained++;
                continue;
            }

            $query = Bm25::weightedQuery([
                ['terms' => $terms, 'weight' => (float) ($item['weight'] ?? 1.0)],
            ]);

            $ranked = $this->rank([
                'subject_id' => $subjectId,
                'query'      => $query,
                'labels'     => [],
                'bigrams'    => [],
                'keyphrases' => [],
                'devanagari' => $devanagari,
            ]);

            if (!$ranked['ranked']) {
                $abstained++;
                continue;
            }

            $top = (int) $ranked['ranked'][0]['chapter_id'];

            // Abstain unless at least one distinctive term drove it.
            if (!$this->matchTerms($query, $top, 1)) {
                $abstained++;
                continue;
            }

            // Weight the vote by how distinctive the match is. A generic
            // drill ("choose the past perfect continuous form") matches
            // some grammar chapter on common vocabulary and should not
            // outvote a lesson-specific question about Nelson Mandela.
            $weight = max(0.05, $this->specificity($query, $top));

            $votes[$top] = ($votes[$top] ?? 0) + $weight;
            $voters++;
        }

        if (!$votes) {
            return ['votes' => [], 'winner' => null, 'winner_share' => 0.0, 'voters' => 0, 'abstained' => $abstained, 'spread' => []];
        }

        arsort($votes);
        $winner = (int) array_key_first($votes);

        $spread = [];
        foreach (array_slice($votes, 0, 5, true) as $chapterId => $n) {
            $spread[] = [
                'chapter_id' => $chapterId,
                'votes'      => round($n, 2),
                'name'       => $this->corpus[$chapterId]['chapter_name'] ?? '?',
                'same'       => ($this->corpus[$chapterId]['subject_id'] ?? null) === $subjectId,
            ];
        }

        return [
            'votes'        => $votes,
            'winner'       => $winner,
            'winner_share' => $votes[$winner] / max(1e-9, array_sum($votes)),
            'voters'       => $voters,
            'abstained'    => $abstained,
            'spread'       => $spread,
        ];
    }
}
