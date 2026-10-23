<?php

namespace App\Services\StudyDeck\Story;

/**
 * Hotspots on a story scene's picture: two to four parts a learner can open, each explained and tied to the scene.
 *
 * The parts come from the picture check (StoryPixelJudge), which LOOKS at the picture and says where each part is and what it shows, so a part is
 * found on the picture, never drawn from a guess. They are written in the shape of the parts of a hotspots slide (`HotspotSpot` in the player:
 * x and y are the CENTRE of the box, as shares of the picture, 0 to 100), so the player's pins, explanation card and "explored" count are the ones it
 * already has. Nothing here invents science: a part's words are the picture check's description of what is visible, and a part with a figure in
 * its words is dropped (no figure can be checked against the chapter from here).
 */
final class StoryHotspots
{
    public const MIN = 2;

    public const MAX = 4;

    /** Two parts that cover this share of each other's area are the same part. */
    private const SAME_PART = 0.55;

    /**
     * The usable parts of what the picture check returned, as hotspots-slide parts; none when fewer than two are usable.
     *
     * @param array<int,mixed> $parts each {label, x, y, w, h (the top-left corner and size, in percent), text}
     * @return array<int,array<string,mixed>>
     */
    public static function spots(array $parts): array
    {
        $kept = [];
        foreach (array_values($parts) as $p) {
            if (!is_array($p)) {
                continue;
            }
            $label = trim((string) preg_replace('/\s+/u', ' ', (string) ($p['label'] ?? '')));
            $text = trim((string) preg_replace('/\s+/u', ' ', (string) ($p['text'] ?? '')));
            $region = StoryPixelJudge::region($p);
            if ($region === null || $label === '' || mb_strlen($label) > 30 || preg_match('/\d/', $label)) {
                continue;
            }
            if (mb_strlen($text) < 20 || mb_strlen($text) > 260 || preg_match('/\d/', $text) || count(preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: []) < 4) {
                continue;
            }
            $duplicate = false;
            foreach ($kept as $other) {
                if (self::overlap($region, $other['region']) >= self::SAME_PART || mb_strtolower($other['label']) === mb_strtolower($label)) {
                    $duplicate = true;
                    break;
                }
            }
            if ($duplicate) {
                continue;
            }
            $kept[] = ['label' => $label, 'text' => $text, 'region' => $region];
            if (count($kept) >= self::MAX) {
                break;
            }
        }
        if (count($kept) < self::MIN) {
            return [];
        }

        $out = [];
        foreach ($kept as $i => $k) {
            $r = $k['region'];
            $out[] = [
                'id' => 'h' . ($i + 1),
                'label' => $k['label'],
                // The player's parts are placed by their CENTRE.
                'x' => round($r['x'] + $r['w'] / 2, 1),
                'y' => round($r['y'] + $r['h'] / 2, 1),
                'w' => $r['w'],
                'h' => $r['h'],
                'text' => $k['text'],
            ];
        }

        return $out;
    }

    /**
     * What is wrong with the hotspots a picture carries (none is fine). Used by the story rules, so a deck with a part outside its picture is refused.
     *
     * @param array<string,mixed> $image a node's picture record
     * @return array<int,string>
     */
    public static function errors(array $image): array
    {
        if (!array_key_exists('hotspots', $image) || $image['hotspots'] === null) {
            return [];
        }
        $spots = $image['hotspots'];
        if (!is_array($spots) || !array_is_list($spots) || count($spots) < self::MIN || count($spots) > self::MAX) {
            return ['its hotspots must be a list of ' . self::MIN . ' to ' . self::MAX . ' parts'];
        }
        $errors = [];
        $ids = [];
        foreach ($spots as $i => $s) {
            $n = $i + 1;
            if (!is_array($s) || trim((string) ($s['id'] ?? '')) === '' || isset($ids[$s['id']])) {
                $errors[] = "hotspot $n needs its own id";
                continue;
            }
            $ids[$s['id']] = true;
            if (trim((string) ($s['label'] ?? '')) === '' || trim((string) ($s['text'] ?? '')) === '') {
                $errors[] = "hotspot $n needs a label and an explanation";
            }
            $box = [$s['x'] ?? null, $s['y'] ?? null, $s['w'] ?? null, $s['h'] ?? null];
            if (count(array_filter($box, 'is_numeric')) !== 4) {
                $errors[] = "hotspot $n needs a position and a size";
                continue;
            }
            [$x, $y, $w, $h] = array_map('floatval', $box);
            if ($w < 4 || $h < 4 || $x - $w / 2 < -0.5 || $y - $h / 2 < -0.5 || $x + $w / 2 > 100.5 || $y + $h / 2 > 100.5) {
                $errors[] = "hotspot $n is outside its picture";
            }
        }

        return $errors;
    }

    /**
     * @param array{x:float,y:float,w:float,h:float} $a
     * @param array{x:float,y:float,w:float,h:float} $b
     */
    private static function overlap(array $a, array $b): float
    {
        $w = max(0.0, min($a['x'] + $a['w'], $b['x'] + $b['w']) - max($a['x'], $b['x']));
        $h = max(0.0, min($a['y'] + $a['h'], $b['y'] + $b['h']) - max($a['y'], $b['y']));
        $smaller = min($a['w'] * $a['h'], $b['w'] * $b['h']);

        return $smaller > 0 ? ($w * $h) / $smaller : 0.0;
    }
}
