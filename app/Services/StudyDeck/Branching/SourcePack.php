<?php

namespace App\Services\StudyDeck\Branching;

use App\Services\StudyDeck\H5pPatternSelector;

/**
 * What one branching scenario is written FROM, and nothing else.
 *
 * Built from the output of ConceptContextBuilder::assemble() -- the same
 * read-only chapter / topic / concept / concept-intelligence context the study
 * deck and the study documents use -- plus the bank questions QuestionSelector
 * found self-contained. It adds three things the other pipelines do not need:
 *
 *   - CHOOSING: a scenario is about a handful of concepts, not the chapter. The
 *     teacher may name topics or concepts; otherwise the ones that suit a
 *     decision best are picked (a consequence to show, a misconception to
 *     confront, a relationship to follow), then put in teaching order.
 *   - EXCERPTS: the paragraphs of the chapter text that talk about the chosen
 *     concepts, so the model reads the book and not its own memory.
 *   - RENDERING: the pack as the text the planner and the writer are given.
 *
 * PURE: arrays in, arrays and strings out. No database, no model.
 */
final class SourcePack
{
    /** Most concepts one scenario may rest on. More than this is a chapter summary, not a decision. */
    public const MAX_CONCEPTS = 8;

    /** Concepts picked automatically, by length of scenario. */
    private const AUTO_COUNT = ['short' => 3, 'standard' => 5, 'extended' => 7];

    /** @var array<int,array<string,mixed>> concept id => concept context, in teaching order */
    public array $concepts = [];

    /** @var array<int,array<int,array<string,mixed>>> concept id => eligible bank questions */
    public array $questions = [];

    /** @param array<string,mixed> $context ConceptContextBuilder::assemble() */
    public function __construct(
        public readonly array $context,
        public readonly string $length = 'standard',
    ) {
    }

    /**
     * @param array<string,mixed> $context ConceptContextBuilder::assemble()
     * @param array<string,mixed> $options topic_ids, concept_ids, length, use_questions
     * @param array<int,array<int,array<string,mixed>>> $eligible QuestionSelector::select()['eligible']
     *
     * @throws \InvalidArgumentException when what was asked for cannot be built
     */
    public static function choose(array $context, array $options, array $eligible): self
    {
        $length = (string) ($options['length'] ?? 'standard');
        if (! isset(self::AUTO_COUNT[$length])) {
            $length = 'standard';
        }
        $pack = new self($context, $length);

        $all = (array) $context['concepts'];
        $asked = array_values(array_unique(array_map('intval', (array) ($options['concept_ids'] ?? []))));
        $topics = array_values(array_unique(array_map('intval', (array) ($options['topic_ids'] ?? []))));

        if ($asked !== []) {
            $chosen = array_values(array_filter($asked, fn ($id) => isset($all[$id])));
            if ($chosen === []) {
                throw new \InvalidArgumentException('None of the chosen concepts belong to this chapter.');
            }
        } elseif ($topics !== []) {
            $chosen = array_keys(array_filter($all, fn ($c) => in_array((int) $c['topic_id'], $topics, true)));
            if ($chosen === []) {
                throw new \InvalidArgumentException('The chosen topics have no concepts in this chapter.');
            }
            if (count($chosen) > self::MAX_CONCEPTS) {
                $chosen = array_slice(self::ranked($chosen, $all, $eligible, $context), 0, self::MAX_CONCEPTS);
            }
        } else {
            $chosen = array_slice(self::ranked(array_keys($all), $all, $eligible, $context), 0, self::AUTO_COUNT[$length]);
        }

        if (count($chosen) > self::MAX_CONCEPTS) {
            throw new \InvalidArgumentException(sprintf(
                'A scenario can rest on at most %d concepts; %d were chosen. Choose fewer, or a topic.',
                self::MAX_CONCEPTS,
                count($chosen)
            ));
        }

        foreach (self::teachingOrder($chosen, (array) $context['prerequisite_edges']) as $id) {
            $pack->concepts[$id] = $all[$id];
            if (! empty($options['use_questions'] ?? true) && ! empty($eligible[$id])) {
                $pack->questions[$id] = $eligible[$id];
            }
        }

        return $pack;
    }

    /**
     * Concept ids, most suited to a decision first.
     *
     * Advisory signals only, in the same spirit as H5pPatternSelector: a concept
     * with a consequence to show, a misconception to confront or a relationship
     * to follow makes a decision worth making; a bare definition does not.
     *
     * @param array<int,int> $ids
     * @param array<int,array<string,mixed>> $all
     * @param array<int,array<int,array<string,mixed>>> $eligible
     * @param array<string,mixed> $context
     * @return array<int,int>
     */
    public static function ranked(array $ids, array $all, array $eligible, array $context): array
    {
        $score = self::scores($ids, $all, $eligible, $context);

        // Ties keep chapter order, so the same chapter gives the same pick.
        $order = array_flip($ids);
        usort($ids, fn ($a, $b) => [$score[$b], $order[$a]] <=> [$score[$a], $order[$b]]);

        return $ids;
    }

    /**
     * How well each concept suits a decision-based lesson, 0 to 10.
     *
     * @param array<int,int> $ids
     * @param array<int,array<string,mixed>> $all
     * @param array<int,array<int,array<string,mixed>>> $eligible
     * @param array<string,mixed> $context
     * @return array<int,int> concept id => score
     */
    public static function scores(array $ids, array $all, array $eligible, array $context): array
    {
        $patterns = new H5pPatternSelector();
        $prereqCount = [];
        foreach ((array) $context['prerequisite_edges'] as $edge) {
            if (! empty($edge['in_chapter'])) {
                $prereqCount[(int) $edge['concept_id']] = ($prereqCount[(int) $edge['concept_id']] ?? 0) + 1;
            }
        }

        $score = [];
        foreach ($ids as $id) {
            $c = $all[$id];
            $s = 0;
            $s += in_array('branching', $patterns->candidatesFor($c, $prereqCount[$id] ?? 0), true) ? 3 : 0;
            $s += ! empty($c['misconceptions']) ? 2 : 0;
            $s += ! empty($c['real_world']) ? 2 : 0;
            $s += array_intersect(['apply', 'analyze', 'evaluate', 'create'], (array) ($c['blooms'] ?? [])) !== [] ? 1 : 0;
            $s += ! empty($eligible[$id]) ? 1 : 0;
            // A concept the intelligence records know nothing about has little
            // to build a consequence from.
            $s += ! empty($c['knowledge']) || ! empty($c['objectives']) ? 1 : 0;
            $score[$id] = $s;
        }

        return $score;
    }

    /**
     * Prerequisites before the concepts that need them; chapter order otherwise.
     *
     * @param array<int,int> $ids
     * @param array<int,array<string,mixed>> $edges
     * @return array<int,int>
     */
    public static function teachingOrder(array $ids, array $edges): array
    {
        sort($ids);
        $needs = [];
        foreach ($edges as $edge) {
            $c = (int) $edge['concept_id'];
            $p = (int) $edge['requires_id'];
            if ($c !== $p && in_array($c, $ids, true) && in_array($p, $ids, true)) {
                $needs[$c][] = $p;
            }
        }

        $out = [];
        $placed = [];
        while (count($out) < count($ids)) {
            $progress = false;
            foreach ($ids as $id) {
                if (isset($placed[$id])) {
                    continue;
                }
                if (array_diff($needs[$id] ?? [], array_keys($placed)) === []) {
                    $out[] = $id;
                    $placed[$id] = true;
                    $progress = true;
                }
            }
            if (! $progress) {
                // A prerequisite cycle in the data: keep what is left in chapter order.
                foreach ($ids as $id) {
                    if (! isset($placed[$id])) {
                        $out[] = $id;
                        $placed[$id] = true;
                    }
                }
            }
        }

        return $out;
    }

    /** @return array<int,int> */
    public function conceptIds(): array
    {
        return array_keys($this->concepts);
    }

    /** @return array<int,array<string,mixed>> question id => question */
    public function questionsById(): array
    {
        $out = [];
        foreach ($this->questions as $list) {
            foreach ($list as $q) {
                $out[(int) $q['id']] = $q;
            }
        }

        return $out;
    }

    /** "Standard 9" -> 9, or null. */
    public function level(): ?int
    {
        return preg_match('/\d+/', (string) ($this->context['chapter']['standard_name'] ?? ''), $m) ? (int) $m[0] : null;
    }

    /** The chapter, subject and class, as one line. */
    public function heading(): string
    {
        $c = $this->context['chapter'];

        return sprintf('%s, %s, chapter "%s"', $c['standard_name'], $c['subject_name'], $c['chapter_name']);
    }

    /** Names of the chosen concepts' topics, for the prompt. */
    private function topicName(?int $id): string
    {
        return $id !== null ? (string) ($this->context['topics'][$id]['name'] ?? '') : '';
    }

    /**
     * The chosen concepts as the text a model is given.
     *
     * Only what the records say: a field that is empty is left out, never
     * filled in. The ids are what the model must cite back, so every
     * statement it makes can be traced to a concept.
     */
    public function conceptsText(): string
    {
        $edges = (array) $this->context['prerequisite_edges'];
        $names = array_map(fn ($c) => $c['name'], $this->context['concepts']);
        $blocks = [];

        foreach ($this->concepts as $id => $c) {
            $lines = ['[concept ' . $id . '] ' . $c['name'] . ($this->topicName($c['topic_id']) !== '' ? ' (topic: ' . $this->topicName($c['topic_id']) . ')' : '')];

            if (! empty($c['definition'])) {
                $lines[] = '  definition: ' . self::flat((string) $c['definition']);
            }
            foreach (['objectives' => 'learning objectives', 'knowledge' => 'key facts', 'terms' => 'terms', 'real_world' => 'real-world uses'] as $key => $label) {
                if (! empty($c[$key])) {
                    $lines[] = '  ' . $label . ': ' . implode(' | ', array_map([self::class, 'flat'], (array) $c[$key]));
                }
            }
            foreach ((array) $c['misconceptions'] as $m) {
                $lines[] = '  common wrong idea: "' . self::flat((string) $m['wrong_idea']) . '"'
                    . (! empty($m['correction']) ? ' -> correct idea: ' . self::flat((string) $m['correction']) : '');
            }
            foreach ($edges as $edge) {
                if ((int) $edge['concept_id'] === $id && isset($names[(int) $edge['requires_id']]) && ! empty($edge['in_chapter'])) {
                    $lines[] = '  builds on: ' . $names[(int) $edge['requires_id']] . (! empty($edge['reason']) ? ' (' . self::flat((string) $edge['reason']) . ')' : '');
                }
            }
            foreach ((array) $c['relationships'] as $rel) {
                if (! empty($rel['target'])) {
                    $lines[] = '  ' . str_replace('_', ' ', (string) $rel['type']) . ': ' . $rel['target'];
                }
            }

            $blocks[] = implode("\n", $lines);
        }

        return implode("\n\n", $blocks);
    }

    /** The reusable bank questions, as the planner is offered them. */
    public function questionsText(): string
    {
        if ($this->questions === []) {
            return '(none)';
        }

        $blocks = [];
        foreach ($this->questions as $conceptId => $list) {
            foreach ($list as $q) {
                $options = implode(' / ', array_map(fn ($o) => $o['label'] . ') ' . self::flat($o['text']), $q['options']));
                $blocks[] = sprintf(
                    '[question %d] concept %d, %s: "%s" Options: %s Correct: %s',
                    $q['id'],
                    $conceptId,
                    $q['form'],
                    self::flat($q['stem']),
                    $options,
                    $q['correct_label']
                );
            }
        }

        return implode("\n", $blocks);
    }

    /**
     * The paragraphs of the chapter text that talk about the given concepts.
     *
     * Scored by how many of a concept's name and terms a paragraph mentions,
     * best first up to $budget characters, then put back in book order so the
     * model reads them as the author wrote them.
     *
     * @param array<int,int> $conceptIds
     */
    public function excerpts(array $conceptIds, int $budget = 7000): string
    {
        $text = (string) $this->context['ground_truth'];
        $paragraphs = array_values(array_filter(array_map('trim', preg_split('/\R\s*\R/u', $text) ?: []), fn ($p) => mb_strlen($p) >= 40));
        if ($paragraphs === []) {
            return '';
        }

        // Matched on stems, so "models" finds "model" and "theories" finds
        // "theory": a concept is named in the plural as often as the text uses
        // the singular.
        $phrases = [];
        foreach ($conceptIds as $id) {
            $c = $this->concepts[$id] ?? null;
            if ($c === null) {
                continue;
            }
            $phrases[self::stemmed((string) $c['name'])] = 5;
            foreach ((array) $c['terms'] as $term) {
                $stem = self::stemmed((string) $term);
                if (mb_strlen($stem) >= 4) {
                    $phrases[$stem] = max($phrases[$stem] ?? 0, 3);
                }
            }
            foreach (preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower((string) $c['name'])) ?: [] as $word) {
                if (mb_strlen($word) >= 5) {
                    $stem = self::stemmed($word);
                    $phrases[$stem] = ($phrases[$stem] ?? 0) + 1;
                }
            }
        }
        unset($phrases['']);

        $scored = [];
        foreach ($paragraphs as $i => $p) {
            $haystack = ' ' . self::stemmed($p) . ' ';
            $s = 0;
            foreach ($phrases as $phrase => $weight) {
                if (str_contains($haystack, ' ' . $phrase . ' ')) {
                    $s += $weight;
                }
            }
            if ($s > 0) {
                $scored[$i] = $s;
            }
        }
        arsort($scored);

        $picked = [];
        $used = 0;
        foreach (array_keys($scored) as $i) {
            $p = mb_strlen($paragraphs[$i]) > 900 ? mb_substr($paragraphs[$i], 0, 900) . '...' : $paragraphs[$i];
            if ($used + mb_strlen($p) > $budget) {
                continue;
            }
            $picked[$i] = $p;
            $used += mb_strlen($p);
        }
        ksort($picked);

        return implode("\n\n", $picked);
    }

    /**
     * Words reduced to a crude stem and joined by single spaces.
     *
     * Not linguistics: just enough that plural, past and -ing forms of a word
     * compare equal ("ignores", "ignoring", "ignore" -> "ignor"). It is only ever
     * used to MATCH text, never shown.
     */
    public static function stemmed(string $text): string
    {
        $out = [];
        foreach (preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text)) ?: [] as $w) {
            if ($w === '') {
                continue;
            }
            $len = mb_strlen($w);
            $w = match (true) {
                $len > 4 && str_ends_with($w, 'ies') => mb_substr($w, 0, -3) . 'y',
                $len > 5 && str_ends_with($w, 'ing') => mb_substr($w, 0, -3),
                $len > 4 && str_ends_with($w, 'ed') => mb_substr($w, 0, -2),
                $len > 4 && str_ends_with($w, 'es') => mb_substr($w, 0, -2),
                $len > 3 && str_ends_with($w, 's') && ! str_ends_with($w, 'ss') => mb_substr($w, 0, -1),
                default => $w,
            };
            if (mb_strlen($w) > 4 && str_ends_with($w, 'e')) {
                $w = mb_substr($w, 0, -1);
            }
            $out[] = $w;
        }

        return implode(' ', $out);
    }

    /** One line, whitespace collapsed, no markup. */
    public static function flat(string $text): string
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
