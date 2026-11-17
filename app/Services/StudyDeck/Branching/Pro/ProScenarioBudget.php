<?php

namespace App\Services\StudyDeck\Branching\Pro;

use App\Services\StudyDeck\Story\StoryActs;

/**
 * How long a pro Branching Scenario may be, and how a chapter is cut into parts to fit.
 *
 * A part is six slides (explore, decide, three outcomes, watch); a lesson adds a cover, an objectives slide and a closing summary. So a
 * limit of 50 slides allows (50 - 3) / 6 = 7 parts, the last of them the closing mission. A chapter with more topics than that has its
 * neighbouring topics put into one part: every concept is still taught (a part explains all the concepts of the topics it holds), and the
 * learner still explores, decides and sees the consequence for each part, but the lesson does not run to a hundred slides.
 *
 * Pure: it reads the acts StoryActs::plan() gives and a number.
 */
final class ProScenarioBudget
{
    /** Slides a lesson has besides its parts: the cover, the objectives and the closing summary. */
    public const FRAME = 3;

    /** Slides in one part: explore, decide, an outcome for each of three answers, and the watch slide. */
    public const PART = 6;

    /** How many parts (the closing mission included) fit under a limit of $maxSlides slides; at least two. */
    public static function maxParts(int $maxSlides): int
    {
        return max(2, intdiv($maxSlides - self::FRAME, self::PART));
    }

    /** Slides a lesson of $parts parts has. */
    public static function slides(int $parts): int
    {
        return self::FRAME + $parts * self::PART;
    }

    /**
     * The topic acts grouped, in order, into as few parts as the limit allows, with the closing mission kept last and on its own.
     *
     * @param array<int,array<string,mixed>> $acts StoryActs::plan()
     * @return array<int,array<string,mixed>> the same shape, each merged part naming its topics
     */
    public static function group(array $acts, ?int $maxSlides): array
    {
        if ($maxSlides === null || $maxSlides <= 0) {
            return $acts;
        }
        $topics = array_values(array_filter($acts, fn ($a) => $a['kind'] === StoryActs::TOPIC));
        $mission = array_values(array_filter($acts, fn ($a) => $a['kind'] === StoryActs::MISSION));
        $room = self::maxParts($maxSlides) - count($mission);
        if (count($topics) <= $room) {
            return $acts;
        }

        // Contiguous groups, as even as they can be: the cut that makes the heaviest group (most concepts) as light as possible, and, among
        // those, the one that makes the groups' weights closest to each other.
        $weights = array_map(fn ($a) => max(1, count($a['concept_ids'])), $topics);
        $groups = [];
        foreach (self::partition($weights, $room) as [$from, $to]) {
            $groups[] = array_slice($topics, $from, $to - $from);
        }
        $out = [];
        foreach ($groups as $i => $g) {
            $out[] = count($g) === 1 ? ['key' => 'a' . ($i + 1)] + $g[0] : [
                'key' => 'a' . ($i + 1), 'kind' => StoryActs::TOPIC, 'title' => self::title($g), 'topic_id' => $g[0]['topic_id'],
                'concept_ids' => array_values(array_unique(array_merge(...array_map(fn ($a) => $a['concept_ids'], $g)))),
                'required_ids' => array_values(array_unique(array_merge(...array_map(fn ($a) => $a['required_ids'], $g)))),
                'description' => trim(implode(' ', array_filter(array_map(fn ($a) => $a['description'], $g)))),
                'topics' => array_map(fn ($a) => $a['title'], $g),
            ];
        }
        foreach ($mission as $m) {
            $out[] = ['key' => 'a' . (count($out) + 1)] + $m;
        }

        return $out;
    }

    /**
     * Cuts a list of weights into $groups contiguous runs so that the heaviest run is as light as possible, and ties go to the cut whose
     * runs differ least (smallest sum of squares). Dynamic programming; the lists are a chapter's topics, so it is tiny.
     *
     * @param array<int,int> $w
     * @return array<int,array{0:int,1:int}> [from, to) for each run
     */
    public static function partition(array $w, int $groups): array
    {
        $n = count($w);
        $groups = max(1, min($groups, $n));
        $prefix = [0];
        foreach ($w as $x) {
            $prefix[] = end($prefix) + $x;
        }
        $INF = PHP_INT_MAX;
        // best[g][i] = [max weight, sum of squares] for the first i items in g runs
        $best = array_fill(0, $groups + 1, array_fill(0, $n + 1, [$INF, $INF]));
        $cut = array_fill(0, $groups + 1, array_fill(0, $n + 1, 0));
        $best[0][0] = [0, 0];
        for ($g = 1; $g <= $groups; $g++) {
            for ($i = $g; $i <= $n; $i++) {
                for ($j = $g - 1; $j < $i; $j++) {
                    if ($best[$g - 1][$j][0] === $INF) {
                        continue;
                    }
                    $run = $prefix[$i] - $prefix[$j];
                    $cand = [max($best[$g - 1][$j][0], $run), $best[$g - 1][$j][1] + $run * $run];
                    if ($cand < $best[$g][$i]) {
                        $best[$g][$i] = $cand;
                        $cut[$g][$i] = $j;
                    }
                }
            }
        }
        $runs = [];
        $i = $n;
        for ($g = $groups; $g >= 1; $g--) {
            $j = $cut[$g][$i];
            array_unshift($runs, [$j, $i]);
            $i = $j;
        }

        return $runs;
    }

    /** "A and B", "A, B and C": the topics of a part, at most 60 characters (a diagram carries it as its centre). */
    private static function title(array $group): string
    {
        $names = array_map(fn ($a) => trim((string) $a['title']), $group);
        $last = array_pop($names);
        $title = $names === [] ? $last : implode(', ', $names) . ' and ' . $last;

        return mb_strlen($title) <= 60 ? $title : rtrim(mb_substr($title, 0, 57), ' ,') . '...';
    }
}
