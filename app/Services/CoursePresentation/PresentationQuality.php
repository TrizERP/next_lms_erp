<?php

namespace App\Services\CoursePresentation;

use App\Services\StudyDeck\CardTitle;
use App\Services\StudyDeck\Story\StoryGraph;
use App\Services\StudyDeck\Story\StoryTemplate;

/**
 * Quality rules for a Course Presentation deck (study-deck schema v3), applied to the source-driven preview AND to a
 * generated draft, so both are held to the same standard. Pure: it reads the deck array it is given and nothing else.
 *
 * It detects: a repeated chapter/topic index slide, repeated titles or explanations, empty or nearly empty slides,
 * slides that are too dense to present, concepts no slide teaches, pictures that cannot be shown, and interactions the
 * viewer could not play. `interactionProblem` mirrors lms_k12/lib/study-deck/interactions.ts so the two agree.
 */
class PresentationQuality
{
    /** @param array<string,mixed>|null $storyConfig StoryTemplate::resolve()['config'] for the school; the built-in defaults when null */
    public function __construct(private readonly ?array $storyConfig = null)
    {
    }

    /** Slide types that frame the lesson (the viewer draws them in its "intro" layout). Mirrors lib/study-deck/stage.ts. */
    private const FRAMING = ['hook', 'objectives', 'prior_knowledge', 'challenge', 'exit_ticket'];
    private const MAX_BULLETS = 8;
    private const MAX_VISIBLE_CHARS = 900;
    private const MAX_REVEAL_ITEMS = 8;

    /**
     * @param array<string,mixed> $deck
     * @param array<int,int>|null $conceptIds every concept the deck must teach (null: the deck's own concept map)
     * @param array<int,string> $names concept id => name, for the message when the deck's own map does not know the concept
     * @return array<int,array{code:string,slide:int|null,message:string}>
     */
    public function check(array $deck, ?array $conceptIds = null, array $names = []): array
    {
        $problems = [];
        $slides = array_values((array) ($deck['slides'] ?? []));
        $add = function (string $code, ?int $n, string $message) use (&$problems) {
            $problems[] = ['code' => $code, 'slide' => $n, 'message' => $message];
        };

        // ---- repeated chapter / topic index slides: the viewer draws a bullet-less framing slide as the full topic list
        $indexSlides = [];
        foreach ($slides as $s) {
            if ($this->isTopicIndex($s)) {
                $indexSlides[] = (int) $s['n'];
            }
        }
        foreach (array_slice($indexSlides, 1) as $n) {
            $add('repeated_index', $n, 'This slide would show the whole chapter topic list again; the overview appears once, near the start.');
        }

        // ---- repeated titles and repeated explanations
        $titles = [];
        $texts = [];
        foreach ($slides as $s) {
            $n = (int) ($s['n'] ?? 0);
            if (($s['slide_type'] ?? '') !== 'cover') {
                $t = $this->norm((string) ($s['title'] ?? ''));
                if ($t !== '' && isset($titles[$t])) {
                    $add('duplicate_title', $n, "The title repeats slide {$titles[$t]}.");
                }
                $titles[$t] ??= $n;
            }
            $seen = [];
            foreach (array_merge(
                array_map(fn ($e) => (string) ($e['text'] ?? ''), (array) ($s['content']['explanations'] ?? [])),
                [(string) ($s['content']['body'] ?? '')],
                array_map('strval', (array) ($s['content']['bullets'] ?? []))
            ) as $text) {
                $k = $this->norm($text);
                if (mb_strlen($k) < 40 || isset($seen[$k])) {
                    continue;
                }
                $seen[$k] = true;
                if (isset($texts[$k]) && $texts[$k] !== $n) {
                    $add('duplicate_content', $n, "The same text already appears on slide {$texts[$k]}.");
                } else {
                    $texts[$k] = $n;
                }
            }
        }

        foreach ($slides as $s) {
            $n = (int) ($s['n'] ?? 0);
            $c = (array) ($s['content'] ?? []);
            $type = (string) ($s['slide_type'] ?? '');
            $bullets = array_filter(array_map('strval', (array) ($c['bullets'] ?? [])));
            $hasExplanation = array_filter(array_map(fn ($e) => trim((string) ($e['text'] ?? '')), (array) ($c['explanations'] ?? []))) !== [];
            $hasInteraction = is_array($s['interaction'] ?? null);
            $hasImage = is_array($s['image'] ?? null);
            $body = trim((string) ($c['body'] ?? ''));

            // ---- empty or nearly empty
            if ($type !== 'cover' && !$hasInteraction && !$hasImage && !$hasExplanation && !$bullets && mb_strlen($body) < 20 && empty($c['misconception']) && empty($c['discussion'])) {
                if (!in_array($n, $indexSlides, true)) {
                    $add('empty_slide', $n, 'The slide has no explanation, list, picture or interaction.');
                }
            }

            // ---- too dense to present
            $visible = mb_strlen($body) + array_sum(array_map('mb_strlen', $bullets));
            if (count($bullets) > self::MAX_BULLETS || $visible > self::MAX_VISIBLE_CHARS) {
                $add('too_dense', $n, 'The slide shows too much text at once (' . count($bullets) . " points, $visible characters).");
            }
            foreach ((array) ($c['explanations'] ?? []) as $e) {
                if (mb_strlen((string) ($e['text'] ?? '')) > 700) {
                    $add('too_dense', $n, 'An explanation is longer than 700 characters.');
                }
            }

            // ---- pictures that cannot be shown
            if ($hasImage) {
                $img = $s['image'];
                if (trim((string) ($img['url'] ?? '')) === '' || (int) ($img['width'] ?? 0) < 1 || (int) ($img['height'] ?? 0) < 1) {
                    $add('bad_image', $n, 'The picture has no address or no size, so it cannot be drawn at its aspect ratio.');
                }
                if (trim((string) ($img['alt'] ?? '')) === '') {
                    $add('bad_image', $n, 'The picture has no alternative text.');
                }
            }

            // ---- numbered placeholder headings ("Key idea 1", "Card 3") on slides, cards, pairs and parts
            foreach ($this->labelsOf($s) as $where => $label) {
                if (trim((string) $label) !== '' && CardTitle::isGeneric($label)) {
                    $add('generic_label', $n, "The $where \"" . trim((string) $label) . '" is a placeholder; it should name what the card says.');
                }
            }

            // ---- interactions the viewer could not play
            if ($hasInteraction) {
                $why = self::interactionProblem($s['interaction'], $hasImage && ($s['image']['type'] ?? '') === 'diagram');
                if ($why !== null) {
                    $add('broken_interaction', $n, "The interaction $why.");
                }
                // A story act is judged by the story rules (every path ends, merges, coverage), the same ones DeckValidator applies.
                if (($s['interaction']['template'] ?? null) === StoryTemplate::KEY) {
                    $story = StoryGraph::check($s['interaction'], array_map('intval', (array) ($s['taught_concept_ids'] ?? [])), $this->storyConfig ?? StoryTemplate::defaults());
                    foreach ($story['errors'] as $e) {
                        $add('broken_story', $n, $e);
                    }
                }
                $k = $s['interaction']['kind'] ?? '';
                if (in_array($k, ['reveal', 'compare'], true) && count((array) ($s['interaction']['items'] ?? [])) > self::MAX_REVEAL_ITEMS) {
                    $add('too_dense', $n, 'The interaction has more than ' . self::MAX_REVEAL_ITEMS . ' cards.');
                }
            }
        }

        // ---- concepts nobody teaches
        $must = $conceptIds ?? array_map('intval', array_keys((array) ($deck['concepts'] ?? [])));
        $taught = [];
        foreach ($slides as $s) {
            foreach ((array) ($s['taught_concept_ids'] ?? []) as $id) {
                $taught[(int) $id] = true;
            }
        }
        foreach ($must as $id) {
            if (empty($taught[(int) $id])) {
                $name = (string) ($names[(int) $id] ?? $deck['concepts'][(string) $id]['name'] ?? $id);
                $add('missing_concept', null, "No slide teaches the concept \"$name\".");
            }
        }

        return $problems;
    }

    /**
     * Every heading a learner reads on a slide, keyed by what it is.
     *
     * @return array<string,string>
     */
    private function labelsOf(array $s): array
    {
        $out = ['slide title' => (string) ($s['title'] ?? '')];
        $i = $s['interaction'] ?? null;
        foreach ((array) ($i['items'] ?? []) as $k => $c) {
            $out['card heading #' . ($k + 1)] = (string) ($c['label'] ?? '');
        }
        foreach ((array) ($i['nodes'] ?? []) as $k => $c) {
            $out['story scene #' . ($k + 1)] = (string) ($c['title'] ?? '');
        }
        foreach ((array) ($i['spots'] ?? []) as $k => $c) {
            $out['diagram part #' . ($k + 1)] = (string) ($c['label'] ?? '');
        }
        foreach ((array) ($i['pairs'] ?? []) as $k => $c) {
            $out['match term #' . ($k + 1)] = (string) ($c['term'] ?? '');
        }

        return $out;
    }

    /** True for a slide the viewer would draw as the whole-chapter topic list. */
    private function isTopicIndex(array $s): bool
    {
        if (($s['slide_type'] ?? '') === 'cover' || is_array($s['interaction'] ?? null) || is_array($s['image'] ?? null)) {
            return false;
        }
        if (in_array($s['slide_type'] ?? '', ['summary', 'concept_map'], true)) {
            return false;
        }
        $framing = in_array($s['slide_type'] ?? '', self::FRAMING, true) || empty($s['taught_concept_ids']);

        return $framing && count((array) ($s['content']['bullets'] ?? [])) === 0;
    }

    private function norm(string $s): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($s)));
    }

    /**
     * What is wrong with an interaction's shape, or null when the player can draw it. Mirrors interactionProblem() in
     * lms_k12/lib/study-deck/interactions.ts.
     *
     * @param mixed $i
     */
    public static function interactionProblem(mixed $i, bool $slideImageIsDiagram): ?string
    {
        if (!is_array($i)) {
            return 'is not an object';
        }
        switch ($i['kind'] ?? null) {
            case 'hotspots':
                if (!$slideImageIsDiagram) {
                    return 'asks for hotspots on a slide with no drawn diagram';
                }
                $spots = $i['spots'] ?? null;
                if (!is_array($spots) || count($spots) < 2) {
                    return 'has fewer than two hotspots';
                }
                $ids = [];
                foreach ($spots as $s) {
                    if (empty($s['id']) || !is_string($s['text'] ?? null)) {
                        return 'has a malformed hotspot';
                    }
                    foreach (['x', 'y', 'w', 'h'] as $k) {
                        if (!is_int($s[$k] ?? null) && !is_float($s[$k] ?? null)) {
                            return 'has a malformed hotspot';
                        }
                    }
                    if (isset($s['pin']) && (!is_numeric($s['pin']['x'] ?? null) || !is_numeric($s['pin']['y'] ?? null) || $s['pin']['x'] < 0 || $s['pin']['x'] > 100 || $s['pin']['y'] < 0 || $s['pin']['y'] > 100)) {
                        return 'has a pin that is off the picture';
                    }
                    $ids[] = $s['id'];
                }
                if (count(array_unique($ids)) !== count($ids)) {
                    return 'repeats a hotspot id';
                }

                return null;
            case 'reveal':
            case 'steps':
            case 'timeline':
            case 'compare':
                $items = $i['items'] ?? null;
                $min = in_array($i['kind'], ['steps', 'timeline'], true) ? 3 : 2;
                if (!is_array($items) || count($items) < $min) {
                    return "has fewer than $min items";
                }
                $ids = [];
                foreach ($items as $c) {
                    if (empty($c['id']) || empty($c['label']) || empty($c['text'])) {
                        return 'has a malformed item';
                    }
                    if ($i['kind'] === 'timeline' && empty($c['when'])) {
                        return 'has a timeline event with no date';
                    }
                    $ids[] = $c['id'];
                }

                return count(array_unique($ids)) === count($ids) ? null : 'repeats an item id';
            case 'match':
                $pairs = $i['pairs'] ?? null;
                if (!is_array($pairs) || count($pairs) < 3) {
                    return 'has fewer than three pairs';
                }
                $ids = [];
                foreach ($pairs as $c) {
                    if (empty($c['id']) || empty($c['term']) || empty($c['meaning'])) {
                        return 'has a malformed pair';
                    }
                    $ids[] = $c['id'];
                }

                return count(array_unique($ids)) === count($ids) ? null : 'repeats a pair id';
            case 'order':
                $items = $i['items'] ?? null;
                if (!is_array($items) || count($items) < 3) {
                    return 'has fewer than three items';
                }

                return null;
            case 'scenario':
                return empty($i['nodes']) ? 'has no decisions' : null;
        }

        return 'has an unknown kind';
    }
}
