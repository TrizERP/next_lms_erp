<?php

namespace App\Services\StudyDeck\Story;

/**
 * What a story picture pass has learned and should not pay for twice: the verified pictures it accepted (so a later scene, or a later run, can
 * reuse a related one when the search fails), the picture an image model made for a given scene text (so a re-run does not generate it again),
 * and the searches that found nothing for a lasting reason.
 *
 * It holds RECORDS only: the title, licence, creator, attribution and source of a picture exactly as the search returned them. The bytes stay in the
 * picture store and are looked up by the address they were fetched from (ImagePlanner::reuse()), so this file is small and holds no picture.
 * It is one JSON file, per school and chapter; with no path it lives in memory for the run. A broken or unreadable file is treated as empty:
 * the library is a cache, never a source of truth.
 */
final class StoryImageLibrary
{
    /** @var array<string,array{record:array<string,mixed>,words:array<int,string>,uses:int}> key (the picture's source address) => entry */
    private array $entries = [];

    /** @var array<string,array<string,mixed>> prompt hash => the record of the picture generated for it */
    private array $generated = [];

    /** @var array<string,string> normalised query => why it found nothing */
    private array $misses = [];

    private bool $dirty = false;

    /** How many scenes one reused picture may serve: the same picture is not spread across unrelated scenes to raise a count. */
    public const MAX_USES = 2;

    private const STOP = ['the', 'and', 'for', 'with', 'that', 'this', 'from', 'into', 'their', 'they', 'them', 'what', 'when', 'where', 'which', 'about', 'your', 'have', 'has', 'are', 'was', 'were', 'but', 'not', 'can', 'its', 'how', 'why', 'one', 'two', 'photograph', 'picture', 'image', 'file', 'jpg', 'png'];

    public function __construct(private readonly ?string $path = null)
    {
        if ($path !== null && is_file($path)) {
            $data = json_decode((string) @file_get_contents($path), true);
            if (is_array($data)) {
                foreach ((array) ($data['entries'] ?? []) as $key => $e) {
                    if (is_array($e) && is_array($e['record'] ?? null)) {
                        $this->entries[(string) $key] = ['record' => $e['record'], 'words' => array_values((array) ($e['words'] ?? [])), 'uses' => 0];
                    }
                }
                $this->generated = array_filter((array) ($data['generated'] ?? []), 'is_array');
                $this->misses = array_filter((array) ($data['misses'] ?? []), 'is_string');
            }
        }
    }

    /** The meaningful words of a text, lower-cased, for matching a picture to a scene. @return array<int,string> */
    public static function words(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter($words, fn ($w) => mb_strlen($w) >= 4 && !in_array($w, self::STOP, true) && !ctype_digit($w))));
    }

    public function count(): int
    {
        return count($this->entries);
    }

    /**
     * Keep a picture that was found and accepted. Only a picture whose terms are on record is kept: a source page, a licence, and the address the
     * bytes came from (without which it could not be brought back).
     *
     * @param array<string,mixed> $record an ImagePlanner::find() record
     * @param array<int,string> $context extra words it was accepted for (the scene's concepts)
     */
    public function remember(array $record, array $context = []): void
    {
        $key = (string) ($record['source_image_url'] ?? '');
        if ($key === '' || empty($record['fetched_from']) || empty($record['source_url']) || empty($record['licence']) || ($record['type'] ?? '') !== 'photo') {
            return;
        }
        $words = self::words(implode(' ', [(string) ($record['title'] ?? ''), (string) ($record['alt'] ?? ''), (string) ($record['query'] ?? ''), implode(' ', $context)]));
        $keep = array_intersect_key($record, array_flip([
            'type', 'role', 'alt', 'caption', 'width', 'height', 'title', 'query', 'licence', 'source_url', 'creator', 'attribution', 'attribution_required',
            'source_image_url', 'fetched_from', 'provider', 'review',
        ]));
        $this->entries[$key] = ['record' => $keep, 'words' => array_slice($words, 0, 40), 'uses' => $this->entries[$key]['uses'] ?? 0];
        $this->dirty = true;
    }

    /**
     * Pictures that are related to a scene, best first: at least two meaningful words in common, and not already used the most a picture may be.
     *
     * @param array<int,string> $sceneWords
     * @param array<int,string> $exclude source addresses not to offer
     * @return array<int,array<string,mixed>> records
     */
    public function related(array $sceneWords, array $exclude = [], int $limit = 3): array
    {
        $scored = [];
        foreach ($this->entries as $key => $e) {
            if (in_array($key, $exclude, true) || $e['uses'] >= self::MAX_USES) {
                continue;
            }
            $overlap = count(array_intersect($sceneWords, $e['words']));
            if ($overlap >= 2) {
                $scored[] = [$overlap, $key];
            }
        }
        usort($scored, fn ($a, $b) => $b[0] <=> $a[0] ?: strcmp($a[1], $b[1]));

        return array_map(fn ($s) => $this->entries[$s[1]]['record'], array_slice($scored, 0, $limit));
    }

    /** Note that a reused picture now serves one more scene. */
    public function used(string $key): void
    {
        if (isset($this->entries[$key])) {
            $this->entries[$key]['uses']++;
        }
    }

    // ---- pictures an image model made

    /** @return array<string,mixed>|null the record of the picture generated for this prompt before */
    public function generatedFor(string $promptHash): ?array
    {
        return $this->generated[$promptHash] ?? null;
    }

    /** @param array<string,mixed> $record an ImagePlanner::generated() record */
    public function rememberGenerated(string $promptHash, array $record): void
    {
        $this->generated[$promptHash] = array_intersect_key($record, array_flip([
            'type', 'role', 'layout', 'match_level', 'alt', 'caption', 'query', 'width', 'height', 'licence', 'source_url', 'creator', 'attribution',
            'attribution_required', 'generated', 'review', 'fetched_from',
        ]));
        $this->dirty = true;
    }

    // ---- searches that found nothing

    public function missed(string $query): ?string
    {
        return $this->misses[$this->norm($query)] ?? null;
    }

    /** Only a lasting reason is kept: a timeout, a rate limit or a failed download says nothing about the query. */
    public function miss(string $query, string $why): void
    {
        if (preg_match('/search failed|time limit|could not be (checked|downloaded)|timed out|HTTP \d{3}|needs a relevance check/i', $why)) {
            return;
        }
        $this->misses[$this->norm($query)] = mb_substr($why, 0, 160);
        $this->dirty = true;
    }

    private function norm(string $q): string
    {
        return implode(' ', self::words($q)) ?: mb_strtolower(trim($q));
    }

    public function save(): void
    {
        if ($this->path === null || !$this->dirty) {
            return;
        }
        $dir = dirname($this->path);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return;
        }
        $entries = array_map(fn ($e) => ['record' => $e['record'], 'words' => $e['words']], $this->entries);
        $tmp = $this->path . '.tmp' . getmypid();
        if (@file_put_contents($tmp, json_encode(['version' => 1, 'entries' => $entries, 'generated' => $this->generated, 'misses' => $this->misses], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) !== false) {
            @rename($tmp, $this->path);
        }
        $this->dirty = false;
    }
}
