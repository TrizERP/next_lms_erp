<?php

namespace App\Services\lms\H5P;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * The .h5p read/write cycle, for any type in this family.
 *
 * H5PPackageArchive owns the FORMAT -- the zip layout, the import bounds, the
 * path-traversal guard, the local-first media read, the content-addressed
 * media naming. H5PPackageService then wrote the cycle ON TOP of it once, for
 * drag and drop, and H5PTextPackageService wrote it a second time for the
 * text-passage types. With four more types arriving, a third and fourth copy
 * would be four places for the same import guard to drift.
 *
 * So the cycle lives here, and a subclass says only the four things that
 * genuinely differ per library:
 *
 *   libraryKey()    which config/h5p_libraries.php entry it is
 *   mediaSubdir()   where imported media lands under public/h5p_content
 *   mediaPrefix()   the filename prefix that makes it identifiable on disk
 *   rewriteMedia()  where in ITS params object the media paths are
 *
 * MEDIA, which is the whole reason rewriteMedia() is abstract. Params hold
 * absolute URLs while the content lives here, because that is what this
 * product's player needs. Inside a package they must be relative to
 * `content/`, or the package only works while this server is reachable. So
 * export rewrites every media path to `images/<file>` and copies the bytes in;
 * import does the reverse. Anything that cannot be fetched keeps its absolute
 * URL and is reported in `warnings` rather than silently dropped.
 *
 * None of this bundles the library CODE -- see config/h5p_libraries.php's
 * header for why, and what would change if that were ever wanted.
 */
abstract class H5PContentPackageService extends H5PPackageArchive
{
    /** config/h5p_libraries.php key for the type this service handles. */
    abstract protected function libraryKey(): string;

    /** Subdirectory under public/h5p_content for imported media. */
    abstract protected function mediaSubdir(): string;

    /** Filename prefix for imported media. */
    abstract protected function mediaPrefix(): string;

    /**
     * Apply $rewrite to every media path in a params object for this library.
     *
     * $rewrite returns the replacement, or null to leave a path untouched.
     *
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    abstract protected function rewriteMedia(array $params, callable $rewrite): array;

    /**
     * Turn a validated params object into this type's row payloads.
     *
     * @param  array<string,mixed>  $content
     * @return array<string,mixed>
     */
    abstract protected function parseParams(array $content): array;

    /** Fallback title for an import whose manifest has none. */
    abstract protected function importedTitleFallback(): string;

    // -----------------------------------------------------------------------
    // Export
    // -----------------------------------------------------------------------

    /**
     * Write a .h5p package and return the local path to it.
     *
     * The caller streams and deletes the file
     * (`response()->download(...)->deleteFileAfterSend()`).
     *
     * @param  array<string,mixed>  $params  already built by the type's builder
     * @param  list<string>  $caveats  format limits to report alongside media warnings
     * @return array{path: string, filename: string, warnings: list<string>}
     */
    protected function writePackage(array $params, string $title, int $sourceId, array $caveats = []): array
    {
        $this->requireZip('export');

        $library = $this->library($this->libraryKey());
        $warnings = $caveats;

        $workspace = $this->makeWorkspace('export');
        $mediaDir = $workspace . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'images';
        $this->ensureDirectory($mediaDir);

        $params = $this->rewriteMedia($params, $this->packMediaUsing($mediaDir, $this->mediaPrefix(), $warnings));

        $title = trim($title) ?: (string) config('h5p_libraries.package.extra_title_fallback');

        $written = $this->writeArchive(
            $workspace,
            $this->manifest($library, $title, 'eduerp:' . $this->mediaSubdir() . ':' . $sourceId),
            $params,
            Str::slug($title ?: $this->mediaSubdir()) . '-' . $sourceId
        );

        return $written + ['warnings' => array_values($warnings)];
    }

    // -----------------------------------------------------------------------
    // Import
    // -----------------------------------------------------------------------

    /**
     * Read an uploaded .h5p package into row payloads.
     *
     * Nothing is written to the database here -- the controller owns that, so
     * the whole import stays inside one transaction with its audit entry.
     *
     * @return array<string,mixed>  the subclass's parse() result, plus `title` and `warnings`
     */
    public function import(UploadedFile $file, int|string|null $subInstituteId): array
    {
        $this->requireZip('import');

        $zip = new ZipArchive();
        if ($zip->open($file->getRealPath()) !== true) {
            throw new RuntimeException('That file is not a readable .h5p package.');
        }

        try {
            $this->guardArchive($zip, (int) $file->getSize());

            $manifest = $this->readJsonEntry($zip, 'h5p.json');
            $content = $this->readJsonEntry($zip, 'content/content.json');

            if ($manifest === null || $content === null) {
                throw new RuntimeException('That package is missing h5p.json or content/content.json.');
            }

            $expected = $this->library($this->libraryKey())['machine_name'];
            $mainLibrary = (string) ($manifest['mainLibrary'] ?? '');
            if ($mainLibrary !== $expected) {
                throw new RuntimeException(
                    'That package is ' . ($mainLibrary ?: 'an unknown type') . '. This importer accepts ' . $expected . '.'
                );
            }

            $warnings = [];
            $allowed = array_map('strtolower', (array) ($this->importLimits()['allowed_media_extensions'] ?? []));

            // Media first, so the path rewrite below has URLs to use.
            $mediaUrls = $this->extractMedia($zip, $allowed, $this->mediaSubdir(), $this->mediaPrefix(), $warnings);
            $content = $this->rewriteMedia($content, $this->unpackMediaUsing($mediaUrls, $warnings));

            $parsed = $this->parseParams($content);

            // A subclass's parse() may raise warnings of its own (an element
            // this ERP has no editor for, say). Those merge with the media
            // ones rather than replacing them.
            $parsed['warnings'] = array_values(array_merge($warnings, (array) ($parsed['warnings'] ?? [])));
            $parsed['title'] = trim((string) ($manifest['title'] ?? '')) ?: $this->importedTitleFallback();

            // Kept verbatim so the type's builder can merge back any key this
            // schema does not model -- see ConvertsToH5PParams::mergePreservedKeys.
            $parsed['raw_params'] = $content;

            return $parsed;
        } finally {
            $zip->close();
        }
    }

    // -----------------------------------------------------------------------
    // Shared rewrite helpers
    // -----------------------------------------------------------------------

    /**
     * Rewrite `path` on an H5P file object in place, if it has one.
     *
     * Every media reference in every one of these libraries is ultimately
     * `{path, mime}` somewhere in the tree, so subclasses walk their own
     * structure and call this at each leaf rather than repeating the
     * null/empty/unchanged dance.
     *
     * @param  mixed  $node
     * @return bool  whether the node was rewritten
     */
    protected function rewriteFileNode(mixed &$node, callable $rewrite): bool
    {
        if (! is_array($node)) {
            return false;
        }

        $path = $node['path'] ?? null;
        if (! is_string($path) || $path === '') {
            return false;
        }

        $replacement = $rewrite($path);
        if ($replacement === null) {
            return false;
        }

        $node['path'] = $replacement;

        return true;
    }

    /**
     * Rewrite a bare path string held directly on a key.
     *
     * Used for this product's extension keys, which carry a URL rather than a
     * file object -- a custom hotspot icon, a memory card's verbatim face.
     *
     * @param  array<string,mixed>  $holder
     */
    protected function rewriteStringPath(array &$holder, string $key, callable $rewrite): bool
    {
        $path = $holder[$key] ?? null;
        if (! is_string($path) || $path === '') {
            return false;
        }

        $replacement = $rewrite($path);
        if ($replacement === null) {
            return false;
        }

        $holder[$key] = $replacement;

        return true;
    }
}
