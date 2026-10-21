<?php

namespace App\Services\CoursePresentation;

/**
 * Is a teaching method suitable for THIS chapter?
 *
 * Judged only from what the chapter's own semantic intelligence recommends: for each analysed concept it lists
 * teaching strategies. A method counts as suitable when enough concepts recommend one of its strategies
 * (config course_presentation_pilot.method_strategies / suitability). Pure: no database, no config reads
 * beyond the arguments' defaults.
 *
 * Verdicts:
 *   suitable      enough concepts recommend it
 *   not_suitable  it can be judged, and too few concepts recommend it
 *   unconfirmed   the analysis has no counterpart for this method, so it cannot be called suitable
 *   unknown       the chapter has no readable analysis at all
 */
class MethodSuitability
{
    /** @var array<string,string> key => label */
    private array $labels;
    /** @var array<string,array<int,string>> */
    private array $strategies;
    private int $minConcepts;
    private float $minShare;

    public function __construct(?array $labels = null, ?array $strategies = null, ?array $rule = null)
    {
        $this->labels = $labels ?? (array) config('course_presentation_pilot.pedagogies', []);
        $this->strategies = $strategies ?? (array) config('course_presentation_pilot.method_strategies', []);
        $rule ??= (array) config('course_presentation_pilot.suitability', []);
        $this->minConcepts = (int) ($rule['min_concepts'] ?? 2);
        $this->minShare = (float) ($rule['min_share'] ?? 0.2);
    }

    /**
     * @param array<string,mixed>|null $semanticRow the chapter's accepted semantic_intelligence row
     * @return array<string,array<string,mixed>> verdict per method key
     */
    public function evaluate(?array $semanticRow): array
    {
        $analysis = $this->analysis($semanticRow);
        if ($analysis === null) {
            $out = [];
            foreach ($this->labels as $key => $label) {
                $out[$key] = ['verdict' => 'unknown', 'concepts_recommending' => null, 'concepts_analysed' => null, 'recommendation' => null,
                    'message' => "{$label} cannot be checked: this chapter has no readable teaching analysis."];
            }

            return $out;
        }

        [$total, $perConcept] = $analysis; // $perConcept: list of sets of normalised strategy names
        $needed = max($this->minConcepts, (int) ceil($this->minShare * $total));

        $counts = [];
        foreach ($this->labels as $key => $label) {
            $mapped = array_map([$this, 'norm'], (array) ($this->strategies[$key] ?? []));
            $counts[$key] = $mapped === [] ? null : count(array_filter($perConcept, fn (array $set) => array_intersect($mapped, $set) !== []));
        }

        // The best-supported methods, for recommendations.
        $ranked = array_filter($counts, fn ($c) => $c !== null && $c >= $needed);
        arsort($ranked);

        $out = [];
        foreach ($this->labels as $key => $label) {
            $count = $counts[$key];
            $alternatives = array_slice(array_diff_key($ranked, [$key => true]), 0, 2, true);
            $recommendation = $alternatives ? array_map(fn ($k, $c) => ['key' => $k, 'label' => $this->labels[$k], 'concepts_recommending' => $c], array_keys($alternatives), array_values($alternatives)) : [];

            if ($count !== null && $count >= $needed) {
                $verdict = 'suitable';
                $message = "{$label} is a suitable fit for this chapter: the chapter's analysis recommends it for {$count} of its {$total} analysed concepts.";
                $recommendation = [];
            } elseif ($count !== null) {
                $verdict = 'not_suitable';
                $message = "{$label} is not a suitable fit for this chapter: the chapter's analysis recommends it for "
                    . ($count === 0 ? "none of its {$total} analysed concepts." : "only {$count} of its {$total} analysed concepts.");
            } else {
                $verdict = 'unconfirmed';
                $message = "{$label} is not confirmed as a suitable fit: the chapter's analysis does not evaluate this method, so it cannot be called suitable.";
            }
            if ($verdict !== 'suitable') {
                $message .= $recommendation
                    ? ' A more appropriate choice is ' . implode(' or ', array_map(fn ($r) => "{$r['label']} (recommended for {$r['concepts_recommending']} of {$total} concepts)", $recommendation)) . '.'
                    : ' The analysis strongly supports none of the other methods offered here.';
            }

            $out[$key] = ['verdict' => $verdict, 'concepts_recommending' => $count, 'concepts_analysed' => $total, 'recommendation' => $recommendation ?: null, 'message' => $message];
        }

        return $out;
    }

    /** @return array{0:int,1:array<int,array<int,string>>}|null total analysed concepts and each one's strategy set */
    private function analysis(?array $row): ?array
    {
        $decoded = json_decode((string) ($row['full_intelegance_json'] ?? ''), true);
        if (!is_array($decoded)) {
            return null;
        }
        $entries = (array) ($decoded['concepts'] ?? $decoded['intelligence']['concepts'] ?? []);
        $sets = [];
        foreach ($entries as $entry) {
            $set = [];
            foreach ((array) ($entry['pedagogy_recommendations'] ?? []) as $rec) {
                $name = $this->norm((string) ($rec['strategy'] ?? ''));
                if ($name !== '') {
                    $set[$name] = $name;
                }
            }
            $sets[] = array_values($set);
        }

        return $sets === [] ? null : [count($sets), $sets];
    }

    /** "Inquiry Based Teaching" and "inquiry-based learning" both become "inquiry". */
    private function norm(string $name): string
    {
        $words = preg_split('/[^a-z0-9]+/', strtolower($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode(' ', array_values(array_diff($words, ['based', 'teaching', 'learning'])));
    }
}
