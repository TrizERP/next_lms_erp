<?php

namespace App\Services\StudyDeck\Story;

/**
 * The "no invented figures" rule, applied while a story is planned and written.
 *
 * DeckValidator refuses any number in slide wording that the chapter does not contain, but it runs on the finished lesson. The same test
 * is applied here, to each skeleton and each written act, so the model is told what to change while the work is still cheap to redo. A
 * skeleton matters most: its choice wording is what the writer then follows.
 */
class StoryFigures
{
    /** Everything a number may legitimately come from: the chapter text, its title, and the concepts' own names and definitions. */
    public static function allowed(array $context, array $map): string
    {
        $allowed = mb_strtolower((string) $context['ground_truth'] . ' ' . $context['chapter']['chapter_name'] . ' ' . $context['chapter']['subject_name'] . ' ' . $context['chapter']['standard_name']);
        foreach ((array) $map['concepts'] as $c) {
            $allowed .= ' ' . mb_strtolower($c['name'] . ' ' . ($c['definition'] ?? ''));
        }

        return $allowed;
    }

    /** @return array<int,string> one error per number in $text that $allowed does not contain */
    public static function errors(string $where, string $text, string $allowed): array
    {
        $errors = [];
        preg_match_all('/\d+(?:[.,]\d+)?/u', $text, $found);
        foreach (array_unique($found[0]) as $num) {
            if (!preg_match('/(?<![\d.])' . preg_quote($num, '/') . '(?![\d])/u', $allowed)) {
                $errors[] = "$where uses the number $num, which the chapter text does not contain. Do not invent figures: say it in words (\"many\", \"a few\", \"about the same\") or use only numbers the chapter gives.";
            }
        }

        return $errors;
    }
}
