<?php

namespace App\Services\StudyDeck\Documents;

/**
 * Works out, from the chapter's own data, where learners MAY find the chapter hard, and so which concepts a remedial
 * class should reteach.
 *
 * Nothing here is measured. There is no record of how a class actually did on a chapter, so every difficulty this
 * class names is a POTENTIAL one, inferred from what the chapter's data says about the concept: how it is rated, how
 * much thinking it asks for, how many misconceptions are listed for it, whether later ideas build on it, whether it
 * builds on an earlier one, and what kind of questions the bank asks about it. The reasons it gives for a concept are the
 * facts that raised its score, written as facts about the concept, never as claims about learners. The document says
 * so in its own words (NOTE).
 *
 * Pure: rows in, rows out. The same chapter always gives the same ranking, so a remedial class can be regenerated and
 * its lessons do not move.
 */
class GapAnalysis
{
    /** Printed above the table of potential difficulties, in both copies of the document. */
    public const NOTE = 'These are potential difficulties, worked out from what the chapter\'s own data says about each idea: how it is rated, the thinking it asks for, the misconceptions listed for it and what builds on it. They are not results from a class. The short check at the start shows where your learners need help.';

    /** Most lessons one topic contributes, so the lessons are not all about one corner of the chapter. */
    private const PER_TOPIC = 2;

    private const WORDS = [1 => 'One', 2 => 'Two', 3 => 'Three'];

    private const DIFFICULTY = ['easy' => 0, 'medium' => 2, 'moderate' => 2, 'hard' => 4, 'difficult' => 4];

    private const BLOOM = ['remember' => 0, 'understand' => 0, 'apply' => 1, 'analyze' => 2, 'analyse' => 2, 'evaluate' => 2, 'create' => 2];

    /**
     * How many concepts get a lesson: about a sixth of the chapter, never fewer than four (a class that teaches less than
     * that is not a class) nor more than seven (it would no longer fit a class), and never more than the chapter has.
     */
    public static function lessonCount(int $concepts): int
    {
        return min($concepts, max(4, min(7, (int) round($concepts / 6))));
    }

    /**
     * The same analysis with fewer lessons: the highest-ranked `$k` stay (in teaching order) and the others go back to the table
     * as ideas that may be hard. Used when a class must be shorter than it was first built.
     *
     * @param array{rows:array<int,array<string,mixed>>, focus:array<int,int>, focus_ranked:array<int,int>, others:array<int,int>} $analysis
     * @return array{rows:array<int,array<string,mixed>>, focus:array<int,int>, focus_ranked:array<int,int>, others:array<int,int>}
     */
    public static function withLessons(array $analysis, int $k): array
    {
        $keep = array_slice($analysis['focus_ranked'], 0, max(1, $k));
        $focus = array_values(array_filter($analysis['focus'], fn ($id) => in_array($id, $keep, true)));
        foreach ($analysis['rows'] as $id => $row) {
            if ($row['priority'] === 'higher' && !in_array($id, $focus, true)) {
                $analysis['rows'][$id]['priority'] = 'medium';
            }
        }

        return ['focus' => $focus, 'focus_ranked' => $keep] + $analysis;
    }

    /**
     * @param array<string,mixed> $map LearningPlanBuilder::build()
     * @param array<int,int> $scope the concepts of the document, in teaching order
     * @param array<int,array<int,array<string,mixed>>> $eligible concept_id => bank questions
     * @return array{rows:array<int,array<string,mixed>>, focus:array<int,int>, focus_ranked:array<int,int>, others:array<int,int>}
     *         rows by concept id (in teaching order), the concepts to reteach (in teaching order, and ranked), and the concepts that are only named
     */
    public function analyse(array $map, array $scope, array $eligible, ?int $lessons = null): array
    {
        $position = array_flip($scope);
        $rows = [];
        foreach ($scope as $id) {
            $rows[$id] = $this->row($map['concepts'][$id], $map, $scope, $eligible[$id] ?? []);
        }

        $want = $lessons ?? self::lessonCount(count($scope));
        $ranked = array_keys($rows);
        usort($ranked, fn ($a, $b) => [$rows[$b]['score'], $position[$a]] <=> [$rows[$a]['score'], $position[$b]]);

        // The highest scores first, but no more than PER_TOPIC from one topic while another topic still has a candidate.
        $focus = [];
        $perTopic = [];
        foreach ([self::PER_TOPIC, PHP_INT_MAX] as $cap) {
            foreach ($ranked as $id) {
                if (count($focus) >= $want) {
                    break 2;
                }
                $topic = (int) $rows[$id]['topic_id'];
                if (in_array($id, $focus, true) || ($perTopic[$topic] ?? 0) >= $cap || $rows[$id]['score'] <= 0) {
                    continue;
                }
                $focus[] = $id;
                $perTopic[$topic] = ($perTopic[$topic] ?? 0) + 1;
            }
        }
        // A chapter whose data rates nothing as hard still gets its lessons: the first concepts, which the rest build on.
        foreach ($scope as $id) {
            if (count($focus) >= $want) {
                break;
            }
            if (!in_array($id, $focus, true)) {
                $focus[] = $id;
            }
        }
        $picked = $focus;
        usort($focus, fn ($a, $b) => $position[$a] <=> $position[$b]);

        // Priority is the rank: the lessons are the higher band, the next sixth of the chapter the medium band.
        $medium = (int) ceil(count($scope) / 6);
        $rest = array_values(array_filter($ranked, fn ($id) => !in_array($id, $focus, true)));
        foreach ($rows as $id => $r) {
            $rows[$id]['priority'] = in_array($id, $focus, true) ? 'higher' : (array_search($id, $rest, true) < $medium && $r['score'] > 0 ? 'medium' : 'lower');
        }

        $others = array_values(array_filter($scope, fn ($id) => $rows[$id]['priority'] === 'lower'));

        return ['rows' => $rows, 'focus' => $focus, 'focus_ranked' => $picked, 'others' => $others];
    }

    /**
     * One concept: its score and the facts behind it.
     *
     * @param array<string,mixed> $c learning-map concept
     * @param array<int,int> $scope
     * @param array<int,array<string,mixed>> $questions
     * @return array<string,mixed>
     */
    private function row(array $c, array $map, array $scope, array $questions): array
    {
        $score = 0;
        $reasons = [];

        $difficulty = self::DIFFICULTY[strtolower((string) ($c['difficulty'] ?? ''))] ?? 0;
        if ($difficulty >= 2) {
            $score += $difficulty;
            $reasons[] = 'Rated ' . strtolower((string) $c['difficulty']) . ' in the chapter data.';
        }

        $dok = array_values(array_filter(array_map('intval', (array) ($c['dok'] ?? [])), fn ($d) => $d >= 1 && $d <= 4));
        $deepest = $dok === [] ? 1 : max($dok);
        $bloom = 0;
        foreach ((array) ($c['blooms'] ?? []) as $b) {
            $bloom = max($bloom, self::BLOOM[strtolower((string) $b)] ?? 0);
        }
        $thinking = min(3, ($deepest - 1) + $bloom);
        $score += $thinking;
        if ($deepest >= 3 || $bloom >= 2) {
            $reasons[] = 'Asks for reasoning, not only recall.';
        } elseif ($deepest >= 2 || $bloom >= 1) {
            $reasons[] = 'Asks learners to explain or use the idea.';
        }

        $misconceptions = count((array) ($c['misconceptions'] ?? []));
        if ($misconceptions > 0) {
            $score += min($misconceptions, 3);
            $reasons[] = ($misconceptions >= 3 ? 'Several misconceptions are' : self::WORDS[$misconceptions] . ' misconception' . ($misconceptions === 1 ? ' is' : 's are')) . ' listed.';
        }

        $builtOn = array_values(array_filter((array) ($c['required_by'] ?? []), fn ($r) => in_array($r, $scope, true)));
        if ($builtOn !== []) {
            $score += min(count($builtOn), 2);
            $reasons[] = 'Later ideas build on it.';
        }

        $needs = array_values(array_filter((array) ($c['requires'] ?? []), fn ($r) => isset($map['concepts'][$r])));
        if ($needs !== []) {
            $score += 1;
        }

        if ($questions !== []) {
            $harder = count(array_filter($questions, fn ($q) => in_array(strtolower((string) ($q['difficulty'] ?? '')), ['medium', 'moderate', 'hard', 'difficult'], true) || (self::BLOOM[strtolower((string) ($q['bloom'] ?? ''))] ?? 0) >= 1));
            $share = $harder / count($questions);
            $score += (int) round(2 * $share);
            if ($share >= 0.5) {
                $reasons[] = 'Most bank questions on it ask for more than recall.';
            }
        }

        return [
            'concept_id' => (int) $c['id'],
            'name' => (string) $c['name'],
            'topic_id' => (int) $c['topic_id'],
            'score' => $score,
            'reasons' => $reasons,
            'check_first' => array_map(fn ($r) => (string) $map['concepts'][$r]['name'], $needs),
            'priority' => 'lower',
        ];
    }
}
