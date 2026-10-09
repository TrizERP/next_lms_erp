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
