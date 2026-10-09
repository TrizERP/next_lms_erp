<?php

namespace App\Services\StudyDeck;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Turns a reviewed study-deck bundle into something that can be stored: every picture in the
 * shared object store, and a deck whose pictures are referenced by that store and nothing else.
 *
 * FILES ONLY. This class never touches the database (the content_master row is
 * ContentGenerationService's job), so what it does can be tested, dry-run and cleaned up without one.
 *
 * Pictures
 *   There is no media table in this application: content pictures live in the DigitalOcean Spaces
 *   disk and are referenced by URL (the same disk and `public/lms_content_file/` prefix as every
 *   other content file, and the only host the PPTX/PDF renderers accept for <img>). So a picture is
 *   "registered" by being stored there under a name made from its own hash:
 *
 *     public/lms_content_file/studydeck/<sha1>.<ext>
 *
 *   The hash name is the idempotency rule: the same picture is stored once however many times, and
 *   by however many decks, it is published. Everything else that would be a media row (source page,
 *   licence, creator, attribution, alt text, size, type) is already in the deck's own image record and
 *   is kept; the deck also gets an `assets` map and each image an `asset_id`.
 *
 * Failure safety
 *   Nothing is trusted until it is read back: each stored object must exist and have the size that
 *   was sent. Any problem throws, and `$created` (the objects THIS run created) can be removed with
 *   `cleanup()`, so a failed publish leaves no orphan.
 */
class StudyDeckPublisher
{
    public const IMAGE_DIR = 'public/lms_content_file/studydeck';

    private const MIME_EXT = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

    /** @var array<int,string> object keys this instance created (and therefore may remove again) */
    private array $created = [];

    public function __construct(private readonly Filesystem $disk, private readonly ?string $baseUrl = null)
    {
    }

    public static function make(): self
    {
        return new self(Storage::disk('digitalocean'));
    }

    /** The public base every stored picture's URL must start with, e.g. https://bucket.region.digitaloceanspaces.com/ */
    public function baseUrl(): string
    {
        // url('') is rejected by the S3 driver (empty key), so take the prefix of a real key's URL.
        return rtrim($this->baseUrl ?? substr($this->disk->url('x'), 0, -1), '/') . '/';
    }

    /** @return array<int,string> */
    public function created(): array
    {
        return $this->created;
    }

    /**
     * @param array<string,mixed> $deck SlideHtmlRenderer deck
     * @param string|null $imageDir folder holding the bundle's `images/<name>` files; null when the deck
     *                              already refers to stored pictures
     * @param bool $write false for a dry run: nothing is uploaded, missing pictures are only reported
     * @return array{deck:array<string,mixed>, html:string, assets:array<string,array<string,mixed>>, uploaded:array<int,string>, reused:array<int,string>, missing:array<int,string>}
     */
    public function prepare(array $deck, string $html, ?string $imageDir, bool $write = true): array
    {
        $assets = [];
        $uploaded = [];
        $reused = [];
        $missing = [];
        $rewrites = [];

        foreach ($deck['slides'] as $i => $slide) {
            $image = $slide['image'] ?? null;
            if (!is_array($image) || empty($image['url'])) {
                continue;
            }

            $url = (string) $image['url'];
            [$asset, $how] = $this->resolve($url, $image, $imageDir, $write);
            $key = $asset['sha1'] ?? $asset['path'];
            if (($asset['missing'] ?? false) === true) {
                $missing[$asset['path']] = $asset['path'];
            } elseif (!isset($assets[$key])) {
                $assets[$key] = $asset;
                $how === 'uploaded' ? $uploaded[] = $asset['path'] : $reused[] = $asset['path'];
            }

            $deck['slides'][$i]['image']['url'] = $asset['url'];
            $deck['slides'][$i]['image']['asset_id'] = $key;
            $rewrites[$url] = $asset['url'];
        }

        // The picture keys are hashes of the picture, so the map is stable and sorted.
        ksort($assets);
        $deck['assets'] = $assets;

        foreach ($rewrites as $from => $to) {
            $html = str_replace('src="' . htmlspecialchars($from, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"', 'src="' . $to . '"', $html);
        }

        $this->assertNoLocalReferences($deck, $html);

        return ['deck' => $deck, 'html' => $html, 'assets' => $assets, 'uploaded' => $uploaded, 'reused' => $reused, 'missing' => array_values($missing)];
    }

    /**
     * One picture: either a bundle file to store, or a picture that is already stored.
     *
     * @param array<string,mixed> $image
     * @return array{0:array<string,mixed>,1:string} the asset record, and 'uploaded' | 'reused'
     */
    private function resolve(string $url, array $image, ?string $imageDir, bool $write): array
    {
        // Already a stored picture: confirm it is really there rather than trusting the string.
        if (str_starts_with($url, $this->baseUrl())) {
            $path = substr($url, strlen($this->baseUrl()));
            if (!$this->disk->exists($path)) {
                return [['path' => $path, 'url' => $url, 'missing' => true], 'reused'];
            }

            return [$this->record($path, $url, (string) ($image['sha1'] ?? ''), (int) $this->disk->size($path), $image), 'reused'];
        }

        if (preg_match('~^(?:[a-z][a-z0-9+.-]*:|/|[A-Za-z]:)~i', $url) || str_contains($url, '..') || str_contains($url, '\\')) {
            throw new \RuntimeException("Image \"$url\" is not a bundle file or a stored picture; refusing to publish a deck that depends on it.");
        }
        if ($imageDir === null) {
            throw new \RuntimeException("Image \"$url\" is a bundle file but no bundle folder was given.");
        }

        // Bundle layout: the deck says `images/<name>`, the file is `<imageDir>/<name>`.
        $file = rtrim($imageDir, '/\\') . '/' . basename($url);
        $bytes = is_file($file) ? (string) file_get_contents($file) : '';
        if ($bytes === '') {
            throw new \RuntimeException("Image file {$file} is missing or empty.");
        }

        $sha = sha1($bytes);
        if (!empty($image['sha1']) && $image['sha1'] !== $sha) {
            throw new \RuntimeException("Image {$url} does not match the hash the deck recorded for it; the bundle is inconsistent.");
        }
        $info = @getimagesizefromstring($bytes);
        $mime = (string) ($info['mime'] ?? '');
        if (!isset(self::MIME_EXT[$mime])) {
            throw new \RuntimeException("Image {$url} is not a PNG, JPEG or WebP picture (found \"{$mime}\").");
        }

        $path = self::IMAGE_DIR . '/' . $sha . '.' . self::MIME_EXT[$mime];
        $publicUrl = $this->baseUrl() . $path;
        $exists = $this->disk->exists($path);

        if (!$exists && !$write) {
            return [$this->record($path, $publicUrl, $sha, strlen($bytes), $image + ['width' => $info[0] ?? 0, 'height' => $info[1] ?? 0], $mime), 'uploaded'];
        }
        if (!$exists) {
            $this->disk->put($path, $bytes, 'public');
            $this->created[] = $path;
            // Read it back: an upload that did not land must not become a deck that points at it.
            if (!$this->disk->exists($path) || (int) $this->disk->size($path) !== strlen($bytes)) {
                throw new \RuntimeException("Image {$path} was not stored correctly.");
            }
        }

        return [$this->record($path, $publicUrl, $sha, strlen($bytes), $image + ['width' => $info[0] ?? 0, 'height' => $info[1] ?? 0], $mime), $exists ? 'reused' : 'uploaded'];
    }

    /**
     * @param array<string,mixed> $image the deck's own record of the picture
     * @return array<string,mixed>
     */
    private function record(string $path, string $url, string $sha, int $bytes, array $image, ?string $mime = null): array
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return [
            'sha1' => $sha !== '' ? $sha : pathinfo($path, PATHINFO_FILENAME),
            'path' => $path,
            'url' => $url,
            'mime' => $mime ?? (array_flip(self::MIME_EXT)[$ext] ?? 'image/jpeg'),
            'filename' => basename($path),
            'bytes' => $bytes,
        ];
    }

    /**
     * A deck that works on another machine refers to nothing on this one.
     *
     * @param array<string,mixed> $deck
     */
    public function assertNoLocalReferences(array $deck, string $html): void
    {
        foreach ($deck['slides'] as $slide) {
            $url = (string) ($slide['image']['url'] ?? '');
            if ($url !== '' && !str_starts_with($url, $this->baseUrl())) {
                throw new \RuntimeException("Slide {$slide['n']}'s picture \"$url\" is not on the shared store.");
            }
        }

        $text = json_encode($deck, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n" . $html;
        // A drive letter ("C:\..." or "C:/..."), a UNC path, a review folder, a storage path or a local host.
        // The lookbehind keeps "https://" from looking like a drive.
        if (preg_match('~(?:(?<![A-Za-z0-9])[A-Za-z]:[\\\\/]|\\\\\\\\|/public/study-deck|study-deck/chapter-|storage/app|localhost|127\.0\.0\.1|file://)~i', $text, $m)) {
            throw new \RuntimeException('The deck still refers to a local path or host (' . $m[0] . '); refusing to publish it.');
        }
        if (preg_match('~src="(?!https://)[^"]*"~i', $html, $m)) {
            throw new \RuntimeException('The presentation still has a picture that is not on the shared store (' . $m[0] . ').');
        }
    }

    /** The name the presentation file is stored under: stable for a given deck, so a repeat finds the same row. */
    public static function filenameFor(string $chapterName, array $deck): string
    {
        $slug = trim(strtolower(preg_replace('/[^a-z0-9]+/i', '_', $chapterName) ?? ''), '_');
        $hash = substr(sha1((string) json_encode($deck, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)), 0, 12);

        return 'study_deck_' . substr($slug, 0, 80) . '_' . $hash . '.pptx';
    }

    /** Remove the pictures this run created (and no others). Used when a later step failed. */
    public function cleanup(): void
    {
        foreach ($this->created as $path) {
            try {
                $this->disk->delete($path);
            } catch (\Throwable) {
                // Best effort: the deck was never stored, so nothing points at it either way.
            }
        }
        $this->created = [];
    }
}
