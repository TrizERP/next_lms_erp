<?php

namespace App\Services\PAL\ApplicationLab;

use App\Services\PAL\Integration\WikimediaCommonsImageSearch;
use Illuminate\Support\Facades\Cache;

/**
 * Real pictures for the things a student names in "Create Your Own Activity".
 *
 * The student types "magnet" or "biker"; this returns a few openly licensed photographs of it from Wikimedia
 * Commons (through the estate's existing, already rate-aware Commons search), each with the credit the licence
 * requires. Only the word the student typed leaves the server, and nothing about who they are.
 *
 * Only Wikimedia picture addresses are ever returned, so the browser can show them and the stored creation can
 * be checked against the same rule. A student is limited to a few searches a minute so typing cannot flood
 * Commons.
 */
class StudentPictureSearch
{
    /** Wikimedia serves its pictures from these two addresses; nothing else is accepted. */
    public const HOSTS = ['upload.wikimedia.org', 'thumb.wikimedia.org'];

    private const PER_MINUTE = 20;

    public function __construct(private WikimediaCommonsImageSearch $commons)
    {
    }

    /** The only picture addresses a creation may carry. */
    public static function isAllowedUrl(string $url): bool
    {
        $hosts = implode('|', array_map(fn ($h) => preg_quote($h, '#'), self::HOSTS));

        return strlen($url) <= 400
            && (bool) preg_match('#^https://('.$hosts.')/[A-Za-z0-9/_%.,()\'~:+@=&!*;-]+$#', $url);
    }

    /**
     * How well a Commons photograph shows the thing the student named. Its title or categories must mention the
     * word; a clean photo of the object itself (a lab, studio or white-background shot with a short title) ranks
     * highest; a place, a person, a museum piece or big-science hardware ranks lower. Below 1 means it is not
     * about the word and is not offered.
     *
     * @param array<string,mixed> $row
     */
    public static function relevance(string $query, array $row): float
    {
        $words = array_values(array_filter(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($query)) ?: [], fn ($w) => mb_strlen($w) > 2));
        if ($words === []) {
            return 0.0;
        }
        $title = mb_strtolower((string) ($row['title'] ?? ''));
        $tags = mb_strtolower(implode(' ', array_map('strval', (array) ($row['tags'] ?? []))));
        $stem = fn (string $w): string => preg_replace('/(es|s)$/', '', $w) ?: $w;

        $score = 0.0;
        foreach ($words as $w) {
            $st = $stem($w);
            if (str_contains($title, $st)) {
                $score += 3;
            } elseif (str_contains($tags, $st)) {
                $score += 1.5;
            }
        }
        $score /= count($words);
        if ($score < 1) {
            return $score;
        }

        $text = $title.' '.$tags;
        if (preg_match('/\b(laborator\w*|science|scientific|apparatus|equipment|glassware|physics|chemistry|biology|experiment\w*|school|lab)\b/', $text)) {
            $score += 1;
        }
        if (preg_match('/\b(white background|isolated|studio|cut ?out|product|closeup|close-up|detail)\b/', $text)) {
            $score += 1;
        }
        // A short title is usually a photograph of the thing itself ("Beaker 01"); a long one describes an event.
        $score += str_word_count($title) <= count($words) + 2 ? 1.5 : 0;
        if (preg_match('/\b(town|city|village|county|district|municipality|street|road|church|station|building|panorama|skyline|people|crowd|portrait|wedding|festival|team|club|football|band|bell|culture|museum|archaeolog\w*|ancient|pottery|silver|antique|medieval|staff|university|institute|observatory|collider|cyclotron|accelerator|neutrino|nijmegen|stellarator|reactor|telescope|lunar|nasa|iss\d*|satellite|cubesat|spacecraft)\b/', $text)) {
            $score -= 3;
        }

        return max(0.1, $score);
    }

    /**
     * @return array{pictures: array<int,array{url:string,credit:string,title:string}>, limited: bool}
     */
    public function search($studentId, string $query, int $limit = 6): array
    {
        $query = trim((string) preg_replace('/\s+/', ' ', mb_substr($query, 0, 40)));
        if (mb_strlen($query) < 3) {
            return ['pictures' => [], 'limited' => false];
        }

        // A few searches a minute per student; the Commons answers themselves are cached for a month.
        $key = 'pal_picture_search:'.(int) $studentId.':'.floor(time() / 60);
        $count = (int) Cache::get($key, 0);
        if ($count >= self::PER_MINUTE) {
            return ['pictures' => [], 'limited' => true];
        }
        Cache::put($key, $count + 1, now()->addMinutes(2));

        // What a student names in a science activity is almost always an object or apparatus, so ask for it in a
        // science setting first (a "beaker laboratory" photo, not a town called Beaker), then as typed.
        $rows = [];
        foreach ([$query.' laboratory', $query.' science', $query] as $form) {
            foreach ($this->commons->search($form, 12) as $row) {
                $rows[(string) ($row['url'] ?? '')] ??= $row;
            }
            if (count($rows) >= $limit * 3) {
                break;
            }
        }

        $scored = [];
        foreach ($rows as $row) {
            // Commons adds tracking words after a "?"; the picture itself does not need them.
            $url = (string) strtok((string) ($row['thumbnail_url'] ?? ''), '?');
            if (! self::isAllowedUrl($url)) {
                continue;
            }
            $score = self::relevance($query, $row);
            if ($score < 1) {
                continue;   // not about what the student typed
            }
            $scored[] = [
                'score' => $score,
                'pic' => [
                    'url' => $url,
                    'credit' => mb_substr((string) ($row['attribution'] ?? ''), 0, 220),
                    'title' => mb_substr((string) ($row['title'] ?? $query), 0, 80),
                ],
            ];
        }
        usort($scored, fn ($x, $y) => $y['score'] <=> $x['score']);
        $out = array_slice(array_column($scored, 'pic'), 0, $limit);

        return ['pictures' => $out, 'limited' => false];
    }
}
