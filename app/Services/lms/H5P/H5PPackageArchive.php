<?php

namespace App\Services\lms\H5P;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * The .h5p file format, minus any one content type's opinion about it.
 *
 * WHY THIS WAS EXTRACTED
 *
 * H5PPackageService was written for drag and drop and did two things at once:
 * it knew the zip format, and it knew H5P.DragQuestion. When the three
 * text-passage types arrived, only the second half differed -- the layout, the
 * zip-bomb bounds, the path-traversal guard, the local-first media read and the
 * content-addressed media naming are properties of the FORMAT, and having two
 * copies of a security guard is how one copy ends up patched and the other not.
 *
 * So the format lives here and each subclass supplies its content type's
 * manifest, params and media map:
 *
 *   H5PPackageService       H5P.DragQuestion  (drag and drop)
 *   H5PTextPackageService   H5P.DragText / H5P.Blanks / H5P.MarkTheWords
 *
 * A .h5p file is a zip with a fixed layout:
 *
 *   h5p.json              manifest: title, mainLibrary, dependency closure
 *   content/content.json  the content type's params
 *   content/images/...    media the params reference by relative path
 *
 * Neither subclass bundles the library CODE (the `H5P.DragQuestion/` directory
 * an official export contains), because this ERP does not host the libraries --
 * see the header of config/h5p_libraries.php. The manifest declares the
 * dependency closure, so an importing host resolves the libraries from its own
 * store, which is how a library-less export is meant to work.
 *
 * IMPORT IS UNTRUSTED INPUT. Every entry is bounded and path-checked before
 * extraction: a zip entry naming `../../public/index.php` is rejected, not
 * written. That rule is enforced here, once, for every content type.
 */
abstract class H5PPackageArchive
{
    /** @throws RuntimeException when the server cannot do zip at all. */
    protected function requireZip(string $verb): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException(
                ucfirst($verb) . ' needs the PHP zip extension, which is not enabled on this server.'
            );
        }
    }

    /** @return array<string,mixed> */
    protected function importLimits(): array
    {
        return (array) config('h5p_libraries.import', []);
    }

    /**
     * One registered library's manifest block.
     *
     * @return array<string,mixed>
     */
    protected function library(string $key): array
    {
        $library = config('h5p_libraries.libraries.' . $key);
        if (! is_array($library)) {
            throw new RuntimeException('H5P library "' . $key . '" is not registered in config/h5p_libraries.php.');
        }

        return $library;
    }

    /**
     * The h5p.json a host reads, for one library and one piece of content.
     *
     * @param  array<string,mixed>  $library
     * @return array<string,mixed>
     */
    protected function manifest(array $library, string $title, string $source): array
    {
        return [
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
            'source' => $source,
        ];
    }

    /**
     * Write h5p.json + content/content.json into a workspace and zip it.
     *
     * @param  array<string,mixed>  $manifest
     * @param  array<string,mixed>  $params
     * @return array{path: string, filename: string}
     */
    protected function writeArchive(string $workspace, array $manifest, array $params, string $filenameBase): array
    {
        $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

        file_put_contents($workspace . DIRECTORY_SEPARATOR . 'h5p.json', json_encode($manifest, $flags));
        file_put_contents(
            $workspace . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'content.json',
            json_encode($params, $flags)
        );

        $filename = $filenameBase . '.h5p';
        $archivePath = $this->makeWorkspace('archive') . DIRECTORY_SEPARATOR . $filename;

        $zip = new ZipArchive();
        if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the .h5p archive.');
        }
        $this->addDirectoryToZip($zip, $workspace, '');
        $zip->close();

        $this->deleteDirectory($workspace);

        return ['path' => $archivePath, 'filename' => $filename];
    }

    /**
     * Bound an uploaded archive before reading anything out of it.
     *
     * Entry count and total uncompressed size are both checked, because either
     * one alone is a zip bomb: a million empty files, or one file that expands
     * to a terabyte.
     */
    protected function guardArchive(ZipArchive $zip, int $uploadedBytes): void
    {
        $limits = $this->importLimits();

        if ($uploadedBytes > (int) ($limits['max_package_bytes'] ?? 67108864)) {
            throw new RuntimeException('That package is larger than the import limit.');
        }
        if ($zip->numFiles > (int) ($limits['max_entries'] ?? 2000)) {
            throw new RuntimeException('That package contains too many files to import.');
        }

        $uncompressed = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $uncompressed += (int) ($stat['size'] ?? 0);
        }
        if ($uncompressed > (int) ($limits['max_uncompressed_bytes'] ?? 268435456)) {
            throw new RuntimeException('That package expands to more than the import limit allows.');
        }
    }

    // -----------------------------------------------------------------------
    // Media
    // -----------------------------------------------------------------------

    /**
     * Read the bytes behind a stored media reference.
     *
     * Local-first: a path under this app's public directory is read from disk
     * rather than fetched over HTTP, which avoids a server calling itself
     * (and failing, on a host that does not resolve its own name).
     */
    protected function readMedia(string $source): ?string
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
     * Extract `content/*` media into public storage and return
     * package-relative path => public URL.
     *
     * @param  list<string>  $allowedExtensions
     * @param  list<string>  $warnings
     * @return array<string,string>
     */
    protected function extractMedia(
        ZipArchive $zip,
        array $allowedExtensions,
        string $subdirectory,
        string $prefix,
        array &$warnings
    ): array {
        $destination = public_path('h5p_content/' . $subdirectory);
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
            $filename = $prefix . substr(sha1($bytes), 0, 16) . '.' . $extension;
            $target = $destination . DIRECTORY_SEPARATOR . $filename;
            if (! is_file($target)) {
                // Created on first write, not up front: a package with no media
                // should not leave an empty directory behind in public/.
                $this->ensureDirectory($destination);
                file_put_contents($target, $bytes);
            }

            // Key by the path as content.json references it: relative to
            // `content/`, so `content/images/a.png` is referenced `images/a.png`.
            $urls[substr($name, strlen('content/'))] = asset('h5p_content/' . $subdirectory . '/' . $filename);
        }

        return $urls;
    }

    protected function mediaFilename(string $source, string $bytes, string $prefix): string
    {
        $path = parse_url($source, PHP_URL_PATH) ?: $source;
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION)) ?: 'jpg';

        return $prefix . substr(sha1($bytes), 0, 16) . '.' . $extension;
    }

    /**
     * The callback export passes to a subclass's media walker: copy the bytes
     * into the package and hand back the relative path, or record a warning
     * and leave the original reference alone.
     */
    protected function packMediaUsing(string $mediaDir, string $prefix, array &$warnings): callable
    {
        return function (string $source) use ($mediaDir, $prefix, &$warnings): ?string {
            $bytes = $this->readMedia($source);
            if ($bytes === null) {
                $warnings[] = 'Could not bundle ' . $source . ' -- the package keeps the original link.';

                return null;
            }

            $name = $this->mediaFilename($source, $bytes, $prefix);
            file_put_contents($mediaDir . DIRECTORY_SEPARATOR . $name, $bytes);

            return 'images/' . $name;
        };
    }

    /**
     * The callback import passes to a subclass's media walker: swap a
     * package-relative path for the public URL its bytes were written to.
     *
     * @param  array<string,string>  $mediaUrls
     * @param  list<string>  $warnings
     */
    protected function unpackMediaUsing(array $mediaUrls, array &$warnings): callable
    {
        return function (string $source) use ($mediaUrls, &$warnings): ?string {
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
        };
    }

    // -----------------------------------------------------------------------
    // Zip / filesystem
    // -----------------------------------------------------------------------

    /** @return array<string,mixed>|null */
    protected function readJsonEntry(ZipArchive $zip, string $entry): ?array
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
    protected function safeEntryPath(string $name): ?string
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

    protected function addDirectoryToZip(ZipArchive $zip, string $directory, string $prefix): void
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

    protected function makeWorkspace(string $kind): string
    {
        $path = storage_path('app/h5p_packages/' . $kind . '_' . Str::random(16));
        $this->ensureDirectory($path . DIRECTORY_SEPARATOR . 'content');

        return $path;
    }

    protected function ensureDirectory(string $path): void
    {
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    protected function deleteDirectory(string $path): void
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
}
