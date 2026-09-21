<?php

namespace App\Services\Remap\Support;

/**
 * Okapi BM25 over a small, fully in-memory corpus.
 *
 * The corpus here is the 115 std-10 chapters, so an inverted index in
 * PHP arrays is ample. Pure and deterministic: no DB, no IO, no clock,
 * which is what lets remap:calibrate treat it as a fixed measuring
 * instrument while it tunes thresholds around it.
 */
class Bm25
{
    /** @var array<string|int, array<string,int>> docId => term => raw frequency */
    private array $termFreq = [];

    /** @var array<string|int, int> docId => token count */
    private array $docLen = [];

    /** @var array<string, int> term => document frequency */
    private array $docFreq = [];

    /** @var array<string, float> term => idf, memoised */
    private array $idf = [];

    private float $avgDocLen = 0.0;

    public function __construct(
        private float $k1 = 1.2,
        private float $b = 0.75
    ) {
    }

    /**
     * Add a document. Terms may repeat; weighting is by repetition.
     *
     * @param string[] $terms
     */
    public function addDocument(string|int $docId, array $terms): void
    {
        $freq = [];
        foreach ($terms as $term) {
            $freq[$term] = ($freq[$term] ?? 0) + 1;
        }

        $this->termFreq[$docId] = $freq;
        $this->docLen[$docId]   = count($terms);

        foreach (array_keys($freq) as $term) {
            $this->docFreq[$term] = ($this->docFreq[$term] ?? 0) + 1;
        }

        $this->idf       = [];
        $this->avgDocLen = 0.0;
    }

    /**
     * Score one document against a weighted query vector.
     *
     * Query weights multiply the term's contribution, which is how the
     * legacy-profile field weights (concept x4, title x2, ...) enter the
     * ranking without being baked into the index.
     *
     * @param array<string,float> $queryWeights term => weight
     */
    public function score(array $queryWeights, string|int $docId): float
    {
        if (!isset($this->termFreq[$docId])) {
            return 0.0;
        }

        $freq   = $this->termFreq[$docId];
        $len    = $this->docLen[$docId];
        $avgdl  = $this->averageDocLength();
        $score  = 0.0;

        // Guard: an empty corpus or all-empty documents.
        if ($avgdl <= 0.0) {
            return 0.0;
        }

        foreach ($queryWeights as $term => $weight) {
            $tf = $freq[$term] ?? 0;
            if ($tf === 0 || $weight <= 0.0) {
                continue;
            }

            $numerator   = $tf * ($this->k1 + 1);
            $denominator = $tf + $this->k1 * (1 - $this->b + $this->b * ($len / $avgdl));

            $score += $weight * $this->idf($term) * ($numerator / $denominator);
        }

        return $score;
    }

    /**
     * Score every document, highest first.
     *
     * @param array<string,float> $queryWeights
     * @return array<string|int, float> docId => score, descending
     */
    public function rank(array $queryWeights): array
    {
        $scores = [];

        foreach (array_keys($this->termFreq) as $docId) {
            $scores[$docId] = $this->score($queryWeights, $docId);
        }

        arsort($scores);

        return $scores;
    }

    /**
     * Probabilistic IDF with the standard +0.5 smoothing, floored at a
     * small positive value so a term present in every document still
     * contributes a little rather than flipping the score negative.
     */
    public function idf(string $term): float
    {
        if (isset($this->idf[$term])) {
            return $this->idf[$term];
        }

        $n  = count($this->termFreq);
        $df = $this->docFreq[$term] ?? 0;

        if ($n === 0) {
            return $this->idf[$term] = 0.0;
        }

        $value = log(1 + (($n - $df + 0.5) / ($df + 0.5)));

        return $this->idf[$term] = max($value, 1e-6);
    }

    public function averageDocLength(): float
    {
        if ($this->avgDocLen > 0.0) {
            return $this->avgDocLen;
        }

        if (!$this->docLen) {
            return 0.0;
        }

        return $this->avgDocLen = array_sum($this->docLen) / count($this->docLen);
    }

    public function documentCount(): int
    {
        return count($this->termFreq);
    }

    public function hasDocument(string|int $docId): bool
    {
        return isset($this->termFreq[$docId]);
    }

    /**
     * Remove a document. Used by the leave-one-out calibration probe,
     * which must be able to hide a chapter from its own corpus.
     */
    public function removeDocument(string|int $docId): void
    {
        if (!isset($this->termFreq[$docId])) {
            return;
        }

        foreach (array_keys($this->termFreq[$docId]) as $term) {
            if (isset($this->docFreq[$term]) && --$this->docFreq[$term] <= 0) {
                unset($this->docFreq[$term]);
            }
        }

        unset($this->termFreq[$docId], $this->docLen[$docId]);

        $this->idf       = [];
        $this->avgDocLen = 0.0;
    }

    /**
     * Build a weighted query vector from field => [terms] with per-field
     * weights, collapsing repeated terms additively.
     *
     * @param  array<string, array{terms: string[], weight: float}> $fields
     * @return array<string, float>
     */
    public static function weightedQuery(array $fields): array
    {
        $query = [];

        foreach ($fields as $field) {
            $weight = $field['weight'] ?? 1.0;

            foreach ($field['terms'] ?? [] as $term) {
                $query[$term] = ($query[$term] ?? 0.0) + $weight;
            }
        }

        return $query;
    }

    /**
     * Per-term contribution to a document's score.
     *
     * Lets a caller ask WHY a document scored, which is what separates
     * a genuine topic match ("triangle", "trigonometry") from a match
     * on generic vocabulary ("video", "class", "presentation") that
     * says nothing about subject matter.
     *
     * @param  array<string,float> $queryWeights
     * @return array<string,float> term => contribution
     */
    public function contributions(array $queryWeights, string|int $docId): array
    {
        if (!isset($this->termFreq[$docId])) {
            return [];
        }

        $freq  = $this->termFreq[$docId];
        $len   = $this->docLen[$docId];
        $avgdl = $this->averageDocLength();

        if ($avgdl <= 0.0) {
            return [];
        }

        $out = [];

        foreach ($queryWeights as $term => $weight) {
            $tf = $freq[$term] ?? 0;
            if ($tf === 0 || $weight <= 0.0) {
                continue;
            }

            $numerator   = $tf * ($this->k1 + 1);
            $denominator = $tf + $this->k1 * (1 - $this->b + $this->b * ($len / $avgdl));

            $out[$term] = $weight * $this->idf($term) * ($numerator / $denominator);
        }

        return $out;
    }

    /**
     * Median IDF across the corpus vocabulary. The dividing line
     * between "common" and "distinctive" terms, derived from the corpus
     * itself rather than a hand-picked constant.
     */
    public function medianIdf(): float
    {
        static $cache = null;
        static $stamp = null;

        $key = count($this->docFreq) . ':' . count($this->termFreq);
        if ($cache !== null && $stamp === $key) {
            return $cache;
        }

        if (!$this->docFreq) {
            return 0.0;
        }

        $idfs = [];
        foreach (array_keys($this->docFreq) as $term) {
            $idfs[] = $this->idf($term);
        }

        sort($idfs);
        $n = count($idfs);
        $median = $n % 2 === 0
            ? ($idfs[$n / 2 - 1] + $idfs[$n / 2]) / 2
            : $idfs[(int) floor($n / 2)];

        $stamp = $key;

        return $cache = $median;
    }

    /** How many documents contain a term. */
    public function documentFrequency(string $term): int
    {
        return $this->docFreq[$term] ?? 0;
    }
}
