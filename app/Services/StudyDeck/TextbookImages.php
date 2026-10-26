<?php

namespace App\Services\StudyDeck;

use App\Services\StudyDeck\Story\StoryImageLibrary;

/**
 * The pictures of a chapter's own textbook, as a FALLBACK for the picture search.
 *
 * Order of preference (see ImagePlanner): a relevant, licence-checked picture from the web first; this textbook picture only when
 * the search finds nothing suitable; a drawn diagram only when it can be built from verified content; otherwise no picture and
 * the slide says so.
 *
 * A picture is offered only from a curated MANIFEST: each entry says which page and chapter the picture is on, what it SHOWS (judged
 * from the picture itself and the caption beside it) and which words describe it. A picture is never chosen from its file name. The
 * manifest is data in the repository; the picture files are read from a folder named in configuration and are not copied anywhere
 * until a deck uses one (then it is stored once through the picture store, which de-duplicates by checksum).
 *
 * An entry is matched to a scene by the words they share. One shared word is not evidence (a scene about "water" would match every
 * picture in a water book), so at least MIN_SHARED different, specific words must be shared, or one of the entry's keyword PHRASES
 * must appear whole in what the scene is about.
 */
final class TextbookImages
{
    /** Different specific words a scene and a picture must share for the picture to be offered. */
    public const MIN_SHARED = 3;

    /** Words that name the whole book's subject rather than a picture's content, so they are no evidence of a match. */
    private const TOO_COMMON = ['water', 'waters', 'earth', 'life', 'World', 'chapter', 'picture', 'image', 'shows', 'showing', 'lesson', 'students', 'class'];

    /** @var array<int,array<string,mixed>> */
    private array $entries;

    /**
     * @param array<string,mixed> $manifest the decoded manifest: { source: {title, publisher, ...}, images: [ {file, page, chapter_id, caption, shows, keywords, usable} ] }
     * @param string $directory the folder the manifest's picture files are in
     */
    public function __construct(private readonly array $manifest, private readonly string $directory)
    {
        $this->entries = array_values(array_filter((array) ($manifest['images'] ?? []), fn ($e) => is_array($e) && !empty($e['file']) && !empty($e['usable'])));
    }

    /** The textbook pictures configured for the application, or null when none are (the feature is then simply off). */
    public static function fromConfig(): ?self
    {
        $manifest = (string) config('course_presentation_pilot.textbook_images.manifest', '');
        $dir = (string) config('course_presentation_pilot.textbook_images.directory', '');
        if ($manifest === '' || $dir === '' || !is_file($manifest) || !is_dir($dir)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($manifest), true);

        return is_array($data) ? new self($data, $dir) : null;
    }

    public function count(): int
    {
        return count($this->entries);
    }

    /** @return array<string,mixed> */
    public function source(): array
    {
        return (array) ($this->manifest['source'] ?? []);
    }

    /**
     * The textbook pictures of $chapterId that show what the scene is about, best first. Nothing here reads a file.
     *
     * @param array<int,string> $skip files already placed in this lesson
     * @return array<int,array{entry:array<string,mixed>,shared:array<int,string>}>
     */
    public function candidates(int $chapterId, string $query, string $purpose, string $teaches, array $skip = []): array
    {
        $scene = $this->specific(StoryImageLibrary::words($query . ' ' . $purpose . ' ' . $teaches));
        $sceneText = mb_strtolower($query . ' ' . $purpose . ' ' . $teaches);
        $out = [];
        foreach ($this->entries as $e) {
            if ((int) ($e['chapter_id'] ?? 0) !== $chapterId || in_array((string) $e['file'], $skip, true)) {
                continue;
            }
            $own = $this->specific(StoryImageLibrary::words(
                (string) ($e['caption'] ?? '') . ' ' . (string) ($e['shows'] ?? '') . ' ' . implode(' ', array_map('strval', (array) ($e['keywords'] ?? [])))
            ));
            $shared = array_values(array_intersect($scene, $own));
            // A whole keyword phrase named by the scene ("surface tension", "water strider") is strong evidence on its own.
            $phrase = array_values(array_filter(array_map('strval', (array) ($e['keywords'] ?? [])), fn ($k) => str_contains($k, ' ') && str_contains($sceneText, mb_strtolower($k))));
            // A NARROW scene (one or two specific words in all, like "overfishing" or "stepwell") that the picture's own description names
            // completely is evidence too. One shared word out of a wider scene is not: "rain" is in a picture of rainwater harvesting and in a
            // scene about droughts and floods, which are not the same subject.
            $narrow = $scene !== [] && count($scene) <= 2 && array_diff($scene, $own) === [];
            // Evidence is counted in different IDEAS, not word forms: "fish", "fishing" and "overfishing" are one.
            $ideas = count(array_unique(array_map([$this, 'stem'], $shared)));
            if ($ideas < self::MIN_SHARED && $phrase === [] && !$narrow) {
                continue;
            }
            $out[] = ['entry' => $e, 'shared' => $phrase !== [] ? array_values(array_unique(array_merge($shared, array_map('mb_strtolower', $phrase)))) : $shared];
        }
        usort($out, fn ($a, $b) => count($b['shared']) <=> count($a['shared']));

        return $out;
    }

    /** The picture file's bytes, or null when it is missing or is not a PNG, JPEG or WebP of a sensible size. @return array{bytes:string,mime:string,width:int,height:int}|null */
    public function read(array $entry): ?array
    {
        // The manifest names a file in the folder; it can never name a path outside it.
        $name = (string) ($entry['file'] ?? '');
        if ($name === '' || $name !== basename($name) || str_contains($name, '..')) {
            return null;
        }
        $path = rtrim($this->directory, '/\\') . DIRECTORY_SEPARATOR . $name;
        $bytes = is_file($path) ? (string) file_get_contents($path) : '';
        $info = $bytes !== '' && strlen($bytes) <= 8 * 1024 * 1024 ? @getimagesizefromstring($bytes) : false;
        if ($info === false || !in_array($info['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return null;
        }

        return ['bytes' => $bytes, 'mime' => (string) $info['mime'], 'width' => (int) $info[0], 'height' => (int) $info[1]];
    }

    /** A crude stem: common endings removed and the first six letters kept, so word forms of one idea compare equal. */
    private function stem(string $word): string
    {
        $w = mb_strtolower($word);
        foreach (['ing', 'ed', 'es', 's'] as $end) {
            if (mb_strlen($w) > 5 && str_ends_with($w, $end)) {
                $w = mb_substr($w, 0, -mb_strlen($end));
                break;
            }
        }

        return mb_substr($w, 0, 6);
    }

    /** @param array<int,string> $words @return array<int,string> */
    private function specific(array $words): array
    {
        $common = array_map('mb_strtolower', self::TOO_COMMON);

        return array_values(array_filter($words, fn ($w) => !in_array($w, $common, true)));
    }
}
