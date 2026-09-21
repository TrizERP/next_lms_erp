<?php

namespace App\Services\lms\H5P;

use App\Models\lms\h5p\H5pDragDrop;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Reads and writes .h5p packages for the drag-and-drop type (H5P.DragQuestion).
 *
 * The FORMAT -- the zip layout, the import bounds, the path-traversal guard,
 * the local-first media read and the content-addressed media naming -- lives in
 * H5PPackageArchive and is shared with the text-passage types. What is left
 * here is the part that is genuinely about H5P.DragQuestion: where in its
 * params object the image paths are, and how the manifest names the content.
 *
 * This service does NOT bundle the library CODE (the `H5P.DragQuestion/`
 * directory an official export contains), because this ERP does not host the
 * library -- see the header of config/h5p_libraries.php. The manifest declares
 * the dependency closure, so an importing host resolves the libraries from its
 * own store, which is how a library-less export is meant to work. A host with
 * no H5P.DragQuestion installed will say so; it will not fail obscurely.
 *
 * MEDIA. Params hold absolute URLs while the content lives here, because that
 * is what this product's player needs. Inside a package they must be relative
 * to `content/`, or the package only works while this server is reachable. So
 * export rewrites every image path to `images/<file>` and copies the bytes in;
 * import does the reverse, writing media into public storage and rewriting the
 * paths back to URLs. Anything that cannot be fetched is left as its absolute
 * URL and reported in `warnings` rather than silently dropped.
 */
class H5PPackageService extends H5PPackageArchive
{
    /** config/h5p_libraries.php key for the only type this service handles. */
    private const LIBRARY_KEY = 'drag_and_drop';

    /** Subdirectory under public/h5p_content, and prefix for imported media. */
    private const MEDIA_SUBDIR = 'drag_drop';
    private const MEDIA_PREFIX = 'dd_';

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
        $this->requireZip('export');

        $library = $this->library(self::LIBRARY_KEY);
        $params = $this->builder->build($task);
        $warnings = [];

        $workspace = $this->makeWorkspace('export');
        $mediaDir = $workspace . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'images';
        $this->ensureDirectory($mediaDir);

        // Rewrite every media reference to a package-relative path, copying
        // the bytes in as we go.
        $params = $this->rewriteMedia($params, $this->packMediaUsing($mediaDir, self::MEDIA_PREFIX, $warnings));

        $title = trim((string) $task->title) ?: (string) config('h5p_libraries.package.extra_title_fallback');

        $written = $this->writeArchive(
            $workspace,
            $this->manifest($library, $title, 'eduerp:h5p_drag_drop:' . $task->id),
            $params,
            Str::slug($title ?: 'drag-and-drop') . '-' . $task->id
        );

        return $written + ['warnings' => $warnings];
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

            $expected = $this->library(self::LIBRARY_KEY)['machine_name'];
            $mainLibrary = (string) ($manifest['mainLibrary'] ?? '');
            if ($mainLibrary !== $expected) {
                throw new RuntimeException(
                    'That package is ' . ($mainLibrary ?: 'an unknown type') . '. This importer accepts ' . $expected . '.'
                );
            }

            $warnings = [];
            $allowed = array_map('strtolower', (array) ($this->importLimits()['allowed_media_extensions'] ?? []));

            // Pull media out first so the path rewrite below has URLs to use.
            $mediaUrls = $this->extractMedia($zip, $allowed, self::MEDIA_SUBDIR, self::MEDIA_PREFIX, $warnings);

            $content = $this->rewriteMedia($content, $this->unpackMediaUsing($mediaUrls, $warnings));

            $parsed = $this->builder->parse($content);
            $parsed['title'] = trim((string) ($manifest['title'] ?? '')) ?: 'Imported drag and drop';
            $parsed['warnings'] = $warnings;

            return $parsed;
        } finally {
            $zip->close();
        }
    }

    // -----------------------------------------------------------------------
    // Media map
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
}
