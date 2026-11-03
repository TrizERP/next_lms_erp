<?php

namespace App\Services\PAL\Integration;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Candidate photographs straight from Wikimedia Commons, through its official MediaWiki API.
 *
 * Why this exists: the Openverse search endpoint stopped answering from this estate's network (the connection opens and nothing comes back, for
 * 45 s and more), and Openverse's `source=wikimedia` filter is only a proxy for this same index. Commons answers in about a second. Candidates
 * come back in the shape ConceptImageSearchService::shape() produces, so everything after the search (ranking, the licence rule, keyword
 * evidence, the download, the sharpness gate, the credit) is the existing pipeline's and is not repeated here.
 *
 * Only what can be shown lawfully and teaches as a photograph is returned:
 *  - a JPEG, PNG or WebP bitmap (no SVG, no scan of a document) that is large enough to be clear on a slide;
 *  - a Creative Commons BY / BY-SA / CC0 or public-domain licence, written the way ImagePlanner accepts it ("BY-SA 4.0", "CC0 1.0", "PDM 1.0");
 *    GFDL-only files and anything with an unclear licence are left out;
 *  - a credited author for BY and BY-SA files (a file with no named author cannot be credited, so it is not offered);
 *  - not a diagram, chart, map, logo, flag, stamp, coin or cover, and nothing a school would not show.
 */
class WikimediaCommonsImageSearch
{
    private const WORDS_MAX = 5;

    /** Words that say nothing about what a picture shows. */
    private const STOPWORDS = ['a', 'an', 'the', 'of', 'is', 'are', 'was', 'were', 'with', 'to', 'in', 'on', 'for', 'and', 'or', 'by', 'from', 'this', 'that', 'its', 'their', 'using', 'use', 'how', 'why', 'what', 'our', 'your'];

    /** A title or category that marks a drawing, a document or decoration rather than a photograph of the subject. */
    private const NOT_A_PHOTO = '/\b(diagrams?|charts?|graphs?|maps?|logos?|icons?|flags?|coats? of arms|stamps?|banknotes?|coins?|book covers?|posters?|screenshots?|infographics?|clip ?art|cartoons?|illustrations?|drawings?|sketch(es)?|pdf|svg|scans? of)\b/i';

    /** Categories a school does not need to see. */
    private const NOT_FOR_SCHOOL = '/\b(nude|nudity|erotic|sexual|sex|porn\w*|genital\w*|lingerie|bikini|topless|gore|corpse|autopsy|violence)\b/i';

    public function available(): bool
    {
        return (bool) config('pal_content.image.commons.enabled', true);
    }

    /**
     * Ranked photographs for a query, best first. An empty list means nothing usable came back, or the service did not answer; the caller
     * cannot tell which and does not need to (Commons has its own timeout, and a failure is never cached).
     *
     * @return array<int,array<string,mixed>>
     */
    public function search(string $query, int $limit = 8): array
    {
        if (!$this->available()) {
            return [];
        }
        $out = [];
        foreach ($this->variants($query) as $variant) {
            foreach ($this->one($variant) as $row) {
                $out[$row['url']] ??= $row;
            }
            if (count($out) >= $limit) {
                break;
            }
        }

        return array_slice(array_values($out), 0, max(1, $limit));
    }

    /**
     * The query as it is, then shorter forms of its own significant words: Commons needs every word to appear, so a long phrase finds nothing
     * where its first words find the subject.
     *
     * @return array<int,string>
     */
    public function variants(string $query): array
    {
        $words = [];
        foreach (preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($query)) ?: [] as $w) {
            if (mb_strlen($w) > 2 && !in_array($w, self::STOPWORDS, true) && !in_array($w, $words, true)) {
                $words[] = $w;
            }
        }
        $words = array_slice($words, 0, self::WORDS_MAX);
        $variants = [];
        foreach ([count($words), 3, 2] as $n) {
            if ($n >= 1 && $n <= count($words)) {
                $variants[] = implode(' ', array_slice($words, 0, $n));
            }
        }

        return array_values(array_unique($variants));
    }

    /**
     * Photographs for many queries at once (a generation that needs a picture for every slide). Each round asks, all together, for the next
     * shorter form of every query that has fewer than `$enough` photographs so far, so a stalled or slow answer for one query costs the others nothing.
     * A round is not started when `$deadline` is near.
     *
     * @param array<int,string> $queries
     * @return array<string,array<int,array<string,mixed>>> query => photographs, best first (empty when none came back)
     */
    public function searchMany(array $queries, int $limit = 8, int $concurrency = 10, ?float $deadline = null, int $enough = 3): array
    {
        $queries = array_values(array_unique(array_filter(array_map(fn ($q) => trim((string) $q), $queries), fn ($q) => $q !== '')));
        $out = array_fill_keys($queries, []);
        if (!$this->available() || $queries === []) {
            return $out;
        }
        $forms = [];
        foreach ($queries as $q) {
            $forms[$q] = $this->variants($q);
        }

        for ($round = 0; $round < 3; $round++) {
            $todo = [];   // query => the form to ask in this round
            foreach ($queries as $q) {
                if (count($out[$q]) < $enough && isset($forms[$q][$round])) {
                    $todo[$q] = $forms[$q][$round];
                }
            }
            if ($todo === [] || ($deadline !== null && $deadline - microtime(true) < 2.0)) {
                break;
            }
            $answers = [];   // form => rows
            $missing = [];
            foreach (array_unique($todo) as $form) {
                if (is_array($hit = $this->remembered($form))) {
                    $answers[$form] = $hit;
                } else {
                    $missing[] = $form;
                }
            }
            foreach (array_chunk($missing, max(1, $concurrency)) as $group) {
                if ($deadline !== null && $deadline - microtime(true) < 2.0) {
                    break;
                }
                $timeout = $deadline === null ? (int) config('pal_content.image.commons.timeout', 10) : max(1, min((int) config('pal_content.image.commons.timeout', 10), (int) ceil($deadline - microtime(true))));
                try {
                    $responses = Http::pool(function (\Illuminate\Http\Client\Pool $pool) use ($group, $timeout) {
                        $calls = [];
                        foreach ($group as $i => $form) {
                            $calls[] = $pool->as((string) $i)->withUserAgent($this->userAgent())->timeout($timeout)->get($this->endpoint(), $this->params($form));
                        }

                        return $calls;
                    });
                } catch (\Throwable) {
                    continue;
                }
                foreach ($group as $i => $form) {
                    $r = $responses[(string) $i] ?? null;
                    if ($r instanceof \Illuminate\Http\Client\Response && ($rows = $this->parse($r)) !== null) {
                        $answers[$form] = $rows;
                        $this->remember($form, $rows);
                    }
                }
            }
            foreach ($todo as $q => $form) {
                foreach ($answers[$form] ?? [] as $row) {
                    $out[$q][$row['url']] ??= $row;
                }
            }
        }

        return array_map(fn ($rows) => array_slice(array_values($rows), 0, max(1, $limit)), $out);
    }

    private function endpoint(): string
    {
        return (string) config('pal_content.image.commons.endpoint', 'https://commons.wikimedia.org/w/api.php');
    }

    /** @return array<string,mixed> */
    private function params(string $query): array
    {
        return [
            'action' => 'query', 'format' => 'json', 'formatversion' => 2,
            'generator' => 'search', 'gsrnamespace' => 6, 'gsrsearch' => $query . ' filetype:bitmap', 'gsrlimit' => 30,
            'prop' => 'imageinfo', 'iiprop' => 'url|size|mime|extmetadata', 'iiurlwidth' => 640,
            'iiextmetadatafilter' => 'LicenseShortName|Artist|Credit|ImageDescription|ObjectName|Categories|Restrictions',
        ];
    }

    /** What an earlier search for these words found, or null. @return array<int,array<string,mixed>>|null */
    private function remembered(string $query): ?array
    {
        try {
            $cached = Cache::get('pal:image:commons:' . sha1($query));
        } catch (\Throwable) {
            return null;
        }

        return is_array($cached) ? $cached : null;
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function remember(string $query, array $rows): void
    {
        try {
            Cache::put('pal:image:commons:' . sha1($query), $rows, now()->addHours((int) config('pal_content.image.external.cache_hours', 720)));
        } catch (\Throwable) {
        }
    }

    /**
     * The usable photographs in Commons' answer, best first; null when it was not a usable answer at all (nothing is remembered about those).
     *
     * @return array<int,array<string,mixed>>|null
     */
    private function parse(\Illuminate\Http\Client\Response $response): ?array
    {
        if (!$response->successful() || !is_array($pages = $response->json('query.pages'))) {
            return null;
        }
        usort($pages, fn ($a, $b) => ((int) ($a['index'] ?? 0)) <=> ((int) ($b['index'] ?? 0)));
        $rows = [];
        foreach ($pages as $page) {
            if (($row = $this->shape((array) $page)) !== null) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /** @return array<int,array<string,mixed>> */
    private function one(string $query): array
    {
        if (is_array($hit = $this->remembered($query))) {
            return $hit;
        }
        try {
            $response = Http::withUserAgent($this->userAgent())->timeout((int) config('pal_content.image.commons.timeout', 10))->retry(0)->get($this->endpoint(), $this->params($query));
        } catch (\Throwable) {
            return [];
        }
        if (($rows = $this->parse($response)) === null) {
            return [];   // nothing is remembered about a query Commons could not answer
        }
        $this->remember($query, $rows);

        return $rows;
    }

    /**
     * One Commons file as a candidate, or null when it may not or should not be offered.
     *
     * @param array<string,mixed> $page
     * @return array<string,mixed>|null
     */
    private function shape(array $page): ?array
    {
        $info = (array) ($page['imageinfo'][0] ?? []);
        $url = (string) ($info['url'] ?? '');
        $width = (int) ($info['width'] ?? 0);
        $height = (int) ($info['height'] ?? 0);
        if (!str_starts_with($url, 'https://') || !in_array($info['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return null;
        }
        // Clear on a slide: a short side under 500 px or a long side under 800 px is soft once it fills the picture area.
        if (min($width, $height) < (int) config('pal_content.image.commons.min_side', 500) || max($width, $height) < (int) config('pal_content.image.commons.min_long_side', 800)) {
            return null;
        }

        $meta = (array) ($info['extmetadata'] ?? []);
        $value = fn (string $k): string => $this->text((string) ($meta[$k]['value'] ?? ''));
        if ($value('Restrictions') !== '') {
            return null;   // trademark, personality rights and the like: not for a lesson
        }
        $licence = $this->licence($value('LicenseShortName'));
        if ($licence === null) {
            return null;
        }
        $creator = mb_substr($value('Artist') ?: $value('Credit'), 0, 200);
        $needsCredit = !str_starts_with($licence, 'CC0') && !str_starts_with($licence, 'PDM');
        if ($needsCredit && $creator === '') {
            return null;   // a BY / BY-SA file must name its author, and this one does not
        }

        $title = $value('ObjectName') ?: trim((string) preg_replace('/\.[A-Za-z0-9]{3,4}$/', '', (string) preg_replace('/^File:/i', '', (string) ($page['title'] ?? ''))));
        $title = trim((string) preg_replace('/\s+/', ' ', str_replace('_', ' ', $title)));
        $categories = array_values(array_filter(array_map('trim', explode('|', $value('Categories')))));
        $haystack = $title . ' ' . implode(' ', $categories);
        if (preg_match(self::NOT_A_PHOTO, $haystack) || preg_match(self::NOT_FOR_SCHOOL, $haystack)) {
            return null;
        }

        $page_url = (string) ($info['descriptionurl'] ?? '');
        $label = 'CC ' . $licence;
        $attribution = $needsCredit
            ? '"' . $title . '" by ' . $creator . ', ' . $label . ', via Wikimedia Commons (' . $page_url . ').'
            : '"' . $title . '"' . ($creator !== '' ? ' by ' . $creator : '') . ', ' . (str_starts_with($licence, 'CC0') ? 'CC0 1.0 (public domain dedication)' : 'public domain') . ', via Wikimedia Commons (' . $page_url . ').';

        return [
            'external_id' => (string) ($page['pageid'] ?? ''),
            'url' => $url,
            'thumbnail_url' => (string) ($info['thumburl'] ?? $url),
            'title' => $title !== '' ? $title : null,
            'source_url' => $page_url !== '' ? $page_url : $url,
            'creator' => $creator !== '' ? $creator : null,
            'license' => $licence,
            'attribution' => $attribution,
            'provider' => 'wikimedia',
            'width' => $width,
            'height' => $height,
            // What the file is about: its categories and the first words of its own description, so the relevance check reads real content.
            'tags' => array_values(array_filter([...array_slice($categories, 0, 12), mb_substr($value('ImageDescription'), 0, 240)])),
        ];
    }

    /**
     * The licence in the form ImagePlanner accepts, or null for one it should not offer (GFDL, a non-commercial or no-derivatives licence, unclear).
     */
    public function licence(string $short): ?string
    {
        $s = trim($short);
        if ($s === '' || preg_match('/\b(NC|ND)\b|GFDL|GNU|fair use|non-?free|attribution only/i', $s)) {
            return null;
        }
        if (preg_match('/^CC0/i', $s)) {
            return 'CC0 1.0';
        }
        if (preg_match('/^(public domain|PD\b|PD-|no known copyright)/i', $s)) {
            return 'PDM 1.0';
        }
        if (preg_match('/^CC[ -]BY[ -]SA(?:[ -]([0-9.]+))?/i', $s, $m)) {
            return trim('BY-SA ' . ($m[1] ?? ''));
        }
        if (preg_match('/^CC[ -]BY(?:[ -]([0-9.]+))?\b/i', $s, $m)) {
            return trim('BY ' . ($m[1] ?? ''));
        }

        return null;
    }

    /** Commons metadata is HTML ("<a href=...>Jane</a>"); a credit is plain words. */
    private function text(string $html): string
    {
        return trim((string) preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    private function userAgent(): string
    {
        $configured = trim((string) config('pal_content.image.user_agent', ''));

        return $configured !== '' ? $configured : 'EduERP-StudyDeck/1.0 (+https://github.com/TrizERP/next_lms_erp; educational content, openly licensed images only)';
    }
}
