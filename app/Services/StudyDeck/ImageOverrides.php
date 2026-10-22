<?php

namespace App\Services\StudyDeck;

/**
 * Swaps chosen pictures into a deck that is already planned, with no model call and no search.
 *
 * For when a person has looked at the pictures and decided which ones teach best: a photograph from a named source, or a
 * figure drawn for the lesson. The file that says which (`--images-from=`) is plain JSON:
 *
 *   { "slides": {
 *       "1":  { "photo": { "image_url": ..., "source_url": ..., "title": ..., "creator": ..., "license": "BY-SA 4.0",
 *                          "provider": ..., "alt": ..., "caption": ..., "shows": ..., "query": ..., "purpose": ... } },
 *       "16": { "illustration": { "file": "illus/16-cell-factory.png", "caption": ..., "alt": ..., "texts": [...],
 *                                 "anchors": [ { "label": ..., "x": ..., "y": ..., "w": ..., "h": ... } ], "purpose": ... } },
 *       "18": { "remove": "why" }
 *   } }
 *
 * Every picture faces the same rules as a found one (see ImagePlanner::adopt). A slide that had no planned visual gets
 * one, so the validator holds its picture to the same rules. The hotspots on a slide whose picture is replaced are moved
 * to where the new picture's labels are (`anchors`), by label; a hotspot whose label has no anchor is an error, never
 * guessed. Nothing is read from or written to the database here except through the image store the planner holds.
 */
final class ImageOverrides
{
    public function __construct(private readonly ImagePlanner $planner)
    {
    }

    /**
     * @param array<string,mixed> $spec the decoded file
     * @param array<string,mixed> $plan plan.json
     * @param array<int,array<string,mixed>> $images slide number => image record
     * @param array<int,array<string,mixed>> $interactions slide number => interaction record
     * @param callable(string,string):void|null $say
     * @return array{0:array<string,mixed>,1:array<int,array<string,mixed>>,2:array<int,array<string,mixed>>} plan, images, interactions
     */
    public function apply(array $spec, array $plan, array $images, array $interactions, ?callable $say = null, string $baseDir = ''): array
    {
        $say ??= fn () => null;
        $slides = (array) ($spec['slides'] ?? []);
        if (!$slides) {
            throw new \InvalidArgumentException('the pictures file names no slides ({"slides": {"<n>": {...}}})');
        }

        foreach ($slides as $key => $entry) {
            $n = (int) $key;
            $at = null;
            foreach ($plan['slides'] as $i => $slide) {
                if ((int) $slide['n'] === $n) {
                    $at = $i;
                }
            }
            if ($at === null) {
                throw new \InvalidArgumentException("The pictures file names slide $n, which the deck does not have.");
            }
            $slide = $plan['slides'][$at];
            $visual = (array) ($slide['visual'] ?? []);

            try {
                if (isset($entry['remove'])) {
                    $images[$n] = ['missing' => 'taken out by the reviewer: ' . trim((string) $entry['remove']), 'query' => (string) ($visual['query'] ?? ''), 'purpose' => (string) ($visual['purpose'] ?? '')];
                    $say('images', "slide $n: picture removed");
                    continue;
                }

                if (isset($entry['photo'])) {
                    $p = (array) $entry['photo'];
                    $query = trim((string) ($p['query'] ?? $visual['query'] ?? $p['title'] ?? ''));
                    $purpose = trim((string) ($p['purpose'] ?? $visual['purpose'] ?? ''));
                    $images[$n] = $this->planner->adopt($p, $query, $purpose);
                    $role = $visual['role'] ?? 'object';
                    $say('images', "slide $n: photograph adopted (" . $images[$n]['licence'] . ')');
                } elseif (isset($entry['illustration'])) {
                    $p = (array) $entry['illustration'];
                    $purpose = trim((string) ($p['purpose'] ?? $visual['purpose'] ?? ''));
                    $file = (string) ($p['file'] ?? '');
                    if ($file !== '' && !preg_match('~^([A-Za-z]:)?[\\\\/]~', $file) && $baseDir !== '') {
                        $file = rtrim($baseDir, '/\\') . '/' . $file;
                    }
                    $images[$n] = $this->planner->illustration($file, $p, $purpose);
                    $role = 'diagram';
                    $query = trim((string) ($visual['query'] ?? $p['caption'] ?? ''));
                    $interactions = $this->moveHotspots($n, $interactions, (array) ($p['anchors'] ?? []), $images[$n]['texts']);
                    $say('images', "slide $n: illustration adopted");
                } else {
                    throw new \InvalidArgumentException('an entry needs "photo", "illustration" or "remove"');
                }
            } catch (\InvalidArgumentException|\RuntimeException $e) {
                throw new \RuntimeException("Slide $n: " . $e->getMessage(), 0, $e);
            }

            // A slide with no planned visual gets one, so the validator holds the new picture to the picture rules.
            if (empty($slide['visual'])) {
                $plan['slides'][$at]['visual'] = ['required' => true, 'role' => $role, 'query' => $query, 'purpose' => $purpose];
            }
        }

        return [$plan, $images, $interactions];
    }

    /**
     * Put each hotspot where its label now is. Matches by label, ignoring case; a label that has no anchor, or an anchor
     * outside the picture, stops the run.
     *
     * @param array<int,array<string,mixed>> $interactions
     * @param array<int,array<string,mixed>> $anchors
     * @param array<int,string> $texts the words drawn on the new picture
     * @return array<int,array<string,mixed>>
     */
    private function moveHotspots(int $n, array $interactions, array $anchors, array $texts): array
    {
        $interaction = $interactions[$n]['interaction'] ?? null;
        if (!is_array($interaction) || ($interaction['kind'] ?? '') !== 'hotspots') {
            return $interactions;
        }
        $by = [];
        foreach ($anchors as $a) {
            $by[mb_strtolower(trim((string) ($a['label'] ?? '')))] = $a;
        }
        $drawn = array_map('mb_strtolower', $texts);

        foreach ($interaction['spots'] as $i => $spot) {
            $label = mb_strtolower(trim((string) $spot['label']));
            if (!isset($by[$label])) {
                throw new \InvalidArgumentException('the hotspot "' . $spot['label'] . '" has no anchor on the new picture');
            }
            if (!in_array($label, $drawn, true)) {
                throw new \InvalidArgumentException('the hotspot "' . $spot['label'] . '" is not a label drawn on the new picture');
            }
            $a = $by[$label];
            foreach (['x', 'y', 'w', 'h'] as $k) {
                if (!isset($a[$k]) || !is_numeric($a[$k]) || $a[$k] < 0 || $a[$k] > 100) {
                    throw new \InvalidArgumentException('the anchor for "' . $spot['label'] . '" has no usable ' . $k);
                }
            }
            $interactions[$n]['interaction']['spots'][$i] = array_merge($spot, ['x' => round((float) $a['x'], 1), 'y' => round((float) $a['y'], 1), 'w' => round((float) $a['w'], 1), 'h' => round((float) $a['h'], 1)]);
        }

        return $interactions;
    }
}
