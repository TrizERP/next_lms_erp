<?php

namespace App\Services\StudyDeck;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moves study-deck pictures that were kept as files into the database, and (only when asked) removes the files.
 *
 * Where the files are
 *   - a review bundle:   <root>/chapter-<id>/out/images/<sha1>.<ext>   (lms:generate-study-deck, before pictures moved)
 *   - the player folder: <root>/chapter-<id>/images/<sha1>.<ext>       (--export-player)
 *   - the download cache: <root>/_image-cache/<sha1 of the source address>   (no extension)
 *
 * What it does, per chapter folder
 *   1. Reads the text files that name the pictures (deck.json, images.json, out/presentation.html) and the sha1 the
 *      deck recorded for each picture.
 *   2. For every picture file: checks it is a PNG/JPEG/WebP and matches the hash the deck recorded; finds the copy
 *      the school already holds (same bytes) or stores it; then READS IT BACK and compares size and SHA-256. A file
 *      that fails any step is reported and left alone.
 *   3. Rewrites `images/<name>` to `study-deck-image:<id>` in those text files (each written whole, to a temporary
 *      name, then moved over the original), and checks the result still parses and names no migrated file.
 *   4. With `cleanup`, deletes a picture file only when ALL of these hold: its database copy verifies again just
 *      before deleting, the file is unchanged since it was read, and no text file in the folder still names it. Empty
 *      picture folders are removed. Nothing else in the folder is touched.
 *
 * A picture file that no deck names is NOT stored and NOT deleted (it has no database copy to verify); it is reported.
 * `includeUnreferenced` stores it too, after which it counts as verified like any other.
 *
 * Idempotent: a second run finds the pictures already stored (same bytes, same school), changes no text file (the
 * paths are gone) and creates nothing.
 */
final class StudyDeckImageMigrator
{
    private const NAME = '~(?<![\w/\\\\])images\\\\?/([A-Za-z0-9._-]+\.(?:png|jpe?g|webp))~i';

    public function __construct(private readonly StudyDeckImages $images = new StudyDeckImages())
    {
    }

    /**
     * @param array<int,string> $roots folders holding chapter-<id>/ folders, or a chapter-<id>/ folder itself
     * @param array{chapter?:?int,tenant?:?int,include_unreferenced?:bool,cleanup?:bool,dry_run?:bool} $options
     * @param callable(string $level, string $message):void $say level: info | ok | warn | fail
     * @return array<string,mixed> counts and the lists behind them
     */
    public function run(array $roots, array $options, callable $say): array
    {
        // Before anything is read or changed: with no table there is nowhere safe to put a picture.
        if (!Schema::hasTable('study_deck_images') || !Schema::hasTable('study_deck_image_links')) {
            throw new \RuntimeException('The study_deck_images tables do not exist yet. Run: php artisan migrate --path=database/migrations/2026_10_09_160000_create_study_deck_images_tables.php');
        }

        $stats = [
            'folders' => 0, 'migrated' => 0, 'already_stored' => 0, 'would_migrate' => 0, 'unreferenced' => 0, 'failed' => 0,
            'rewritten' => 0, 'deleted' => 0, 'would_delete' => 0, 'removed_dirs' => 0,
            'unreferenced_files' => [], 'failures' => [],
        ];

        foreach ($roots as $root) {
            foreach ($this->folders($root, $options['chapter'] ?? null) as [$dir, $chapterId]) {
                $stats['folders']++;
                $this->folder($dir, $chapterId, $options, $stats, $say);
            }
        }

        return $stats;
    }

    /** @return array<int,array{0:string,1:?int}> folder and chapter id (null for the download cache) */
    private function folders(string $root, ?int $only): array
    {
        $root = rtrim($root, '/\\');
        if (!is_dir($root)) {
            return [];
        }
        $found = [];
        if (preg_match('~^chapter-(\d+)$~', basename($root), $m)) {
            $found[] = [$root, (int) $m[1]];
        } else {
            foreach (glob($root . '/chapter-*', GLOB_ONLYDIR) ?: [] as $dir) {
                if (preg_match('~^chapter-(\d+)$~', basename($dir), $m)) {
                    $found[] = [$dir, (int) $m[1]];
                }
            }
            if (is_dir($root . '/_image-cache')) {
                $found[] = [$root . '/_image-cache', null];
            }
        }

        return array_values(array_filter($found, fn ($f) => $only === null || $f[1] === null || $f[1] === $only));
    }

    /**
     * @param array<string,mixed> $o
     * @param array<string,mixed> $stats
     */
    private function folder(string $dir, ?int $chapterId, array $o, array &$stats, callable $say): void
    {
        $dry = !empty($o['dry_run']);
        $isCache = $chapterId === null;

        $imageDirs = $isCache ? [$dir] : array_values(array_filter([$dir . '/images', $dir . '/out/images'], 'is_dir'));
        $textFiles = $isCache ? [] : array_values(array_filter([$dir . '/deck.json', $dir . '/images.json', $dir . '/out/presentation.html'], 'is_file'));
        if (!$imageDirs && !$this->namesIn($textFiles)) {
            return;
        }

        $tenant = (int) ($o['tenant'] ?? 0);
        if ($tenant < 1 && $chapterId !== null) {
            $tenant = (int) DB::table('chapter_master')->where('id', $chapterId)->value('sub_institute_id');
        }
        if ($tenant < 1 && $isCache) {
            $tenant = StudyDeckImages::PLATFORM_TENANT;
        }
        $label = $isCache ? 'download cache ' . $dir : 'chapter ' . $chapterId . ' (' . $dir . ')';
        if ($tenant < 1) {
            $stats['failed']++;
            $stats['failures'][] = "$label: cannot tell which school it belongs to (no such chapter; pass --tenant)";
            $say('fail', "$label: cannot tell which school it belongs to; pass --tenant");

            return;
        }

        $referenced = $this->namesIn($textFiles);
        $recorded = $this->recordedHashes($dir . '/deck.json');
        $say('info', "$label, school $tenant: " . count($referenced) . ' picture(s) named, ' . array_sum(array_map(fn ($d) => count($this->filesIn($d)), $imageDirs)) . ' file(s) on disk');

        /** @var array<string,array{name:string,id:int,sha256:string,size:int}> $good files whose database copy verified */
        $good = [];
        /** @var array<string,int> $map name => id, for the pictures a text file names */
        $map = [];

        foreach ($imageDirs as $imageDir) {
            foreach ($this->filesIn($imageDir) as $path) {
                $name = basename($path);
                $bytes = (string) @file_get_contents($path);
                $size = strlen($bytes);
                $sha = hash('sha256', $bytes);
                $isNamed = isset($referenced[$name]);

                if (isset($recorded[$name]) && $recorded[$name] !== sha1($bytes)) {
                    $this->fail($stats, $say, "$path: does not match the hash the deck recorded for it");
                    continue;
                }

                $id = $size > 0 ? $this->images->idForBytes($bytes, $tenant) : null;
                if ($id !== null) {
                    $stats['already_stored']++;
                } elseif (!$isNamed && empty($o['include_unreferenced'])) {
                    $stats['unreferenced']++;
                    $stats['unreferenced_files'][] = $path;
                    continue;
                } elseif ($dry) {
                    $stats['would_migrate']++;
                    $say('info', "  would store $name (" . number_format($size) . ' bytes)');
                    // Once stored and verified it would be deletable like any other, so a dry run counts it.
                    $good[$path] = ['name' => $name, 'id' => 0, 'sha256' => $sha, 'size' => $size];
                    continue;
                } else {
                    try {
                        $put = $this->images->put($bytes, $tenant, $chapterId);
                    } catch (\Throwable $e) {
                        $this->fail($stats, $say, "$path: " . $e->getMessage());
                        continue;
                    }
                    $id = $put['id'];
                    $put['created'] ? $stats['migrated']++ : $stats['already_stored']++;
                    if ($put['created']) {
                        $say('ok', "  stored $name as " . StudyDeckImages::ref($id));
                    }
                }

                // Nothing is trusted until it has been read back.
                if (!$this->images->verify($id, $sha, $size)) {
                    $this->fail($stats, $say, "$path: the database copy (#$id) did not verify; the file is kept");
                    continue;
                }
                $good[$path] = ['name' => $name, 'id' => $id, 'sha256' => $sha, 'size' => $size];
                if ($isNamed) {
                    $map[$name] = $id;
                }
            }
        }

        if ($map && !$dry) {
            foreach ($textFiles as $file) {
                $this->rewrite($file, $map, $stats, $say);
            }
        }

        if (!empty($o['cleanup'])) {
            $this->cleanup($good, $textFiles, $imageDirs, $dry, $stats, $say);
        }
    }

    /**
     * @param array<string,int> $map
     * @param array<string,mixed> $stats
     */
    private function rewrite(string $file, array $map, array &$stats, callable $say): void
    {
        $before = (string) file_get_contents($file);
        $after = $before;
        foreach ($map as $name => $id) {
            $after = preg_replace('~(["\'])images\\\\?/' . preg_quote($name, '~') . '\1~', '$1' . StudyDeckImages::ref($id) . '$1', $after) ?? $after;
        }
        if ($after === $before) {
            return;
        }
        if (str_ends_with($file, '.json') && json_decode($after, true) === null && trim($after) !== 'null') {
            $this->fail($stats, $say, "$file: the rewritten file would not parse; it was not changed");

            return;
        }

        $tmp = $file . '.migrating';
        if (@file_put_contents($tmp, $after) !== strlen($after) || !@rename($tmp, $file)) {
            @unlink($tmp);
            $this->fail($stats, $say, "$file: could not be rewritten; it was not changed");

            return;
        }
        $stats['rewritten']++;
        $say('ok', '  rewrote ' . $file);
    }

    /**
     * @param array<string,array{name:string,id:int,sha256:string,size:int}> $good
     * @param array<int,string> $textFiles
     * @param array<int,string> $imageDirs
     * @param array<string,mixed> $stats
     */
    private function cleanup(array $good, array $textFiles, array $imageDirs, bool $dry, array &$stats, callable $say): void
    {
        $stillNamed = $this->namesIn($textFiles);
        if ($dry) {
            // A real run rewrites the text files first, so the pictures it migrates are no longer named afterwards.
            foreach ($good as $g) {
                unset($stillNamed[$g['name']]);
            }
        }

        foreach ($good as $path => $g) {
            if (isset($stillNamed[$g['name']])) {
                $say('warn', "  keeping $path: a deck file still names it");
                continue;
            }
            if ($dry) {
                $stats['would_delete']++;
                $say('info', "  would delete $path");
                continue;
            }
            // The last look before deleting: unchanged on disk, and the database copy still verifies.
            if (!is_file($path) || is_link($path) || hash_file('sha256', $path) !== $g['sha256'] || !$this->images->verify($g['id'], $g['sha256'], $g['size'])) {
                $this->fail($stats, $say, "$path: changed or no longer verifies; it was not deleted");
                continue;
            }
            if (@unlink($path)) {
                $stats['deleted']++;
            } else {
                $this->fail($stats, $say, "$path: could not be deleted");
            }
        }

        if (!$dry) {
            foreach ($imageDirs as $d) {
                if (is_dir($d) && count(scandir($d) ?: []) <= 2 && @rmdir($d)) {
                    $stats['removed_dirs']++;
                    $say('ok', "  removed empty folder $d");
                }
            }
        }
    }

    /**
     * @param array<string,mixed> $stats
     */
    private function fail(array &$stats, callable $say, string $message): void
    {
        $stats['failed']++;
        $stats['failures'][] = $message;
        $say('fail', '  ' . $message);
    }

    /**
     * The sha1 a deck recorded for each of its pictures, by file name.
     *
     * @return array<string,string>
     */
    private function recordedHashes(string $deckFile): array
    {
        $deck = is_file($deckFile) ? json_decode((string) file_get_contents($deckFile), true) : null;
        $out = [];
        foreach (['slides', 'sections'] as $part) {
            foreach ((array) ($deck[$part] ?? []) as $slide) {
                $url = (string) ($slide['image']['url'] ?? '');
                if (preg_match(self::NAME, $url, $m) && !empty($slide['image']['sha1'])) {
                    $out[$m[1]] = (string) $slide['image']['sha1'];
                }
            }
        }

        return $out;
    }

    /**
     * The picture file names the text files still name.
     *
     * @param array<int,string> $files
     * @return array<string,true>
     */
    private function namesIn(array $files): array
    {
        $names = [];
        foreach ($files as $file) {
            if (is_file($file) && preg_match_all(self::NAME, (string) file_get_contents($file), $m)) {
                foreach ($m[1] as $name) {
                    $names[$name] = true;
                }
            }
        }

        return $names;
    }

    /** @return array<int,string> regular files directly inside a folder (no links, no dot files) */
    private function filesIn(string $dir): array
    {
        $out = [];
        foreach (scandir($dir) ?: [] as $entry) {
            $path = $dir . '/' . $entry;
            if ($entry[0] !== '.' && is_file($path) && !is_link($path)) {
                $out[] = $path;
            }
        }
        sort($out);

        return $out;
    }
}
