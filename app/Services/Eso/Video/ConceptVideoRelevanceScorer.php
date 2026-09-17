<?php

namespace App\Services\Eso\Video;

use Illuminate\Support\Str;

/**
 * "Is this video actually about THIS concept, or just about this chapter?"
 *
 * The question matters because `content_master.concept_id` is unpopulated
 * across the entire live estate, so the only join available is `chapter_id`.
 * That means every candidate is guaranteed to be topically adjacent, and a
 * naive "best match wins" would hand the same chapter-overview video to all
 * seventeen concepts in a chapter. A student who has just failed a check is
 * at the exact moment they are most willing to spend effort — spending it on
 * a video that does not address their gap is worse than showing no video at
 * all, because the text ladder would otherwise have carried on.
 *
 * So this class is built to say NO. Two independent gates must both pass, and
 * a candidate that clears neither is dropped rather than ranked last.
 *
 * The scoring is chapter-local IDF rather than a static stopword list. Tokens
 * like "metals" in a metals chapter appear in nearly every candidate, so their
 * IDF collapses toward zero on its own. That is self-calibrating: it cannot be
 * wrong about a chapter nobody has looked at, which a hand-maintained list of
 * per-subject noise words inevitably would be.
 *
 * Pure: no DB, no config reads beyond the thresholds handed in. Unit-tested
 * against the real chapter-1014 titles.
 */
class ConceptVideoRelevanceScorer
{
    /**
     * Field weights. A concept token found in the title is strong evidence; the
     * same token recovered from a filename is weak, because filenames on this
     * estate are frequently export artefacts ("audio3.mp4") rather than
     * descriptions written for a reader.
     */
    protected const FIELD_WEIGHTS = [
        'title' => 1.00,
        'meta_tags' => 0.70,
        'description' => 0.60,
        'filename' => 0.35,
    ];

    /** Two adjacent concept tokens landing adjacent in the title. */
    protected const PHRASE_BONUS = 0.15;

    /**
     * English function words plus curriculum boilerplate. These are removed
     * from the CONCEPT side so they never demand a match; on the candidate
     * side IDF already neutralises them.
     */
    protected const STOPWORDS = [
        'and', 'the', 'for', 'with', 'from', 'into', 'its', 'are', 'was', 'were', 'this', 'that',
        'their', 'there', 'they', 'them', 'has', 'have', 'had', 'can', 'will', 'shall', 'been',
        'class', 'grade', 'std', 'standard', 'chapter', 'lesson', 'topic', 'unit', 'part',
        'introduction', 'intro', 'overview', 'video', 'animation', 'lecture', 'explanation',
        'explained', 'ncert', 'cbse', 'icse', 'hindi', 'english', 'full', 'complete', 'session',
        'revision', 'notes', 'ppt', 'pdf', 'question', 'answer', 'exercise', 'solution',
        'solutions', 'mp4', 'hd', 'board', 'science', 'maths', 'mathematics',

        // Curriculum filler. These are the words concept names are padded with
        // rather than the subject of the concept — "Multiples of a NUMBER",
        // "MEANING of divisibility", "FINDING HCF by LISTING factors". Left in,
        // they outvote the one word that actually identifies the concept: a
        // video titled "Multiples" scores 1 of 2 tokens and is rejected, so the
        // concept ends up with no video at all despite an exact match existing.
        'number', 'numbers', 'answers', 'value', 'values', 'meaning', 'finding',
        'listing', 'calculation', 'calculations', 'using', 'given', 'example',
        'examples', 'problem', 'problems', 'sums', 'basics', 'basic', 'simple',
    ];

    /**
     * Filenames that carry no teaching signal at all. Measured on chapter 1014:
     * "audio3.mp4", "audio2.mp4", "audio explanation1.mp4". These are export
     * artefacts, and any token they appear to contain is an accident.
     */
    protected const JUNK_FILENAME = '/^(audio|video|vid|clip|file|untitled|new|final|part|rec|recording|screen[ _-]?record|screencast|whatsapp|img|image|mov|movie|lecture|session|temp|tmp|test|copy|download|output|export)([ _-]*(explanation|of|no|part)?[ _-]*\d*)?$/i';

    /**
     * Score every candidate against the concept and return only those that
     * clear both gates, best first.
     *
     * `pool_mode` selects how the pool itself is interpreted, and getting it
     * wrong silently rejects everything:
     *
     *   'chapter' (default) — the candidates are a chapter's whole library.
     *       They share a topic by construction, so vocabulary common to most
     *       of them carries no information about any one concept. IDF and the
     *       distinctiveness floor apply.
     *
     *   'search' — the candidates are search results for this concept. Here
     *       shared vocabulary means the OPPOSITE: five results all saying
     *       "amphoteric oxides" is the search having worked. Applying the
     *       chapter rules would drive every token's IDF to zero and reject
     *       the entire result set, so they are switched off and the score is
     *       plain weighted coverage.
     *
     * @param  array<int, array<string, mixed>>  $candidates  rows carrying any of
     *         title / description / meta_tags / filename / sort_order / id
     * @param  array{min_score?:float, small_pool_score?:float, small_pool_size?:int, pool_mode?:string}  $opts
     * @return array<int, array<string, mixed>>  each candidate plus match_score + match_reason
     */
    public function rank(string $conceptName, array $candidates, array $opts = []): array
    {
        if ($candidates === []) {
            return [];
        }

        $conceptTokens = $this->conceptTokens($conceptName);
        if ($conceptTokens === []) {
            // A concept whose name is entirely stopwords gives us nothing to
            // match on. Serving the chapter's first video here would be
            // guessing, so we decline.
            return [];
        }

        $pool = [];
        foreach ($candidates as $index => $candidate) {
            $pool[$index] = $this->fieldTokens($candidate);
        }

        $poolSize = count($pool);
        $searchPool = ($opts['pool_mode'] ?? 'chapter') === 'search';

        // One token means one piece of evidence, so it has to be exact.
        $exactOnly = count($conceptTokens) === 1;

        // In search mode the pool is not a library to discriminate within, so
        // the small-pool branch (which exists to handle thin libraries) does
        // not apply either.
        $smallPool = ! $searchPool && $poolSize < (int) ($opts['small_pool_size'] ?? 3);

        // Document frequency across the chapter's own pool.
        $df = [];
        foreach ($conceptTokens as $token) {
            $df[$token] = 0;
            foreach ($pool as $fields) {
                if ($this->tokenInAnyField($token, $fields, $exactOnly)) {
                    $df[$token]++;
                }
            }
        }

        // With a pool of one or two, "how many others also mention this" is
        // not a measurement. Fall back to plain weighted coverage and raise
        // the bar instead of pretending the statistic means something.
        $idf = [];
        foreach ($conceptTokens as $token) {
            $idf[$token] = ($smallPool || $searchPool)
                ? 1.0
                : log(1 + $poolSize / (1 + $df[$token]));
        }

        $idfTotal = array_sum($idf);
        if ($idfTotal <= 0.0) {
            return [];
        }

        $minScore = $smallPool
            ? (float) ($opts['small_pool_score'] ?? 0.65)
            : (float) ($opts['min_score'] ?? 0.55);

        // A token is "distinctive" when it is NOT shared by most of the
        // chapter. At least one match must be distinctive, otherwise the
        // candidate matched only what every video in the chapter matches.
        $distinctiveCeiling = max(1, (int) floor($poolSize / 3));

        $scored = [];
        foreach ($candidates as $index => $candidate) {
            $fields = $pool[$index];

            $weighted = 0.0;
            $matched = [];
            $titleMatches = 0;
            $hasDistinctive = false;

            foreach ($conceptTokens as $token) {
                $bestWeight = 0.0;
                foreach ($fields as $field => $tokens) {
                    if (! $this->tokenMatches($token, $tokens, $exactOnly)) {
                        continue;
                    }
                    $bestWeight = max($bestWeight, self::FIELD_WEIGHTS[$field] ?? 0.0);
                    if ($field === 'title') {
                        $titleMatches++;
                    }
                }

                if ($bestWeight <= 0.0) {
                    continue;
                }

                $weighted += $idf[$token] * $bestWeight;
                $matched[] = $token;

                if (! $smallPool && ! $searchPool && $df[$token] <= $distinctiveCeiling) {
                    $hasDistinctive = true;
                }
            }

            if ($matched === []) {
                continue;
            }

            $score = $weighted / $idfTotal;

            if ($this->hasAdjacentPhrase($conceptTokens, $fields['title'] ?? [])) {
                $score = min(1.0, $score + self::PHRASE_BONUS);
            }

            if ($score < $minScore) {
                continue;
            }

            if ($searchPool) {
                // A search result earns its place by actually naming the
                // concept, not by being unlike its peers. The engine already
                // ranked for relevance; this only rejects obvious drift.
                if ($titleMatches === 0) {
                    continue;
                }
            } elseif ($smallPool) {
                // Without IDF the distinctiveness test is unavailable, so
                // demand instead that a substantial token matched the TITLE.
                if ($titleMatches === 0 || ! $this->hasLongToken($matched)) {
                    continue;
                }
            } elseif (! $hasDistinctive) {
                // Matched only chapter-wide vocabulary. This is the gate that
                // stops "METALS.mp4" becoming the answer to all 17 concepts.
                continue;
            }

            $candidate['match_score'] = round($score, 4);
            $candidate['match_reason'] = $this->reason($matched, $titleMatches, $smallPool);
            $candidate['_title_matches'] = $titleMatches;

            $scored[] = $candidate;
        }

        // Determinism is load-bearing: EsoConceptVideoResolver's rankOffset
        // walks these positions on a second failed check, and it keeps no
        // learner state of its own. Identical input must give identical order.
        usort($scored, static function (array $a, array $b): int {
            $descending = [
                [$b['match_score'], $a['match_score']],
                [$b['_title_matches'], $a['_title_matches']],
                [mb_strlen((string) ($b['title'] ?? '')), mb_strlen((string) ($a['title'] ?? ''))],
            ];

            foreach ($descending as [$left, $right]) {
                $cmp = $left <=> $right;
                if ($cmp !== 0) {
                    return $cmp;
                }
            }

            $ascending = [
                [(int) ($a['sort_order'] ?? 999999), (int) ($b['sort_order'] ?? 999999)],
                [(int) ($a['id'] ?? 0), (int) ($b['id'] ?? 0)],
            ];

            foreach ($ascending as [$left, $right]) {
                $cmp = $left <=> $right;
                if ($cmp !== 0) {
                    return $cmp;
                }
            }

            return 0;
        });

        foreach ($scored as $i => $row) {
            unset($scored[$i]['_title_matches']);
        }

        return array_values($scored);
    }

    /** The concept's meaningful tokens — what a candidate has to match. */
    public function conceptTokens(string $conceptName): array
    {
        return $this->tokenize($conceptName);
    }

    /**
     * Per-field tokens for one candidate. A junk filename contributes nothing
     * rather than contributing noise.
     *
     * @return array<string, array<int, string>>
     */
    protected function fieldTokens(array $candidate): array
    {
        $filename = (string) ($candidate['filename'] ?? '');
        $base = $this->filenameBase($filename);

        return array_filter([
            'title' => $this->tokenize((string) ($candidate['title'] ?? '')),
            'meta_tags' => $this->tokenize((string) ($candidate['meta_tags'] ?? '')),
            'description' => $this->tokenize((string) ($candidate['description'] ?? '')),
            'filename' => preg_match(self::JUNK_FILENAME, $base) === 1 ? [] : $this->tokenize($base),
        ], static fn (array $tokens) => $tokens !== []);
    }

    /**
     * The last path segment of a filename, minus its extension. Many rows on
     * this estate store a full URL here, so the meaningful part is the tail.
     */
    protected function filenameBase(string $filename): string
    {
        $filename = trim($filename);
        if ($filename === '') {
            return '';
        }

        $path = parse_url($filename, PHP_URL_PATH) ?: $filename;
        $base = basename((string) $path);

        return (string) preg_replace('/\.[A-Za-z0-9]{2,5}$/', '', rawurldecode($base));
    }

    /** @return array<int, string> */
    protected function tokenize(string $text): array
    {
        $text = mb_strtolower(trim($text));
        if ($text === '') {
            return [];
        }

        $text = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text);

        $tokens = [];
        foreach (preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
            if (mb_strlen($token) < 3) {
                continue;
            }
            if (ctype_digit($token)) {
                continue;
            }
            if (in_array($token, self::STOPWORDS, true)) {
                continue;
            }
            $tokens[] = $token;
        }

        return array_values(array_unique($tokens));
    }

    /** @param array<string, array<int, string>> $fields */
    protected function tokenInAnyField(string $token, array $fields, bool $exactOnly = false): bool
    {
        foreach ($fields as $tokens) {
            if ($this->tokenMatches($token, $tokens, $exactOnly)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Equal after singularisation, or sharing a long enough common prefix.
     *
     * The common-prefix rule stands in for a stemmer, and it earns its keep on
     * this curriculum: "Chemical Reactivity of Metals" is best served by a
     * video titled "Reaction of metals with water", and strict prefix matching
     * relates neither reaction/reactivity nor chemistry/chemical (neither is a
     * prefix of the other). Without this the right video scores below the gate
     * and the student gets nothing.
     *
     * The floors are what keep it honest. Both tokens must be >= 6 characters
     * and share >= 5, so "ion"/"ionic" and "oxide"/"oxidation" — both real,
     * both wrong — stay unmatched.
     *
     * `$exactOnly` switches the approximation off. It is used when the concept
     * name reduces to a SINGLE token, because then that one token carries the
     * entire score and an approximate match is the whole verdict. The pair that
     * forced this: "Multiples of a number" reduces to {multiples}, and
     * "multiples"/"multiply" share seven leading characters — so a video about
     * multiplication scored a perfect 1.00 for a concept about multiples. With
     * one piece of evidence, it has to be the real thing.
     *
     * @param  array<int, string>  $tokens
     */
    protected function tokenMatches(string $needle, array $tokens, bool $exactOnly = false): bool
    {
        $needleSingular = Str::singular($needle);

        foreach ($tokens as $token) {
            if ($token === $needle || Str::singular($token) === $needleSingular) {
                return true;
            }

            if ($exactOnly) {
                continue;
            }

            if (mb_strlen($token) < 6 || mb_strlen($needle) < 6) {
                continue;
            }

            if ($this->commonPrefixLength($token, $needle) >= 5) {
                return true;
            }
        }

        return false;
    }

    protected function commonPrefixLength(string $a, string $b): int
    {
        $limit = min(mb_strlen($a), mb_strlen($b));

        $shared = 0;
        for ($i = 0; $i < $limit; $i++) {
            if (mb_substr($a, $i, 1) !== mb_substr($b, $i, 1)) {
                break;
            }
            $shared++;
        }

        return $shared;
    }

    /**
     * Two concept tokens that are adjacent in the concept name also landing
     * adjacent in the title — "extraction metals" in "Extraction of metals".
     *
     * @param  array<int, string>  $conceptTokens
     * @param  array<int, string>  $titleTokens
     */
    protected function hasAdjacentPhrase(array $conceptTokens, array $titleTokens): bool
    {
        if (count($conceptTokens) < 2 || count($titleTokens) < 2) {
            return false;
        }

        for ($i = 0; $i < count($conceptTokens) - 1; $i++) {
            for ($j = 0; $j < count($titleTokens) - 1; $j++) {
                $firstPair = $this->tokenMatches($conceptTokens[$i], [$titleTokens[$j]])
                    && $this->tokenMatches($conceptTokens[$i + 1], [$titleTokens[$j + 1]]);

                // Order-insensitive: "metals extraction" counts too.
                $swapped = $this->tokenMatches($conceptTokens[$i], [$titleTokens[$j + 1]])
                    && $this->tokenMatches($conceptTokens[$i + 1], [$titleTokens[$j]]);

                if ($firstPair || $swapped) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @param array<int, string> $matched */
    protected function hasLongToken(array $matched): bool
    {
        foreach ($matched as $token) {
            if (mb_strlen($token) >= 5) {
                return true;
            }
        }

        return false;
    }

    /** @param array<int, string> $matched */
    protected function reason(array $matched, int $titleMatches, bool $smallPool): string
    {
        $reason = sprintf(
            'Matches %s%s',
            implode(', ', array_slice($matched, 0, 4)),
            $titleMatches > 0 ? ' in the title' : ''
        );

        return mb_substr($smallPool ? $reason . ' (few videos in this chapter)' : $reason, 0, 255);
    }
}
