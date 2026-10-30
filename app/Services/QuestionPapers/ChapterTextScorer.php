<?php

namespace App\Services\QuestionPapers;

/**
 * Pure text scoring: how well does one question's wording match each candidate chapter's curriculum text?
 *
 * TF-IDF cosine over unigrams and bigrams, with the chapter's text weighted by how authoritative the field is (its title
 * and concept/topic names count more than a long description). No database, no model, no network: the same input always
 * gives the same ranking, which is what makes the confidence thresholds testable and calibratable against questions whose
 * chapter is already known.
 *
 * "Score" is the cosine similarity (0..1) of the question to the best chapter; "margin" is how far that is ahead of the
 * runner-up. Both are reported so a caller can require BOTH a strong match and clear separation.
 */
final class ChapterTextScorer
{
    /** Words that carry no subject meaning in an exam paper (instructions, connectives). */
    private const STOPWORDS = [
        'a', 'an', 'the', 'and', 'or', 'of', 'in', 'on', 'at', 'to', 'for', 'with', 'by', 'from', 'as', 'is', 'are', 'was', 'were', 'be', 'been',
        'it', 'its', 'this', 'that', 'these', 'those', 'which', 'what', 'who', 'whom', 'whose', 'when', 'where', 'why', 'how', 'not', 'no', 'yes',
        'do', 'does', 'did', 'can', 'could', 'will', 'would', 'should', 'may', 'might', 'has', 'have', 'had', 'than', 'then', 'there', 'their',
        'they', 'them', 'we', 'you', 'your', 'our', 'his', 'her', 'he', 'she', 'if', 'but', 'so', 'such', 'also', 'into', 'out', 'up', 'down',
        'one', 'two', 'three', 'four', 'following', 'correct', 'incorrect', 'option', 'options', 'choose', 'select', 'statement', 'statements',
        'true', 'false', 'answer', 'answers', 'question', 'questions', 'define', 'explain', 'describe', 'give', 'write', 'state', 'name', 'list',
        'mark', 'marks', 'fill', 'blank', 'blanks', 'match', 'column', 'following', 'given', 'below', 'above', 'between', 'about', 'any', 'all',
        'each', 'both', 'either', 'neither', 'only', 'other', 'another', 'some', 'most', 'more', 'less', 'many', 'much', 'very', 'used', 'use',
        'called', 'known', 'example', 'examples', 'following', 'assertion', 'reason', 'case', 'study', 'passage', 'read', 'based', 'short', 'long',
    ];

    /** Field weights for a chapter's text: a title says more about the chapter than a paragraph of description. */
    public const WEIGHTS = ['title' => 4, 'concepts' => 3, 'topics' => 3, 'outcomes' => 1, 'semantic' => 1, 'description' => 1];

    /**
     * @param  array<int, array<string, string>>  $chapters  chapterId => [field => text], fields as in WEIGHTS
     * @return array<int, array<string, float>>  chapterId => term => weight (L2-normalised), plus the corpus idf via idf()
     */
    private array $vectors = [];

    /** @var array<string, float> */
    private array $idf = [];

    /** @param array<int, array<string, string>> $chapters */
    public function __construct(array $chapters)
    {
        $counts = [];
        foreach ($chapters as $id => $fields) {
            $terms = [];
            foreach ($fields as $field => $text) {
                $weight = self::WEIGHTS[$field] ?? 1;
                foreach (self::terms((string) $text) as $term) {
                    $terms[$term] = ($terms[$term] ?? 0) + $weight;
                }
            }
            $counts[$id] = $terms;
        }

        $n = count($counts);
        $df = [];
        foreach ($counts as $terms) {
            foreach (array_keys($terms) as $term) {
                $df[$term] = ($df[$term] ?? 0) + 1;
            }
        }
        foreach ($df as $term => $d) {
            // Smoothed idf: a term in every chapter still counts a little; one in a single chapter counts most.
            $this->idf[$term] = log(($n + 1) / ($d + 1)) + 1.0;
        }

        foreach ($counts as $id => $terms) {
            $vector = [];
            foreach ($terms as $term => $count) {
                $vector[$term] = (1 + log($count)) * $this->idf[$term];
            }
            $this->vectors[$id] = self::normalise($vector);
        }
    }

    /**
     * Rank the chapters for one question's text.
     *
     * @return list<array{chapter_id: int, score: float, terms: list<string>}> best first; empty when nothing overlaps
     */
    public function rank(string $text): array
    {
        $query = [];
        foreach (self::terms($text) as $term) {
            if (isset($this->idf[$term])) {
                $query[$term] = ($query[$term] ?? 0) + 1;
            }
        }
        if ($query === []) {
            return [];
        }
        foreach ($query as $term => $count) {
            $query[$term] = (1 + log($count)) * $this->idf[$term];
        }
        $query = self::normalise($query);

        $ranked = [];
        foreach ($this->vectors as $id => $vector) {
            $score = 0.0;
            $shared = [];
            foreach ($query as $term => $weight) {
                if (isset($vector[$term])) {
                    $part = $weight * $vector[$term];
                    $score += $part;
                    $shared[$term] = $part;
                }
            }
            if ($score > 0) {
                arsort($shared);
                $ranked[] = ['chapter_id' => (int) $id, 'score' => round($score, 4), 'terms' => array_slice(array_keys($shared), 0, 5)];
            }
        }
        usort($ranked, fn ($a, $b) => $b['score'] <=> $a['score'] ?: $a['chapter_id'] <=> $b['chapter_id']);

        return $ranked;
    }

    /** @return list<string> unigram and bigram terms of a text */
    public static function terms(string $text): array
    {
        $text = mb_strtolower(strip_tags($text));
        preg_match_all('/[\p{L}\p{N}]+/u', $text, $m);

        $words = [];
        foreach ($m[0] as $word) {
            if (mb_strlen($word) < 2 || in_array($word, self::STOPWORDS, true) || ctype_digit($word)) {
                continue;
            }
            $words[] = self::stem($word);
        }

        $terms = $words;
        for ($i = 0; $i + 1 < count($words); $i++) {
            $terms[] = $words[$i] . ' ' . $words[$i + 1];
        }

        return $terms;
    }

    /** Conservative suffix stripping so "cells"/"cell" and "reproduction"/"reproduce" meet; no aggressive stemming. */
    private static function stem(string $word): string
    {
        if (mb_strlen($word) <= 4) {
            return $word;
        }
        foreach ([['ies', 'y'], ['sses', 'ss'], ['ing', ''], ['ed', ''], ['es', ''], ['s', '']] as [$suffix, $replacement]) {
            if (str_ends_with($word, $suffix) && mb_strlen($word) - mb_strlen($suffix) >= 4 && !str_ends_with($word, 'ss')) {
                return mb_substr($word, 0, mb_strlen($word) - mb_strlen($suffix)) . $replacement;
            }
        }

        return $word;
    }

    /** @param array<string, float> $vector @return array<string, float> */
    private static function normalise(array $vector): array
    {
        $norm = sqrt(array_sum(array_map(fn ($w) => $w * $w, $vector)));
        if ($norm <= 0) {
            return $vector;
        }

        return array_map(fn ($w) => $w / $norm, $vector);
    }
}
