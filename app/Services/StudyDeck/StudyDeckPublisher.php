<?php

namespace App\Services\StudyDeck;

/**
 * Turns a reviewed study-deck bundle into something that can be stored: every picture in the database,
 * and a deck whose pictures are referenced by that store and nothing else.
 *
 * It never touches content_master (that is ContentGenerationService's job) and never writes a file or an
 * object-store key. The only thing it writes is a picture into `study_deck_images`, through StudyDeckImages.
 *
 * Pictures
 *   A deck made by the current pipeline already refers to its pictures by `study-deck-image:<id>`: they were
 *   stored while the deck was generated. Publishing confirms each one is there and that the deck's school may
 *   see it. A bundle written before that (its deck says `images/<name>` and the file sits in the bundle's
 *   folder) is brought in here: the file is stored, the reference replaces the path in the deck and in the
 *   presentation, and the folder is not needed again.
 *
 *   Everything else that would be a media row (source page, licence, creator, attribution, alt text) is already
 *   in the deck's own image record and is kept; the deck also gets an `assets` map (id, checksum, type, size,
 *   dimensions) and each image an `asset_id`.
 *
 * Failure safety
 *   A picture brought in from a bundle is read back from the database (size and checksum) before the deck is
 *   allowed to point at it. Any problem throws, and the pictures THIS run stored for the first time can be
 *   removed with `cleanup()` (only those no deck uses), so a failed publish leaves no orphan.
 */
class StudyDeckPublisher
{
    /**
     * Where a stored item keeps the parts that can carry a picture: a study deck's `slides`, and a study document's
     * (revision notes, remedial class, classroom activities) `sections`. Both name the picture the same way (`image`).
     */
    private const PARTS = ['slides', 'sections'];

    /** What a bundle picture is called in a dry run, when it has no id yet. */
    private const PENDING = '~^study-deck-image:new-[0-9a-f]{12}$~';

    /** @var array<int,int> ids of the pictures this instance stored for the first time (and so may remove again) */
    private array $created = [];

    public function __construct(private readonly StudyDeckImages $images, private readonly int $tenant)
    {
    }

    public static function make(int $tenant): self
    {
        return new self(new StudyDeckImages(), $tenant);
    }

    /** @return array<int,int> */
    public function created(): array
    {
        return $this->created;
    }

    /**
     * @param array<string,mixed> $deck SlideHtmlRenderer deck (or a study document)
     * @param string|null $imageDir folder holding a legacy bundle's `images/<name>` files; null when the deck
     *                              already refers to stored pictures
     * @param bool $write false for a dry run: nothing is stored, bundle pictures are only checked
     * @return array{deck:array<string,mixed>, html:string, assets:array<string,array<string,mixed>>, uploaded:array<int,string>, reused:array<int,string>, missing:array<int,string>}
     */
    public function prepare(array $deck, string $html, ?string $imageDir, bool $write = true): array
    {
        $assets = [];
        $stored = [];
        $reused = [];
        $missing = [];
        $rewrites = [];

        foreach (self::PARTS as $part) {
            foreach ($deck[$part] ?? [] as $i => $slide) {
                $image = $slide['image'] ?? null;
                if (!is_array($image) || empty($image['url'])) {
                    continue;
                }

                $url = (string) $image['url'];
                [$asset, $how] = $this->resolve($url, $image, $deck, $imageDir, $write);
                if (($asset['missing'] ?? false) === true) {
                    $missing[$url] = $url;
                    continue;
                }

                $key = $asset['sha256'];
                if (!isset($assets[$key])) {
                    $assets[$key] = $asset;
                    $how === 'stored' ? $stored[] = $asset['ref'] : $reused[] = $asset['ref'];
                }

                $deck[$part][$i]['image']['url'] = $asset['ref'];
                $deck[$part][$i]['image']['asset_id'] = $key;
                $rewrites[$url] = $asset['ref'];
            }
        }

        // The keys are checksums of the pictures, so the map is stable and sorted.
        ksort($assets);
        $deck['assets'] = $assets;

        foreach ($rewrites as $from => $to) {
            if ($from !== $to) {
                $html = str_replace('src="' . htmlspecialchars($from, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"', 'src="' . $to . '"', $html);
            }
        }

        $this->assertNoLocalReferences($deck, $html, !$write);

        return ['deck' => $deck, 'html' => $html, 'assets' => $assets, 'uploaded' => $stored, 'reused' => $reused, 'missing' => array_values($missing)];
    }

    /**
     * The ids of the stored pictures a prepared deck uses.
     *
     * @param array<string,mixed> $prepared the result of prepare()
     * @return array<int,int>
     */
    public static function imageIds(array $prepared): array
    {
        return array_values(array_filter(array_map(fn ($a) => (int) ($a['image_id'] ?? 0), $prepared['assets'])));
    }

    /**
     * One picture: either a stored picture to confirm, or a bundle file to store.
     *
     * @param array<string,mixed> $image
     * @param array<string,mixed> $deck
     * @return array{0:array<string,mixed>,1:string} the asset record, and 'stored' | 'reused'
     */
    private function resolve(string $url, array $image, array $deck, ?string $imageDir, bool $write): array
    {
        // Already a stored picture: confirm it is really there, and that this school may use it.
        if (($id = StudyDeckImages::idFromRef($url)) !== null) {
            $meta = $this->images->visibleMeta($id, $this->tenant);

            return $meta === null ? [['ref' => $url, 'missing' => true], 'reused'] : [$this->record($meta), 'reused'];
        }

        if (preg_match('~^(?:[a-z][a-z0-9+.-]*:|/|[A-Za-z]:)~i', $url) || str_contains($url, '..') || str_contains($url, '\\')) {
            throw new \RuntimeException("Image \"$url\" is not a bundle file or a stored picture; refusing to publish a deck that depends on it.");
        }
        if ($imageDir === null) {
            throw new \RuntimeException("Image \"$url\" is a bundle file but no bundle folder was given.");
        }

        // Legacy bundle layout: the deck says `images/<name>`, the file is `<imageDir>/<name>`.
        $file = rtrim($imageDir, '/\\') . '/' . basename($url);
        $bytes = is_file($file) ? (string) file_get_contents($file) : '';
        if ($bytes === '') {
            throw new \RuntimeException("Image file {$file} is missing or empty.");
        }
        if (!empty($image['sha1']) && $image['sha1'] !== sha1($bytes)) {
            throw new \RuntimeException("Image {$url} does not match the hash the deck recorded for it; the bundle is inconsistent.");
        }
        try {
            $info = $this->images->inspect($bytes);
        } catch (\InvalidArgumentException $e) {
            throw new \RuntimeException("Image {$url}: " . $e->getMessage());
        }
        $sha256 = hash('sha256', $bytes);

        if (!$write) {
            $existing = $this->images->idForBytes($bytes, $this->tenant);
            $asset = $this->record(['id' => $existing ?? 0, 'sha256' => $sha256, 'mime' => $info['mime'], 'format' => $info['format'], 'bytes' => strlen($bytes), 'width' => $info['width'], 'height' => $info['height']]);
            if ($existing === null) {
                $asset['ref'] = 'study-deck-image:new-' . substr($sha256, 0, 12);
            }

            return [$asset, $existing === null ? 'stored' : 'reused'];
        }

        $put = $this->images->put($bytes, $this->tenant, isset($deck['chapter']['id']) ? (int) $deck['chapter']['id'] : null);
        if ($put['created']) {
            $this->created[] = $put['id'];
        }
        // Read it back: a write that did not land must not become a deck that points at it.
        if (!$this->images->verify($put['id'], $put['sha256'], $put['bytes'])) {
            throw new \RuntimeException("Image {$url} was not stored correctly.");
        }

        return [$this->record($put), $put['created'] ? 'stored' : 'reused'];
    }

    /**
     * @param array<string,mixed> $meta id, sha256, mime, format, bytes, width, height
     * @return array<string,mixed>
     */
    private function record(array $meta): array
    {
        return [
            'image_id' => (int) $meta['id'],
            'ref' => StudyDeckImages::ref((int) $meta['id']),
            'sha256' => (string) $meta['sha256'],
            'mime' => (string) $meta['mime'],
            'format' => (string) $meta['format'],
            'bytes' => (int) $meta['bytes'],
            'width' => (int) $meta['width'],
            'height' => (int) $meta['height'],
        ];
    }

    /**
     * A deck that works on another machine refers to nothing on this one.
     *
     * @param array<string,mixed> $deck
     * @param bool $allowPending a dry run, where a bundle picture has no id yet
     */
    public function assertNoLocalReferences(array $deck, string $html, bool $allowPending = false): void
    {
        foreach (self::PARTS as $part) {
            foreach ($deck[$part] ?? [] as $slide) {
                $url = (string) ($slide['image']['url'] ?? '');
                if ($url !== '' && StudyDeckImages::idFromRef($url) === null && !($allowPending && preg_match(self::PENDING, $url))) {
                    throw new \RuntimeException(($part === 'slides' ? 'Slide' : 'Section') . " {$slide['n']}'s picture \"$url\" is not a stored picture reference.");
                }
            }
        }

        $text = json_encode($deck, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n" . $html;
        // A drive letter ("C:\..." or "C:/..."), a UNC path, a review folder, a storage path or a local host.
        // The lookbehind keeps "https://" from looking like a drive.
        if (preg_match('~(?:(?<![A-Za-z0-9])[A-Za-z]:[\\\\/]|\\\\\\\\|/public/study-deck|study-deck/chapter-|storage/app|localhost|127\.0\.0\.1|file://)~i', $text, $m)) {
            throw new \RuntimeException('The deck still refers to a local path or host (' . $m[0] . '); refusing to publish it.');
        }
        if (preg_match('~src="(?!study-deck-image:)[^"]*"~i', $html, $m)) {
            throw new \RuntimeException('The presentation still has a picture that is not a stored picture (' . $m[0] . ').');
        }
    }

    /** The name the presentation file is stored under: stable for a given deck, so a repeat finds the same row. */
    public static function filenameFor(string $chapterName, array $deck): string
    {
        $slug = trim(strtolower(preg_replace('/[^a-z0-9]+/i', '_', $chapterName) ?? ''), '_');
        $hash = substr(sha1((string) json_encode($deck, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)), 0, 12);

        return 'study_deck_' . substr($slug, 0, 80) . '_' . $hash . '.pptx';
    }

    /** Remove the pictures this run stored for the first time, unless a deck uses them. Used when a later step failed. */
    public function cleanup(): void
    {
        if ($this->created) {
            try {
                $this->images->deleteUnlinked($this->created);
            } catch (\Throwable) {
                // Best effort: the deck was never stored, so nothing points at them either way.
            }
        }
        $this->created = [];
    }
}
