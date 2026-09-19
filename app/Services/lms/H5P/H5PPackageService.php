<?php

namespace App\Services\lms\H5P;

use App\Models\lms\h5p\H5pDragDrop;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Reads and writes .h5p packages for the drag-and-drop type.
 *
 * A .h5p file is a zip with a fixed layout:
 *
 *   h5p.json              manifest: title, mainLibrary, dependency closure
 *   content/content.json  the content type's params
 *   content/images/...    media the params reference by relative path
 *
 * This service writes that layout from our rows and reads it back. It does NOT
 * bundle the library CODE (the `H5P.DragQuestion/` directory an official export
 * contains), because this ERP does not host the library -- see the header of
 * config/h5p_libraries.php. The manifest declares the dependency closure, so an
 * importing host resolves the libraries from its own store, which is how a
 * library-less export is meant to work. A host with no H5P.DragQuestion
 * installed will say so; it will not fail obscurely.
 *
 * MEDIA. Params hold absolute URLs while the content lives here, because that
 * is what this product's player needs. Inside a package they must be relative
 * to `content/`, or the package only works while this server is reachable. So
 * export rewrites every image path to `images/<file>` and copies the bytes in;
 * import does the reverse, writing media into public storage and rewriting the
 * paths back to URLs. Anything that cannot be fetched is left as its absolute
 * URL and reported in `warnings` rather than silently dropped.
 *
 * IMPORT IS UNTRUSTED INPUT. Every entry is bounded and path-checked before
 * extraction (see safeEntryPath and the limits in config/h5p_libraries.php):
 * a zip entry naming `../../public/index.php` is rejected, not written.
 */
class H5PPackageService
{
    public function __construct(private readonly H5PDragQuestionBuilder $builder)
    {
    }

    // -----------------------------------------------------------------------
    // Export
    // -----------------------------------------------------------------------

    /**
     * Write a .h5p package for one task and return the local path to it.
     *
     * The caller is responsible for streaming and deleting the file
     * (`response()->download(...)->deleteFileAfterSend()`).
     *
     * @return array{path: string, filename: string, warnings: list<string>}
     */
    public function export(H5pDragDrop $task): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Export needs the PHP zip extension, which is not enabled on this server.');
        }

        $library = $this->library();
        $params = $this->builder->build($task);
        $warnings = [];

        $workspace = $this->makeWorkspace('export');
        $mediaDir = $workspace . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'images';
        $this->ensureDirectory($mediaDir);

        // Rewrite every media reference to a package-relative path, copying
        // the bytes in as we go.
        $params = $this->rewriteMedia(
            $params,
            function (string $source) use ($mediaDir, &$warnings): ?string {
                $bytes = $this->readMedia($source);
                if ($bytes === null) {
                    $warnings[] = 'Could not bundle ' . $source . ' -- the package keeps the original link.';

                    return null;
                }

                $name = $this->mediaFilename($source, $bytes);
                file_put_contents($mediaDir . DIRECTORY_SEPARATOR . $name, $bytes);

                return 'images/' . $name;
            }
        );

        $title = trim((string) $task->title) ?: (string) config('h5p_libraries.package.extra_title_fallback');

        $manifest = [
            'title' => $title,
            'language' => (string) config('h5p_libraries.package.language', 'en'),
            'mainLibrary' => $library['machine_name'],
            'embedTypes' => $library['embed_types'],
            'license' => (string) config('h5p_libraries.package.default_license', 'U'),
            'defaultLanguage' => (string) config('h5p_libraries.package.language', 'en'),
            // The main library FIRST, then its closure -- an importing host
            // installs them in this order.
            'preloadedDependencies' => array_merge(
                [[
                    'machineName' => $library['machine_name'],
                    'majorVersion' => $library['major_version'],
                    'minorVersion' => $library['minor_version'],
                ]],
                $library['dependencies']
            ),
            'editorDependencies' => $library['editor_dependencies'],
            // Provenance. Not read by any host; it is what makes a package
            // found on disk months later traceable back to a row here.
            'authors' => [[
                'name' => 'EduERP',
                'role' => (string) config('h5p_libraries.package.author_role', 'Author'),
            ]],
            'source' => 'eduerp:h5p_drag_drop:' . $task->id,
        ];

        file_put_contents(
            $workspace . DIRECTORY_SEPARATOR . 'h5p.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
        file_put_contents(
            $workspace . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'content.json',
            json_encode($params, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        $filename = Str::slug($title ?: 'drag-and-drop') . '-' . $task->id . '.h5p';
        $archivePath = $this->makeWorkspace('archive') . DIRECTORY_SEPARATOR . $filename;

        $zip = new ZipArchive();
        if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the .h5p archive.');
        }
        $this->addDirectoryToZip($zip, $workspace, '');
        $zip->close();

        $this->deleteDirectory($workspace);

        return ['path' => $archivePath, 'filename' => $filename, 'warnings' => $warnings];
    }

    // -----------------------------------------------------------------------
    // Import
    // -----------------------------------------------------------------------

    /**
     * Read an uploaded .h5p package into row payloads for one chapter.
     *
     * Nothing is written to the database here -- the controller owns that, so
     * the whole import stays inside one transaction with the audit entries.
     *
     * @return array{title: string, task: array<string,mixed>, elements: list<array<string,mixed>>, zones: list<array<string,mixed>>, warnings: list<string>}
     */
    public function import(UploadedFile $file, int|string|null $subInstituteId): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Import needs the PHP zip extension, which is not enabled on this server.');
        }

        $limits = (array) config('h5p_libraries.import', []);

        if ($file->getSize() > (int) ($limits['max_package_bytes'] ?? 67108864)) {
            throw new RuntimeException('That package is larger than the import limit.');
        }

        $zip = new ZipArchive();
        if ($zip->open($file->getRealPath()) !== true) {
            throw new RuntimeException('That file is not a readable .h5p package.');
        }

        try {
            if ($zip->numFiles > (int) ($limits['max_entries'] ?? 2000)) {
                throw new RuntimeException('That package contains too many files to import.');
            }

            // Guard against a zip bomb before reading anything out of it.
            $uncompressed = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $uncompressed += (int) ($stat['size'] ?? 0);
            }
            if ($uncompressed > (int) ($limits['max_uncompressed_bytes'] ?? 268435456)) {
                throw new RuntimeException('That package expands to more than the import limit allows.');
            }

            $manifest = $this->readJsonEntry($zip, 'h5p.json');
            $content = $this->readJsonEntry($zip, 'content/content.json');

            if ($manifest === null || $content === null) {
                throw new RuntimeException('That package is missing h5p.json or content/content.json.');
            }

            $expected = $this->library()['machine_name'];
            $mainLibrary = (string) ($manifest['mainLibrary'] ?? '');
            if ($mainLibrary !== $expected) {
                throw new RuntimeException(
                    'That package is ' . ($mainLibrary ?: 'an unknown type') . '. This importer accepts ' . $expected . '.'
                );
            }

            $warnings = [];
            $allowed = array_map('strtolower', (array) ($limits['allowed_media_extensions'] ?? []));

            // Pull media out first so the path rewrite below has URLs to use.
            $mediaUrls = $this->extractMedia($zip, $allowed, $subInstituteId, $warnings);

            $content = $this->rewriteMedia($content, function (string $source) use ($mediaUrls, &$warnings): ?string {
                $key = ltrim($source, '/');
                if (isset($mediaUrls[$key])) {
                    return $mediaUrls[$key];
                }
                // An absolute URL in an imported package is left alone: it is
                // already reachable, and rewriting it would break it.
                if (str_starts_with($source, 'http://') || str_starts_with($source, 'https://')) {
                    return null;
                }
                $warnings[] = 'The package references ' . $source . ', which it does not contain.';

                return null;
            });

            $parsed = $this->builder->parse($content);
            $parsed['title'] = trim((string) ($manifest['title'] ?? '')) ?: 'Imported drag and drop';
            $parsed['warnings'] = $warnings;

            return $parsed;
        } finally {
            $zip->close();
        }
    }

    // -----------------------------------------------------------------------
    // Media
    // -----------------------------------------------------------------------

    /**
     * Walk the two places a DragQuestion params object holds an image path and
     * apply $rewrite to each. A null return leaves the path untouched.
     *
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    private function rewriteMedia(array $params, callable $rewrite): array
    {
        $backgroundPath = $params['question']['settings']['background']['path'] ?? null;
        if (is_string($backgroundPath) && $backgroundPath !== '') {
            $replacement = $rewrite($backgroundPath);
            if ($replacement !== null) {
                $params['question']['settings']['background']['path'] = $replacement;
            }
        }

        $elements = $params['question']['task']['elements'] ?? [];
        if (is_array($elements)) {
            foreach ($elements as $index => $element) {
                $path = $element['type']['params']['file']['path'] ?? null;
                if (is_string($path) && $path !== '') {
                    $replacement = $rewrite($path);
                    if ($replacement !== null) {
                        $elements[$index]['type']['params']['file']['path'] = $replacement;
                    }
                }
            }
            $params['question']['task']['elements'] = $elements;
        }

        return $params;
    }

    /**
     * Read the bytes behind a stored media reference.
     *
     * Local-first: a path under this app's public directory is read from disk
     * rather than fetched over HTTP, which avoids a server calling itself
     * (and failing, on a host that does not resolve its own name).
     */
    private function readMedia(string $source): ?string
    {
        $path = parse_url($source, PHP_URL_PATH) ?: $source;
        $localCandidate = public_path(ltrim($path, '/'));

        if (is_file($localCandidate) && is_readable($localCandidate)) {
            $bytes = @file_get_contents($localCandidate);

            return $bytes === false ? null : $bytes;
        }

        if (! str_starts_with($source, 'http://') && ! str_starts_with($source, 'https://')) {
            return null;
        }

        try {
            $context = stream_context_create(['http' => ['timeout' => 10, 'method' => 'GET']]);
            $bytes = @file_get_contents($source, false, $context);

            return $bytes === false ? null : $bytes;
        } catch (\Throwable $e) {
            Log::warning('H5P export could not fetch media', ['source' => $source, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Extract `content/images/*` into public storage and return
     * package-relative path => public URL.
     *
     * @param  list<string>  $allowedExtensions
     * @param  list<string>  $warnings
     * @return array<string,string>
     */
    private function extractMedia(ZipArchive $zip, array $allowedExtensions, int|string|null $subInstituteId, array &$warnings): array
    {
        $destination = public_path('h5p_content/drag_drop');
        $urls = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = (string) ($stat['name'] ?? '');

            if ($name === '' || str_ends_with($name, '/') || ! str_starts_with($name, 'content/')) {
                continue;
            }
            if ($name === 'content/content.json') {
                continue;
            }
            if ($this->safeEntryPath($name) === null) {
                $warnings[] = 'Skipped an entry with an unsafe path.';
                continue;
            }

            $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
            if (! in_array($extension, $allowedExtensions, true)) {
                $warnings[] = 'Skipped ' . $name . ' -- ' . ($extension ?: 'that file type') . ' is not an allowed media type.';
                continue;
            }

            $bytes = $zip->getFromIndex($i);
            if ($bytes === false) {
                $warnings[] = 'Could not read ' . $name . ' out of the package.';
                continue;
            }

            // Content-addressed, so importing the same package twice does not
            // fill the disk with duplicate copies of the same image.
            $filename = 'dd_' . substr(sha1($bytes), 0, 16) . '.' . $extension;
            $target = $destination . DIRECTORY_SEPARATOR . $filename;
            if (! is_file($target)) {
                // Created on first write, not up front: a package with no media
                // should not leave an empty directory behind in public/.
                $this->ensureDirectory($destination);
                file_put_contents($target, $bytes);
            }

            // Key by the path as content.json references it: relative to
            // `content/`, so `content/images/a.png` is referenced `images/a.png`.
            $urls[substr($name, strlen('content/'))] = asset('h5p_content/drag_drop/' . $filename);
        }

        return $urls;
    }

    private function mediaFilename(string $source, string $bytes): string
    {
        $path = parse_url($source, PHP_URL_PATH) ?: $source;
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION)) ?: 'jpg';

        return 'dd_' . substr(sha1($bytes), 0, 16) . '.' . $extension;
    }

    // -----------------------------------------------------------------------
    // Zip / filesystem helpers
    // -----------------------------------------------------------------------

    /** @return array<string,mixed>|null */
    private function readJsonEntry(ZipArchive $zip, string $entry): ?array
    {
        $raw = $zip->getFromName($entry);
        if ($raw === false) {
            return null;
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Reject any entry name that would escape the extraction root -- absolute
     * paths, drive letters, and anything containing a `..` segment.
     */
    private function safeEntryPath(string $name): ?string
    {
        $normalised = str_replace('\\', '/', $name);

        if (str_starts_with($normalised, '/') || preg_match('/^[A-Za-z]:/', $normalised)) {
            return null;
        }
        foreach (explode('/', $normalised) as $segment) {
            if ($segment === '..') {
                return null;
            }
        }

        return $normalised;
    }

    private function addDirectoryToZip(ZipArchive $zip, string $directory, string $prefix): void
    {
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = $directory . DIRECTORY_SEPARATOR . $entry;
            $relative = $prefix === '' ? $entry : $prefix . '/' . $entry;

            if (is_dir($full)) {
                $zip->addEmptyDir($relative);
                $this->addDirectoryToZip($zip, $full, $relative);
            } else {
                $zip->addFile($full, $relative);
            }
        }
    }

    private function makeWorkspace(string $kind): string
    {
        $path = storage_path('app/h5p_packages/' . $kind . '_' . Str::random(16));
        $this->ensureDirectory($path . DIRECTORY_SEPARATOR . 'content');

        return $path;
    }

    private function ensureDirectory(string $path): void
    {
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    private function deleteDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = $path . DIRECTORY_SEPARATOR . $entry;
            is_dir($full) ? $this->deleteDirectory($full) : @unlink($full);
        }
        @rmdir($path);
    }

    /** @return array<string,mixed> */
    private function library(): array
    {
        $library = config('h5p_libraries.libraries.drag_and_drop');
        if (! is_array($library)) {
            throw new RuntimeException('H5P.DragQuestion is not registered in config/h5p_libraries.php.');
        }

        return $library;
    }
}
