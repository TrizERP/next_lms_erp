<?php

namespace App\Services\QuestionPapers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Suggests, for EACH question of a paper, which chapter of the resolved standard and subject it belongs to.
 *
 * A paper is never assumed to be about one chapter: every question is ranked against the chapters on its own wording.
 * The evidence is the school's own curriculum data for those chapters only (title, key concepts, description, topics,
 * concepts, learning outcomes, semantic intelligence), read once per paper and scored locally by ChapterTextScorer: no AI
 * call per question, no second extraction pipeline.
 *
 * A chapter is ASSIGNED only when the match is both strong (MIN_SCORE) and clearly ahead of the runner-up (MIN_MARGIN_RATIO),
 * thresholds measured against questions whose chapter is already known (see docs/QUESTION_PAPER_CHAPTER_MATCHING.md).
 * Anything weaker stays a SUGGESTION with its reason and alternatives, for a teacher to confirm; it is never forced.
 */
class QuestionPaperChapterMatcher
{
    /*
     * Thresholds measured on 2,346 textbook-extracted Standard 9 Science questions whose chapter is already recorded
     * (read-only; see docs/QUESTION_PAPER_CHAPTER_MATCHING.md): top-1 accuracy 94.2%; with these two rules the automatic
     * assignment was right 99.4% of the time and covered 74% of questions. The remaining 26% become suggestions.
     * Cosine scores of a short question against a whole chapter are small by nature, so the absolute floor is low and the
     * lead over the runner-up carries the weight.
     */

    /** Cosine similarity the best chapter must reach before it can be assigned automatically. */
    public const MIN_SCORE = 0.06;

    /** The best chapter must lead the runner-up by at least this fraction of its own score: (s1 - s2) / s1. */
    public const MIN_MARGIN_RATIO = 0.25;

    /** A chapter is only assigned automatically when there are at least this many chapters to choose between. */
    public const MIN_CANDIDATES = 2;

    /** Below this the best chapter is not even worth suggesting. */
    public const MIN_SUGGEST = 0.03;

    /**
     * What each level has actually been worth: the share of textbook-extracted Standard 9 Science questions (2,346) whose
     * recorded chapter the matcher named first, per level. Shown to the teacher as the confidence, so it is a measured
     * figure and not an invented one. high = assigned automatically; close = the top two chapters are near each other;
     * weak = few shared terms but a clear lead; none = nothing worth suggesting.
     */
    public const LEVEL_ACCURACY = ['high' => 99, 'close' => 73, 'weak' => 84, 'none' => 46];

    /** @var array<string, array{names: array<int, string>, scorer: ChapterTextScorer}> */
    private array $cache = [];

    /**
     * @param  array<int|string, array<string, mixed>>  $items  itemKey => normalised question payload (question_text, options, model_answer)
     * @return array<int|string, array<string, mixed>> itemKey => suggestion
     */
    public function suggest(int $tenant, int $standardId, int $subjectId, array $items): array
    {
        ['names' => $names, 'scorer' => $scorer] = $this->load($tenant, $standardId, $subjectId);

        $out = [];
        foreach ($items as $key => $item) {
            $out[$key] = $this->decide($scorer->rank($this->textOf($item)), $names);
        }

        return $out;
    }

    /**
     * Which subject's chapters best fit these questions, for the case where the paper's heading names no subject.
     * Only ever a SUGGESTION: the caller must not assign it.
     *
     * @param  list<int>  $subjectIds  subjects mapped to the standard
     * @param  list<array<string, mixed>>  $items
     * @return array{subject_id: int, share: float}|null the subject that clearly wins, else null
     */
    public function suggestSubject(int $tenant, int $standardId, array $subjectIds, array $items): ?array
    {
        $votes = [];
        $total = 0;
        foreach ($subjectIds as $subjectId) {
            ['scorer' => $scorer] = $this->load($tenant, $standardId, (int) $subjectId);
            foreach ($items as $i => $item) {
                $best = $scorer->rank($this->textOf($item))[0]['score'] ?? 0.0;
                $votes[$i][(int) $subjectId] = $best;
            }
        }
        $wins = [];
        foreach ($votes as $perSubject) {
            arsort($perSubject);
            $top = array_key_first($perSubject);
            if ($top !== null && $perSubject[$top] >= self::MIN_SUGGEST) {
                $wins[$top] = ($wins[$top] ?? 0) + 1;
                $total++;
            }
        }
        if ($total === 0) {
            return null;
        }
        arsort($wins);
        $subject = array_key_first($wins);
        $share = $wins[$subject] / $total;

        // A subject is suggested only when it wins the clear majority of the questions that match anything.
        return $share >= 0.7 && $total >= 2 ? ['subject_id' => (int) $subject, 'share' => round($share, 2)] : null;
    }

    /** @return array<int, string> chapterId => name, for the chapters of this standard and subject */
    public function chapterNames(int $tenant, int $standardId, int $subjectId): array
    {
        return $this->load($tenant, $standardId, $subjectId)['names'];
    }

    /**
     * Score one question and turn the ranking into a decision.
     *
     * @param  list<array{chapter_id: int, score: float, terms: list<string>}>  $ranked
     * @param  array<int, string>  $names
     * @return array<string, mixed>
     */
    public function decide(array $ranked, array $names): array
    {
        $none = ['chapter_id' => null, 'suggested_id' => null, 'suggested_name' => null, 'score' => 0.0, 'margin' => 0.0, 'level' => 'none', 'confidence' => self::LEVEL_ACCURACY['none'], 'auto' => false, 'alternatives' => [],
            'reason' => 'Nothing in the question matches the curriculum text of any chapter.'];
        if ($ranked === [] || $ranked[0]['score'] < self::MIN_SUGGEST) {
            return $none;
        }

        $best = $ranked[0];
        $second = $ranked[1]['score'] ?? 0.0;
        $margin = $best['score'] > 0 ? round(($best['score'] - $second) / $best['score'], 3) : 0.0;
        // With a single candidate there is nothing to separate it from, so a "clear lead" proves nothing: never automatic.
        $contrast = count($names) >= self::MIN_CANDIDATES;
        $auto = $contrast && $best['score'] >= self::MIN_SCORE && $margin >= self::MIN_MARGIN_RATIO;

        $alternatives = [];
        foreach (array_slice($ranked, 0, 3) as $row) {
            $alternatives[] = ['id' => $row['chapter_id'], 'name' => $names[$row['chapter_id']] ?? '', 'score' => $row['score']];
        }

        $terms = implode(', ', $best['terms']);
        if ($auto) {
            $level = 'high';
            $reason = "Matches \"{$names[$best['chapter_id']]}\" on: {$terms}.";
        } elseif (!$contrast) {
            $level = 'weak';
            $reason = "Only one chapter exists for this subject, so there is nothing to compare it with; please confirm \"{$names[$best['chapter_id']]}\".";
        } elseif ($best['score'] >= self::MIN_SCORE) {
            $level = 'close';
            $runner = $names[$ranked[1]['chapter_id']] ?? 'another chapter';
            $reason = "Close between \"{$names[$best['chapter_id']]}\" and \"{$runner}\"; teacher confirmation needed.";
        } else {
            $level = 'weak';
            $reason = "Weak match with \"{$names[$best['chapter_id']]}\" ({$terms}); teacher confirmation needed.";
        }

        return [
            'chapter_id' => $auto ? $best['chapter_id'] : null,
            'suggested_id' => $best['chapter_id'],
            'suggested_name' => $names[$best['chapter_id']] ?? null,
            'score' => $best['score'],
            'margin' => $margin,
            'level' => $level,
            'confidence' => self::LEVEL_ACCURACY[$level],
            'auto' => $auto,
            'alternatives' => $alternatives,
            'reason' => $reason,
        ];
    }

    /** @param array<string, mixed> $item */
    public function textOf(array $item): string
    {
        $parts = [(string) ($item['question_text'] ?? '')];
        foreach (($item['options'] ?? []) as $option) {
            $parts[] = (string) ($option['text'] ?? '');
        }
        foreach (($item['pairs'] ?? []) as $pair) {
            $parts[] = ($pair['left'] ?? '') . ' ' . ($pair['right'] ?? '');
        }
        $parts[] = (string) ($item['model_answer'] ?? '');

        return implode(' ', $parts);
    }

    /** @return array{names: array<int, string>, scorer: ChapterTextScorer} */
    private function load(int $tenant, int $standardId, int $subjectId): array
    {
        $key = "{$tenant}:{$standardId}:{$subjectId}";
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        // ONLY the chapters of this school, standard and subject: never another subject's or school's chapters.
        // key_concepts and chapter_desc exist in the live schema; guard them like the other curriculum tables.
        $columns = array_values(array_filter(['id', 'chapter_name', 'key_concepts', 'chapter_desc'], fn ($c) => $c === 'id' || $c === 'chapter_name' || Schema::hasColumn('chapter_master', $c)));
        $chapters = DB::table('chapter_master')
            ->where('sub_institute_id', $tenant)->where('standard_id', $standardId)->where('subject_id', $subjectId)
            ->orderBy('id')->get($columns);

        $profiles = [];
        $names = [];
        foreach ($chapters as $chapter) {
            $names[(int) $chapter->id] = (string) $chapter->chapter_name;
            [$conceptNames, $conceptText] = $this->keyConcepts((string) ($chapter->key_concepts ?? ''));
            $profiles[(int) $chapter->id] = [
                'title' => (string) $chapter->chapter_name,
                'concepts' => $conceptNames,
                'description' => trim($conceptText . ' ' . (string) ($chapter->chapter_desc ?? '')),
                'topics' => '', 'outcomes' => '', 'semantic' => '',
            ];
        }

        $ids = array_keys($profiles);
        if ($ids !== []) {
            $this->append($profiles, 'topics', 'topic_master', ['name', 'description'], $ids);
            $this->append($profiles, 'concepts', 'lms_concept', ['name'], $ids);
            $this->append($profiles, 'description', 'lms_concept', ['description', 'definition'], $ids);
            $this->append($profiles, 'outcomes', 'lms_learning_outcomes', ['description'], $ids);
            $this->append($profiles, 'semantic', 'semantic_intelligence', ['learning_objective', 'knowledge', 'learning_outcomes'], $ids);
        }

        return $this->cache[$key] = ['names' => $names, 'scorer' => new ChapterTextScorer($profiles)];
    }

    /**
     * @param  array<int, array<string, string>>  $profiles
     * @param  list<string>  $columns
     * @param  list<int>  $ids
     */
    private function append(array &$profiles, string $field, string $table, array $columns, array $ids): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }
        foreach ($columns as $column) {
            if (!Schema::hasColumn($table, $column)) {
                return;
            }
        }
        foreach (DB::table($table)->whereIn('chapter_id', $ids)->get(array_merge(['chapter_id'], $columns)) as $row) {
            foreach ($columns as $column) {
                $profiles[(int) $row->chapter_id][$field] .= ' ' . (string) $row->{$column};
            }
        }
    }

    /** @return array{0: string, 1: string} concept names, and their descriptions */
    private function keyConcepts(string $raw): array
    {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return ['', $raw];
        }
        $names = '';
        $descriptions = '';
        foreach ($decoded as $concept) {
            if (is_array($concept)) {
                $names .= ' ' . ($concept['name'] ?? '');
                $descriptions .= ' ' . ($concept['description'] ?? '');
            } elseif (is_string($concept)) {
                $names .= ' ' . $concept;
            }
        }

        return [trim($names), trim($descriptions)];
    }
}
