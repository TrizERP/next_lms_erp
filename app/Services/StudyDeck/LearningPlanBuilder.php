<?php

namespace App\Services\StudyDeck;

/**
 * Turns the loaded chapter context into a LEARNING MAP: the teaching order,
 * who depends on whom, which questions are available and which instructional
 * patterns might suit each concept.
 *
 * It is deterministic and makes no model call. The map is what the slide
 * planner (Claude, stage 1) reasons over, so the plan is built from a
 * structured picture of the chapter and never from raw database rows.
 */
class LearningPlanBuilder
{
    public function __construct(private readonly H5pPatternSelector $patterns = new H5pPatternSelector())
    {
    }

    /**
     * @param array<string,mixed> $context ConceptContextBuilder::assemble()
     * @param array<int,array<int,array<string,mixed>>> $eligibleQuestions concept_id => questions
     * @return array<string,mixed>
     */
    public function build(array $context, array $eligibleQuestions, int $minSlides = 30, int $maxSlides = 35): array
    {
        $concepts = $context['concepts'];
        $warnings = [];

        // requires[A] = concepts that must come before A (in this chapter only).
        $requires = [];
        $dependents = [];
        foreach ($context['prerequisite_edges'] as $e) {
            if ($e['in_chapter'] && isset($concepts[$e['concept_id']])) {
                $requires[$e['concept_id']][] = $e['requires_id'];
                $dependents[$e['requires_id']][] = $e['concept_id'];
            }
        }

        $sequence = [];
        $topicOrder = [];
        foreach ($context['topics'] as $topic) {
            $ordered = $this->orderWithin($topic['concept_ids'], $requires);
            $topicOrder[] = ['id' => $topic['id'], 'name' => $topic['name'], 'concept_ids' => $ordered];
            array_push($sequence, ...$ordered);
        }

        // Concepts that belong to no topic still have to be taught.
        $loose = array_values(array_diff(array_keys($concepts), $sequence));
        if ($loose) {
            $warnings[] = count($loose) . ' concept(s) have no topic and are appended at the end.';
            array_push($sequence, ...$this->orderWithin($loose, $requires));
        }

        $position = array_flip($sequence);
        foreach ($requires as $concept => $needs) {
            foreach ($needs as $need) {
                if (($position[$need] ?? -1) > ($position[$concept] ?? 0)) {
                    $warnings[] = sprintf('"%s" needs "%s", which sits in a later topic.', $concepts[$concept]['name'], $concepts[$need]['name']);
                }
            }
        }

        $map = [];
        foreach ($sequence as $id) {
            $c = $concepts[$id];
            $related = [];
            foreach ($c['relationships'] as $r) {
                if ($r['target_id'] !== null && $r['target_id'] !== $id) {
                    $related[] = ['concept_id' => $r['target_id'], 'type' => $r['type']];
                }
            }
            $map[$id] = $c + [
                'requires' => array_values(array_unique($requires[$id] ?? [])),
                'required_by' => array_values(array_unique($dependents[$id] ?? [])),
                'related' => $related,
                'question_ids' => array_column($eligibleQuestions[$id] ?? [], 'id'),
                'pattern_candidates' => $this->patterns->candidatesFor($c, count($requires[$id] ?? [])),
            ];
        }

        return [
            'target_slides' => ['min' => $minSlides, 'max' => $maxSlides],
            'sequence' => $sequence,
            'topics' => $topicOrder,
            'concepts' => $map,
            'warnings' => $warnings,
        ];
    }

    /** Stable topological order: prerequisites first, ties broken by original position. */
    private function orderWithin(array $ids, array $requires): array
    {
        $ids = array_values($ids);
        $placed = [];
        $out = [];
        $guard = count($ids) + 1;

        while (count($out) < count($ids) && $guard-- > 0) {
            foreach ($ids as $id) {
                if (isset($placed[$id])) {
                    continue;
                }
                $waiting = array_filter($requires[$id] ?? [], fn ($r) => in_array($r, $ids, true) && !isset($placed[$r]) && $r !== $id);
                if (!$waiting) {
                    $placed[$id] = true;
                    $out[] = $id;
                }
            }
        }

        // A prerequisite cycle is a data fault; keep the rest rather than lose concepts.
        foreach ($ids as $id) {
            if (!isset($placed[$id])) {
                $out[] = $id;
            }
        }

        return $out;
    }
}
