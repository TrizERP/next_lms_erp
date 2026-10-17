<?php

namespace App\Services\Ceds;

use InvalidArgumentException;

/**
 * Turns a teaching method (its CEDS phases) and a topic of a chapter into a timed lesson flow, and
 * lines up the STORED resources that can support each phase.
 *
 * PURE. It is handed the already-read sidecars of the stored deck, activities document and revision
 * notes, plus the topic's lab rows. It reads nothing, writes nothing and changes no resource: nothing
 * is shortened, reordered or rewritten to fit a phase. Where a stored resource is longer than its phase,
 * the answer is a warning.
 *
 * A resource is attached to a phase only through an id that can be checked:
 *   - a deck slide: its concept ids intersect the topic's concept ids (the hook slide by its type)
 *   - an activity or a revision-note section: its topic_id is the topic, or its concept ids are the topic's
 *   - a lab: its topic_id is the topic (labs in review stay "Pending Review")
 *   - bank questions: the question ids the topic's own slides already carry
 * Which kinds of resource may support which phase of which method is config (`phase_roles`); a phase
 * with no stored counterpart, or with nothing found, is returned as teacher-led. Nothing is guessed.
 */
class LessonFlowBuilder
{
    public const SLIDE_ROLES = [
        'explain' => ['concept_intro', 'concept_visual', 'relationship'],
        'worked' => ['worked_example'],
        'applied' => ['scenario', 'application'],
        'misconception' => ['misconception'],
    ];

    /** @var array<string,mixed> */
    private array $config;

    /** @param array<string,mixed>|null $config defaults to config('ceds_fit_matrix') */
    public function __construct(private CedsFitService $fit, ?array $config = null)
    {
        $this->config = $config ?? (array) config('ceds_fit_matrix', []);
    }

    /**
     * Suggested minutes for each phase of a method, summing to the period. A phase with no share of the
     * period (a flipped lesson's pre-class input) is 0 and is outside the period.
     *
     * @return list<int>
     */
    public function defaultMinutes(string $pedagogy, int $period): array
    {
        $weights = array_map(fn (array $p) => (int) $p[1], $this->fit->phases($pedagogy));
        $total = array_sum($weights) ?: 1;
        $exact = array_map(fn (int $w) => $period * $w / $total, $weights);
        $minutes = array_map(fn (float $x) => (int) floor($x), $exact);

        // Largest remainder first, earliest phase first on a tie, so the minutes always sum to the period.
        $left = $period - array_sum($minutes);
        $order = array_keys($exact);
        usort($order, fn (int $a, int $b) => [$exact[$b] - floor($exact[$b]), $a] <=> [$exact[$a] - floor($exact[$a]), $b]);
        foreach ($order as $i) {
            if ($left <= 0) {
                break;
            }
            if ($weights[$i] > 0) {
                $minutes[$i]++;
                $left--;
            }
        }

        return $minutes;
    }

    /**
     * @param array<string,mixed> $material deck, deck_id, activities, activities_id, notes, notes_id, labs, unreadable
     * @param list<int>|null $minutes the teacher's own minutes per phase (null = the suggested ones)
     * @return array<string,mixed>
     */
    public function build(string $pedagogy, int $topicId, int $period, array $material, ?array $minutes = null): array
    {
        $phases = $this->fit->phases($pedagogy);
        $roles = $this->config['phase_roles'][$pedagogy] ?? null;
        if ($roles === null || count($roles) !== count($phases)) {
            throw new InvalidArgumentException("No phase roles defined for '$pedagogy'.");
        }
        $suggested = $this->defaultMinutes($pedagogy, $period);
        if ($minutes !== null && count($minutes) !== count($phases)) {
            throw new InvalidArgumentException('One value of minutes is needed for every phase.');
        }
        $planned = $minutes ?? $suggested;

        $pool = $this->pool($topicId, $material);
        $out = [];
        foreach ($phases as $i => [$name, $weight]) {
            $outside = (int) $weight === 0;
            $resources = [];
            foreach ($roles[$i] as $role) {
                array_push($resources, ...$this->resourcesFor($role, $pool));
            }
            $warnings = $outside ? [] : $this->overruns($resources, (int) $planned[$i]);

            $out[] = [
                'index' => $i + 1,
                'name' => $name,
                'minutes' => $outside ? 0 : (int) $planned[$i],
                'suggested_minutes' => $suggested[$i],
                'outside_period' => $outside,
                'resources' => $resources,
                'teacher_led' => $resources === [],
                'teacher_led_reason' => $resources !== [] ? null : ($roles[$i] === [] ? 'no_stored_equivalent' : ($pool['unreadable'] ? 'resource_unreadable' : 'none_found')),
                'warnings' => $warnings,
            ];
        }

        $sum = array_sum(array_column($out, 'minutes'));
        $flowWarnings = [];
        if ($sum !== $period) {
            $flowWarnings[] = ['code' => 'period_mismatch', 'message' => "The phases add up to $sum minutes, not the $period-minute period."];
        }
        foreach ($pool['unreadable'] as $what) {
            $flowWarnings[] = ['code' => 'unreadable', 'message' => "The stored $what could not be read, so nothing from it is shown."];
        }

        return [
            'pedagogy' => $pedagogy,
            'pedagogy_label' => $this->fit->pedagogies()[$pedagogy],
            'topic_id' => $topicId,
            'period_minutes' => $period,
            'planned_minutes' => $sum,
            'phases' => $out,
            'warnings' => $flowWarnings,
            'deck_content_id' => $material['deck_id'] ?? null,
        ];
    }

    /**
     * The topics that have anything stored for them: id => counts of what is attached.
     *
     * @param array<string,mixed> $material
     * @return list<array{id:int,concept_ids:list<int>,slides:int,activities:int,notes:int,labs:int}>
     */
    public function topics(array $material): array
    {
        $ids = [];
        foreach ((array) ($material['deck']['concepts'] ?? []) as $c) {
            $ids[(int) $c['topic_id']] = true;
        }
        foreach ([(array) ($material['activities']['sections'] ?? []), (array) ($material['notes']['sections'] ?? [])] as $sections) {
            foreach ($sections as $s) {
                if (!empty($s['topic_id'])) {
                    $ids[(int) $s['topic_id']] = true;
                }
            }
        }
        foreach ((array) ($material['labs'] ?? []) as $l) {
            if (!empty($l['topic_id'])) {
                $ids[(int) $l['topic_id']] = true;
            }
        }
        ksort($ids);

        $topics = [];
        foreach (array_keys($ids) as $id) {
            $pool = $this->pool($id, $material);
            $topics[] = [
                'id' => $id,
                'concept_ids' => $pool['concepts'],
                'slides' => count($pool['slides']),
                'activities' => count($pool['activities']),
                'notes' => count($pool['notes']),
                'labs' => count($pool['labs']),
            ];
        }

        return $topics;
    }

    // ----------------------------------------------------------------------------------------------

    /**
     * Everything stored that is verifiably this topic's.
     *
     * @param array<string,mixed> $material
     * @return array<string,mixed>
     */
    private function pool(int $topicId, array $material): array
    {
        $deck = $material['deck'] ?? null;
        $concepts = [];
        foreach ((array) ($deck['concepts'] ?? []) as $c) {
            if ((int) $c['topic_id'] === $topicId) {
                $concepts[] = (int) $c['id'];
            }
        }

        $slides = [];
        $hook = null;
        foreach ((array) ($deck['slides'] ?? []) as $s) {
            if (($s['slide_type'] ?? '') === 'hook' && $hook === null) {
                $hook = $s;
            }
            if ($concepts !== [] && array_intersect($concepts, array_map('intval', (array) ($s['concept_ids'] ?? [])))) {
                $slides[] = $s;
            }
        }

        $belongs = fn (array $s) => (int) ($s['topic_id'] ?? 0) === $topicId
            || ($concepts !== [] && array_intersect($concepts, array_map('intval', (array) ($s['concept_ids'] ?? []))));
        $activities = array_values(array_filter((array) ($material['activities']['sections'] ?? []), fn ($s) => ($s['type'] ?? '') === 'activity' && $belongs($s)));
        $notes = array_values(array_filter((array) ($material['notes']['sections'] ?? []), fn ($s) => ($s['type'] ?? '') === 'note' && $belongs($s)));
        $labs = array_values(array_filter((array) ($material['labs'] ?? []), fn ($l) => (int) ($l['topic_id'] ?? 0) === $topicId));

        return [
            'material' => $material,
            'concepts' => $concepts,
            'slides' => $slides,
            'hook' => $hook,
            'activities' => $activities,
            'notes' => $notes,
            'labs' => $labs,
            'unreadable' => array_values((array) ($material['unreadable'] ?? [])),
        ];
    }

    /**
     * @param array<string,mixed> $pool
     * @return list<array<string,mixed>>
     */
    private function resourcesFor(string $role, array $pool): array
    {
        $m = $pool['material'];
        $deckId = $m['deck_id'] ?? null;
        $docs = fn (string $key) => $m[$key . '_id'] ?? null;
        $out = [];

        if (isset(self::SLIDE_ROLES[$role]) && $deckId !== null) {
            foreach ($pool['slides'] as $s) {
                if (in_array($s['slide_type'] ?? '', self::SLIDE_ROLES[$role], true)) {
                    $out[] = $this->slide($s, $deckId, 'topic');
                }
            }
        } elseif ($role === 'hook' && $deckId !== null && $pool['hook'] !== null) {
            $out[] = $this->slide($pool['hook'], $deckId, 'chapter');
        } elseif ($role === 'activity') {
            foreach ($pool['activities'] as $a) {
                $out[] = ['type' => 'activity', 'title' => (string) ($a['title'] ?? ''), 'section' => (int) ($a['n'] ?? 0), 'content_id' => $docs('activities'),
                    'stored_minutes' => isset($a['content']['minutes']) ? (int) $a['content']['minutes'] : null,
                    'format' => $a['content']['format'] ?? null, 'grouping' => $a['content']['grouping'] ?? null,
                    'status' => 'available', 'review_label' => null];
            }
            foreach ($pool['labs'] as $l) {
                $review = ($l['status'] ?? '') === 'review';
                $out[] = ['type' => 'lab', 'title' => (string) ($l['title'] ?? ''), 'id' => (int) $l['id'], 'stored_minutes' => null,
                    'status' => $review ? ResourceResolver::PENDING_REVIEW : ResourceResolver::AVAILABLE,
                    'review_label' => $review ? ResourceResolver::PENDING_REVIEW_LABEL : null];
            }
        } elseif ($role === 'questions' && $deckId !== null) {
            $ids = [];
            $on = [];
            foreach ($pool['slides'] as $s) {
                foreach ((array) ($s['question_ids'] ?? []) as $q) {
                    $ids[(int) $q] = true;
                    $on[(int) $s['n']] = true;
                }
            }
            if ($ids !== []) {
                $out[] = ['type' => 'questions', 'question_ids' => array_keys($ids), 'on_slides' => array_keys($on), 'content_id' => $deckId,
                    'title' => count($ids) . ' bank question' . (count($ids) === 1 ? '' : 's') . ' already on the slides',
                    'stored_minutes' => null, 'status' => 'available', 'review_label' => null];
            }
        } elseif ($role === 'notes') {
            foreach ($pool['notes'] as $n) {
                $out[] = ['type' => 'notes', 'title' => (string) ($n['title'] ?? ''), 'section' => (int) ($n['n'] ?? 0), 'content_id' => $docs('notes'),
                    'stored_minutes' => null, 'status' => 'available', 'review_label' => null];
            }
        }

        return $out;
    }

    /** @param array<string,mixed> $s @return array<string,mixed> */
    private function slide(array $s, int $deckId, string $scope): array
    {
        return ['type' => 'slide', 'slide' => (int) $s['n'], 'slide_type' => (string) $s['slide_type'], 'title' => (string) ($s['title'] ?? ''),
            'content_id' => $deckId, 'scope' => $scope, 'stored_minutes' => isset($s['content']['minutes']) ? (int) $s['content']['minutes'] : null,
            'status' => 'available', 'review_label' => null];
    }

    /**
     * Slides run one after another, so their stored minutes add up against the phase. An activity or a lab
     * is an alternative to another, so each one is held against the phase on its own.
     *
     * @param list<array<string,mixed>> $resources
     * @return list<array<string,mixed>>
     */
    private function overruns(array $resources, int $minutes): array
    {
        $warnings = [];
        $slides = array_filter($resources, fn ($r) => $r['type'] === 'slide' && $r['stored_minutes'] !== null);
        $sum = array_sum(array_column($slides, 'stored_minutes'));
        if ($slides !== [] && $sum > $minutes) {
            $warnings[] = ['code' => 'overrun', 'message' => "The stored slides take $sum minutes; this phase has $minutes.", 'stored_minutes' => $sum, 'phase_minutes' => $minutes];
        }
        foreach ($resources as $r) {
            if ($r['type'] === 'activity' && $r['stored_minutes'] !== null && $r['stored_minutes'] > $minutes) {
                $warnings[] = ['code' => 'overrun', 'message' => "\"{$r['title']}\" is stored as {$r['stored_minutes']} minutes; this phase has $minutes.", 'stored_minutes' => $r['stored_minutes'], 'phase_minutes' => $minutes];
            }
        }

        return $warnings;
    }
}
