<?php

namespace App\Services\StudyDeck\Documents;

use App\Services\StudyDeck\ActivityPlanner;

/**
 * Decides which EXISTING question-bank questions a study document uses, and how each is played.
 *
 * Pure: rows in, ids out. It never writes a question, never rewrites one and never invents one. The bank's
 * questions arrive already filtered by QuestionSelector (self-contained, a stored answer, a stored explanation),
 * so the only decisions made here are which of them go where and in what order:
 *
 *   revision notes  a few per concept, mixed in form and thinking level, for the "important questions" section
 *   remedial class  up to three per concept, easiest first, so practice gets harder step by step
 *   activities      a few per activity, taken from the concepts that activity covers, each used once in the document
 *
 * A question that is used is described by an ACTIVITY SPEC (ActivityPlanner): the H5P target the existing
 * native players will ask it as. That is the same spec the study deck stores, so the same player, the same
 * runtime check and the same printed form (`StudyDeckPdfRenderer::question`) serve both.
 */
class QuestionPlacement
{
    private const DIFFICULTY = ['easy' => 1, 'medium' => 2, 'moderate' => 2, 'hard' => 3, 'difficult' => 3];

    private const BLOOM = ['remember' => 1, 'understand' => 2, 'apply' => 3, 'analyze' => 4, 'evaluate' => 5, 'create' => 6];

    /** The labels a learner sees on a question: the study deck's, so one vocabulary serves every document. */
    public const LABELS = ActivityPlanner::LABELS;

    public function __construct(private readonly ActivityPlanner $planner = new ActivityPlanner())
    {
    }

    /**
     * Important questions for revision: at least one for every concept that has any, a second where there is room,
     * the harder concepts first. Within a concept, questions of different forms and thinking levels come first.
     *
     * @param array<int,array<int,array<string,mixed>>> $eligible concept_id => questions
     * @param array<int,int> $conceptIds the concepts in teaching order
     * @param array<int,array<string,mixed>> $concepts learning-map concepts by id (difficulty, dok)
     * @return array<int,array<int,array<string,mixed>>> concept_id => chosen questions
     */
    public function forRevision(array $eligible, array $conceptIds, array $concepts, int $perConcept = 2, int $cap = 40): array
    {
        $cap = max($cap, count($conceptIds));
        $out = [];
        $used = 0;

        // First pass: one question for every concept that has one.
        foreach ($conceptIds as $id) {
            $ordered = $this->varied($eligible[$id] ?? []);
            if ($ordered !== []) {
                $out[$id] = [$ordered[0]];
                $used++;
            }
        }

        // Second pass: a further question where the concept is the harder kind, then where it is not, until the cap.
        $second = array_values(array_filter($conceptIds, fn ($id) => count($this->varied($eligible[$id] ?? [])) > 1));
        usort($second, fn ($a, $b) => $this->weight($concepts[$b] ?? []) <=> $this->weight($concepts[$a] ?? []));
        foreach ($second as $id) {
            if ($used >= $cap || count($out[$id] ?? []) >= $perConcept) {
                continue;
            }
            $ordered = $this->varied($eligible[$id]);
            $out[$id][] = $ordered[1];
            $used++;
        }

        return $this->inOrder($out, $conceptIds);
    }

    /**
     * The questions a COMPACT revision pack may print, best first. A compact pack has room for only a few, so the
     * order is what makes them a representative few: one from every topic before a second from any, a concept not yet
     * asked about before one that has been, and within those the higher thinking level first. Only questions the pack
     * can print whole (`CompactRevisionPdfRenderer::fits`: choices, a stored answer and reason that fit the answer area)
     * are offered; the bank's text is never shortened or reworded to make one fit.
     *
     * @param array<int,array<int,array<string,mixed>>> $eligible concept_id => questions
     * @param array<int,int> $conceptIds the concepts in teaching order
     * @param array<int,array<string,mixed>> $concepts learning-map concepts by id (topic_id)
     * @return array<int,array<string,mixed>> questions, in the order a pack of any size should take them
     */
    public function forCompact(array $eligible, array $conceptIds, array $concepts): array
    {
        $byTopic = [];
        foreach ($conceptIds as $id) {
            foreach ($this->varied($eligible[$id] ?? []) as $q) {
                if (CompactRevisionPdfRenderer::fits($q)) {
                    $byTopic[(int) ($concepts[$id]['topic_id'] ?? 0)][] = $q;
                }
            }
        }

        $out = [];
        $asked = [];
        while ($byTopic !== []) {
            foreach ($byTopic as $topic => $list) {
                // Within the topic: a concept not asked about yet first, then the higher thinking level, then the lower id.
                usort($list, fn ($a, $b) => [isset($asked[$a['concept_id']]), -$this->bloom($a), $a['id']] <=> [isset($asked[$b['concept_id']]), -$this->bloom($b), $b['id']]);
                $q = array_shift($list);
                $out[] = $q;
                $asked[$q['concept_id']] = true;
                if ($list === []) {
                    unset($byTopic[$topic]);
                } else {
                    $byTopic[$topic] = $list;
                }
            }
        }

        return $out;
    }

    /**
     * The first `$n` of a priority-ordered list, grouped by concept in teaching order: the shape the assembler takes.
     *
     * @param array<int,array<string,mixed>> $ordered questions from forCompact()
     * @param array<int,int> $conceptIds
     * @return array<int,array<int,array<string,mixed>>> concept_id => questions
     */
    public function takeCompact(array $ordered, int $n, array $conceptIds, int $perConcept = 2): array
    {
        $out = [];
        $taken = 0;
        foreach ($ordered as $q) {
            if ($taken >= max(0, $n)) {
                break;
            }
            // No concept is asked about more often than its part allows (the validator's limit for the kind).
            if (count($out[(int) $q['concept_id']] ?? []) >= $perConcept) {
                continue;
            }
            $out[(int) $q['concept_id']][] = $q;
            $taken++;
        }

        return $this->inOrder($out, $conceptIds);
    }

    /** Longest stem, option, model answer and stored reason, in characters, a purpose-based document prints whole. */
    private const PRINT = ['stem' => 360, 'option' => 120, 'answer' => 640, 'why' => 520];

    /**
     * Can the question be printed whole on a page of a purpose-based document? The bank's text is never shortened or
     * reworded, so a question that would take a page of its own is simply not offered.
     *
     * @param array<string,mixed> $q a normalised bank question
     */
    public static function printable(array $q): bool
    {
        $options = (array) ($q['options'] ?? []);
        if ($options !== [] && (count($options) < 2 || count($options) > 6)) {
            return false;
        }
        foreach ($options as $o) {
            if (mb_strlen((string) $o['text']) > self::PRINT['option']) {
                return false;
            }
        }

        return mb_strlen(trim((string) ($q['stem'] ?? ''))) <= self::PRINT['stem']
            && mb_strlen((string) ($q['answer_text'] ?? '')) <= self::PRINT['answer']
            && mb_strlen((string) ($q['explanation'] ?? '')) <= self::PRINT['why'];
    }

    /**
     * The questions of a revision pack's "test yourself": few, spread over every topic, and exam-like. One from each topic
     * before a second from any, a concept not yet asked about before one that has been, the higher thinking level first,
     * and a different form before a repeat of one already taken. Revision is recall under exam conditions, so these are
     * the harder end of what the bank offers; a remedial class takes its questions from the other end (forRemedialClass).
     *
     * @param array<int,array<int,array<string,mixed>>> $eligible concept_id => questions
     * @param array<int,int> $conceptIds the concepts in teaching order
     * @param array<int,array<string,mixed>> $concepts learning-map concepts by id (topic_id)
     * @return array<int,array<string,mixed>> the chosen questions in teaching order
     */
    public function forCheck(array $eligible, array $conceptIds, array $concepts, int $n = 12): array
    {
        $position = array_flip($conceptIds);
        $byTopic = [];
        foreach ($conceptIds as $id) {
            foreach ($eligible[$id] ?? [] as $q) {
                if (self::printable($q)) {
                    $byTopic[(int) ($concepts[$id]['topic_id'] ?? 0)][] = $q;
                }
            }
        }

        $out = [];
        $asked = [];
        $forms = [];
        while ($byTopic !== [] && count($out) < $n) {
            foreach ($byTopic as $topic => $list) {
                if (count($out) >= $n) {
                    break;
                }
                usort($list, fn ($a, $b) => [isset($asked[$a['concept_id']]), $forms[$a['form']] ?? 0, -$this->bloom($a), $a['id']] <=> [isset($asked[$b['concept_id']]), $forms[$b['form']] ?? 0, -$this->bloom($b), $b['id']]);
                $q = array_shift($list);
                $out[] = $q;
                $asked[$q['concept_id']] = true;
                $forms[$q['form']] = ($forms[$q['form']] ?? 0) + 1;
                if ($list === []) {
                    unset($byTopic[$topic]);
                } else {
                    $byTopic[$topic] = $list;
                }
            }
        }
        usort($out, fn ($a, $b) => [$position[$a['concept_id']] ?? PHP_INT_MAX, $a['id']] <=> [$position[$b['concept_id']] ?? PHP_INT_MAX, $b['id']]);

        return $out;
    }

    /**
     * The bank questions of each stage of a remedial class, none used twice and none in `$exclude` (the revision notes').
     * A stage has its own job, so each takes a different slice of the bank:
     *
     *   guided       easiest first, for the concepts that get a lesson: the practice that goes with the lesson
     *   exit         one for each lesson concept, a step harder, then a second for the lessons that still have one up to `$exitMax`: did the lessons work?
     *   diagnostic   one for each topic, the plainest, preferring concepts that get no lesson: where does help start?
     *   independent  what is left of the lesson concepts' questions, middle difficulty first: practice without help
     *
     * Allocation is in that order of need (a lesson is never left without practice or an exit question), not in the
     * order the document prints them.
     *
     * @param array<int,array<int,array<string,mixed>>> $eligible concept_id => questions
     * @param array<int,int> $conceptIds the concepts in teaching order
     * @param array<int,array<string,mixed>> $concepts learning-map concepts by id (topic_id)
     * @param array<int,int> $focus the concepts that get a lesson, in teaching order
     * @param array<int,int> $exclude question ids this document must not use
     * @param int $exitMax the most questions the exit check holds
     * @return array{guided:array<int,array<int,array<string,mixed>>>, exit:array<int,array<string,mixed>>, diagnostic:array<int,array<string,mixed>>, independent:array<int,array<string,mixed>>}
     */
    public function forRemedialClass(array $eligible, array $conceptIds, array $concepts, array $focus, array $exclude = [], int $guided = 2, int $independent = 6, int $exitMax = 6): array
    {
        $used = array_fill_keys($exclude, true);
        $position = array_flip($conceptIds);
        $isFocus = array_flip($focus);
        $topicOf = fn (int $id) => (int) ($concepts[$id]['topic_id'] ?? 0);
        $take = function (array $q) use (&$used): array {
            $used[$q['id']] = true;

            return $q;
        };
        // A closure, not an arrow function: it must see the questions taken after it was made.
        $free = function (int $id) use ($eligible, &$used): array {
            return array_values(array_filter($eligible[$id] ?? [], fn ($q) => self::printable($q) && !isset($used[$q['id']])));
        };
        $plain = fn ($a, $b) => [$this->difficulty($a), $this->bloom($a), $a['id']] <=> [$this->difficulty($b), $this->bloom($b), $b['id']];

        $guide = [];
        foreach ($focus as $id) {
            $list = $free($id);
            usort($list, $plain);
            foreach (array_slice($list, 0, $guided) as $q) {
                $guide[$id][] = $take($q);
            }
        }

        // Diagnostic: the plainest question of each topic, a concept with no lesson before one with, a choice before a written
        // answer. Taken before the exit questions so that every topic is probed even where its lesson concept has few questions.
        $diagnostic = [];
        $topics = array_values(array_unique(array_map($topicOf, $conceptIds)));
        foreach ($topics as $topic) {
            $list = [];
            foreach ($conceptIds as $id) {
                if ($topicOf($id) === $topic) {
                    $list = array_merge($list, $free($id));
                }
            }
            usort($list, fn ($a, $b) => [isset($isFocus[$a['concept_id']]), empty($a['options']), $this->difficulty($a), $this->bloom($a), $a['id']] <=> [isset($isFocus[$b['concept_id']]), empty($b['options']), $this->difficulty($b), $this->bloom($b), $b['id']]);
            if ($list !== []) {
                $diagnostic[] = $take($list[0]);
            }
        }
        usort($diagnostic, fn ($a, $b) => [$position[$a['concept_id']] ?? PHP_INT_MAX, $a['id']] <=> [$position[$b['concept_id']] ?? PHP_INT_MAX, $b['id']]);

        // Exit: the lesson concept's own question first; a concept left without one borrows from its topic.
        $exit = [];
        foreach ($focus as $id) {
            $list = $free($id);
            if ($list === []) {
                foreach ($conceptIds as $other) {
                    if ($other !== $id && $topicOf($other) === $topicOf($id)) {
                        $list = array_merge($list, $free($other));
                    }
                }
            }
            usort($list, fn ($a, $b) => [abs($this->difficulty($a) - 2), -$this->bloom($a), $a['id']] <=> [abs($this->difficulty($b) - 2), -$this->bloom($b), $b['id']]);
            if ($list !== []) {
                $exit[] = $take($list[0]);
            }
        }
        // A readiness rule is only as good as the questions behind it: a second question for the lessons that still have one, a round
        // at a time, until the check has `$exitMax` (three questions would make "ready" mean "all three right").
        while (count($exit) < $exitMax) {
            $added = false;
            foreach ($focus as $id) {
                $list = $free($id);
                if ($list === [] || count($exit) >= $exitMax) {
                    continue;
                }
                usort($list, fn ($a, $b) => [abs($this->difficulty($a) - 2), -$this->bloom($a), $a['id']] <=> [abs($this->difficulty($b) - 2), -$this->bloom($b), $b['id']]);
                $exit[] = $take($list[0]);
                $added = true;
            }
            if (!$added) {
                break;
            }
        }
        usort($exit, fn ($a, $b) => [$position[$a['concept_id']] ?? PHP_INT_MAX, $a['id']] <=> [$position[$b['concept_id']] ?? PHP_INT_MAX, $b['id']]);

        // Independent: mixed practice over the topics the lessons belong to, a round at a time so no concept crowds out another.
        $lessonTopics = array_map($topicOf, $focus);
        $from = array_merge($focus, array_values(array_filter($conceptIds, fn ($id) => !isset($isFocus[$id]) && in_array($topicOf($id), $lessonTopics, true))));
        $own = [];
        foreach ($from as $id) {
            $list = $free($id);
            usort($list, fn ($a, $b) => [abs($this->difficulty($a) - 2), $this->bloom($a), $a['id']] <=> [abs($this->difficulty($b) - 2), $this->bloom($b), $b['id']]);
            $own[$id] = $list;
        }
        $alone = [];
        while (count($alone) < $independent && array_filter($own)) {
            foreach ($own as $id => $list) {
                if (count($alone) >= $independent) {
                    break;
                }
                if ($list !== []) {
                    $alone[] = $take(array_shift($list));
                    $own[$id] = $list;
                }
            }
        }
        usort($alone, fn ($a, $b) => [$position[$a['concept_id']] ?? PHP_INT_MAX, $a['id']] <=> [$position[$b['concept_id']] ?? PHP_INT_MAX, $b['id']]);

        return ['guided' => $guide, 'exit' => $exit, 'diagnostic' => $diagnostic, 'independent' => $alone];
    }

    /**
     * Practice that gets harder: up to `$perConcept` questions per concept, the easiest and plainest first. The
     * position in the returned list is the level the learner meets it at (1 = with the most help).
     *
     * @param array<int,array<int,array<string,mixed>>> $eligible concept_id => questions
     * @param array<int,int> $conceptIds
     * @return array<int,array<int,array<string,mixed>>> concept_id => questions, easiest first
     */
    public function forRemedial(array $eligible, array $conceptIds, int $perConcept = 3): array
    {
        $out = [];
        foreach ($conceptIds as $id) {
            $list = array_values($eligible[$id] ?? []);
            if ($list === []) {
                continue;
            }
            usort($list, fn ($a, $b) => [$this->difficulty($a), $this->bloom($a), $a['id']] <=> [$this->difficulty($b), $this->bloom($b), $b['id']]);
            $out[$id] = array_slice($list, 0, $perConcept);
        }

        return $out;
    }

    /**
     * Questions for one activity, taken from the concepts it covers and never used before in the document.
     *
     * @param array<int,array<int,array<string,mixed>>> $eligible concept_id => questions
     * @param array<int,int> $conceptIds the concepts the activity covers
     * @param array<int,bool> $used question ids already placed (updated)
     * @return array<int,array<string,mixed>>
     */
    public function forActivity(array $eligible, array $conceptIds, int $limit, array &$used): array
    {
        $pool = [];
        foreach ($conceptIds as $id) {
            foreach ($this->varied($eligible[$id] ?? []) as $rank => $q) {
                if (!isset($used[$q['id']])) {
                    $pool[] = [$rank, $q];
                }
            }
        }
        // Each concept's best question before any concept's second: every concept the activity covers is asked about.
        usort($pool, fn ($a, $b) => $a[0] <=> $b[0]);

        $out = [];
        foreach ($pool as [, $q]) {
            if (count($out) >= $limit) {
                break;
            }
            if (!isset($used[$q['id']])) {
                $used[$q['id']] = true;
                $out[] = $q;
            }
        }

        return $out;
    }

    /**
     * How each chosen question is played: the study deck's own activity spec, one per question.
     *
     * @param array<int,array<string,mixed>> $questions
     * @param array<int,array<string,mixed>> $concepts learning-map concepts by id
     * @return array<int,array<string,mixed>>
     */
    public function specs(array $questions, array $concepts, ?int $level, bool $recall = false): array
    {
        $out = [];
        foreach (array_values($questions) as $index => $q) {
            $pattern = $recall ? $this->recallPattern($q) : null;
            $stub = [
                'slide_type' => 'practice',
                'concept_ids' => [$q['concept_id']],
                'relationship' => null,
                'h5p_pattern' => $pattern !== null ? ['type' => $pattern, 'reason' => 'a recall question on a term or a short fact suits this form'] : null,
            ];
            $spec = $this->planner->plan($stub, [$q], $concepts, $level)[0];
            // The label rotates through the vocabulary when the bank says nothing about the thinking level, so a
            // list of questions does not read as one prompt repeated.
            $spec['label'] = $this->planner->label($q['bloom'], $q['form'], false, $index);
            $out[] = $spec;
        }

        return $out;
    }

    /** A recall question is asked as a card; a short factual answer as a blank to fill. Otherwise its own form. */
    private function recallPattern(array $q): ?string
    {
        if (!in_array($q['form'], ['short_answer', 'very_short_answer'], true)) {
            return null;
        }
        if (($q['bloom'] ?: 'remember') === 'remember') {
            return 'flashcards';
        }

        return null;
    }

    /**
     * The questions of one concept with the most different ones first: a different form or thinking level than
     * those already taken comes before another of the same.
     *
     * @param array<int,array<string,mixed>> $list
     * @return array<int,array<string,mixed>>
     */
    private function varied(array $list): array
    {
        $list = array_values($list);
        usort($list, fn ($a, $b) => [$this->bloom($b), $a['id']] <=> [$this->bloom($a), $b['id']]);
        $out = [];
        $seen = [];
        foreach ($list as $q) {
            $key = $q['form'] . '|' . $q['bloom'];
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $out[] = $q;
            }
        }
        foreach ($list as $q) {
            if (!in_array($q, $out, true)) {
                $out[] = $q;
            }
        }

        return $out;
    }

    /** @param array<string,mixed> $concept */
    private function weight(array $concept): int
    {
        $difficulty = self::DIFFICULTY[strtolower((string) ($concept['difficulty'] ?? ''))] ?? 2;
        $dok = $concept['dok'] ?? [];

        return $difficulty * 10 + (int) (is_array($dok) && $dok !== [] ? max($dok) : 0);
    }

    private function difficulty(array $q): int
    {
        return self::DIFFICULTY[strtolower((string) ($q['difficulty'] ?? ''))] ?? 2;
    }

    private function bloom(array $q): int
    {
        return self::BLOOM[strtolower((string) ($q['bloom'] ?? ''))] ?? 2;
    }

    /**
     * @param array<int,array<int,array<string,mixed>>> $placed
     * @param array<int,int> $order
     * @return array<int,array<int,array<string,mixed>>>
     */
    private function inOrder(array $placed, array $order): array
    {
        $out = [];
        foreach ($order as $id) {
            if (isset($placed[$id])) {
                $out[$id] = $placed[$id];
            }
        }

        return $out;
    }
}
