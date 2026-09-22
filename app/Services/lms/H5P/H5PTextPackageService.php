<?php

namespace App\Services\lms\H5P;

use App\Models\lms\h5p\H5pTextActivity;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Reads and writes .h5p packages for the three text-passage types:
 * H5P.DragText, H5P.Blanks, H5P.MarkTheWords.
 *
 * The zip format itself lives in H5PPackageArchive, shared with the drag-and-
 * drop exporter. What is specific here is small, because these types are
 * small: one optional image in `media`, and a `mainLibrary` that has to be
 * matched against whichever of the three the caller asked for.
 *
 * IMPORT IS TYPE-DIRECTED, NOT TYPE-SNIFFING.
 *
 * `import()` takes the content type the teacher is importing INTO and refuses
 * a package whose mainLibrary is a different one. That is deliberate: the
 * three types round-trip through the same table, so a package accepted into
 * the wrong list would store cleanly and then render as the wrong activity --
 * a Mark the Words passage presented as draggable words, with an answer key
 * that looks right and a task that is not what the author wrote. Refusing at
 * the boundary is the only place that mistake is still legible.
 *
 * `detectType()` exists for the caller that wants to be told which list a
 * package belongs in rather than guessing, and is what the import endpoint
 * uses to phrase its error.
 */
class H5PTextPackageService extends H5PPackageArchive
{
    /** Subdirectory under public/h5p_content, and prefix for imported media. */
    private const MEDIA_SUBDIR = 'text_activity';
    private const MEDIA_PREFIX = 'ta_';

    public function __construct(private readonly H5PTextActivityBuilder $builder)
    {
    }

    // -----------------------------------------------------------------------
    // Export
    // -----------------------------------------------------------------------

    /**
     * Write a .h5p package for one activity and return the local path to it.
     *
     * The caller streams and deletes the file. `warnings` carries two kinds of
     * thing and both matter to the author: media that could not be bundled,
     * and answer-key detail the target library cannot express (see
     * H5PTextActivityBuilder's header on flattening).
     *
     * @return array{path: string, filename: string, warnings: list<string>}
     */
    public function export(H5pTextActivity $activity): array
    {
        $this->requireZip('export');

        $library = $this->library($this->builder->libraryKey((string) $activity->content_type));
        $params = $this->builder->build($activity);

        // Recorded by build(): alternatives or tips dropped because the target
        // library has no syntax for them. Reported, never silent.
        $warnings = $this->builder->notes();

        $workspace = $this->makeWorkspace('export');
        $mediaDir = $workspace . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'images';
        $this->ensureDirectory($mediaDir);

        $params = $this->rewriteMedia($params, $this->packMediaUsing($mediaDir, self::MEDIA_PREFIX, $warnings));

        $title = trim((string) $activity->title)
            ?: ($activity->label() ?: (string) config('h5p_libraries.package.extra_title_fallback'));

        $written = $this->writeArchive(
            $workspace,
            $this->manifest($library, $title, 'eduerp:h5p_text_activity:' . $activity->id),
            $params,
            Str::slug($title) . '-' . $activity->id
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
     * the whole import stays inside one transaction with its audit entries.
     *
     * @param  string  $contentType  the list being imported into
     * @return array{title: string, content_type: string, activity: array<string,mixed>, blanks: list<array<string,mixed>>, warnings: list<string>}
     */
    public function import(UploadedFile $file, string $contentType, int|string|null $subInstituteId): array
    {
        $this->requireZip('import');

        $expected = $this->builder->machineName($contentType);

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

            $mainLibrary = (string) ($manifest['mainLibrary'] ?? '');
            if ($mainLibrary !== $expected) {
                throw new RuntimeException($this->wrongTypeMessage($mainLibrary, $expected));
            }

            $warnings = [];
            $allowed = array_map('strtolower', (array) ($this->importLimits()['allowed_media_extensions'] ?? []));

            $mediaUrls = $this->extractMedia($zip, $allowed, self::MEDIA_SUBDIR, self::MEDIA_PREFIX, $warnings);
            $content = $this->rewriteMedia($content, $this->unpackMediaUsing($mediaUrls, $warnings));

            $parsed = $this->builder->parse($content, $contentType);
            $parsed['title'] = trim((string) ($manifest['title'] ?? ''))
                ?: ('Imported ' . strtolower(H5pTextActivity::LABELS[$contentType] ?? 'activity'));
            $parsed['warnings'] = $warnings;

            if (($parsed['blanks'] ?? []) === []) {
                // Not fatal -- the teacher may want to fix the markup here --
                // but it is the single most common reason an imported package
                // looks blank, so it is said plainly rather than discovered.
                $parsed['warnings'][] = 'No answers were found in this package. Check that the passage marks them with asterisks, for example *answer*.';
            }

            return $parsed;
        } finally {
            $zip->close();
        }
    }

    /**
     * Which of the three types a package holds, or null if it is none of them.
     *
     * Used to turn "this importer accepts H5P.Blanks" into "that is a Mark the
     * Words package -- import it from the Mark the Words list instead."
     */
    public function detectType(UploadedFile $file): ?string
    {
        if (! class_exists(ZipArchive::class)) {
            return null;
        }

        $zip = new ZipArchive();
        if ($zip->open($file->getRealPath()) !== true) {
            return null;
        }

        try {
            $manifest = $this->readJsonEntry($zip, 'h5p.json');
            $mainLibrary = (string) ($manifest['mainLibrary'] ?? '');

            foreach (H5pTextActivity::TYPES as $type) {
                if ($this->builder->machineName($type) === $mainLibrary) {
                    return $type;
                }
            }

            return null;
        } finally {
            $zip->close();
        }
    }

    private function wrongTypeMessage(string $mainLibrary, string $expected): string
    {
        foreach (H5pTextActivity::TYPES as $type) {
            if ($this->builder->machineName($type) === $mainLibrary) {
                return 'That is a ' . (H5pTextActivity::LABELS[$type] ?? $mainLibrary)
                    . ' package. Import it from the ' . (H5pTextActivity::LABELS[$type] ?? $mainLibrary)
                    . ' list instead.';
            }
        }

        return 'That package is ' . ($mainLibrary ?: 'an unknown type')
            . '. This importer accepts ' . $expected . '.';
    }

    // -----------------------------------------------------------------------
    // Media map
    // -----------------------------------------------------------------------

    /**
     * These three types hold at most one image, in `media.type.params.file`.
     *
     * A null return from $rewrite leaves the path untouched, same contract as
     * the drag-and-drop walker.
     *
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    private function rewriteMedia(array $params, callable $rewrite): array
    {
        $path = $params['media']['type']['params']['file']['path'] ?? null;
        if (is_string($path) && $path !== '') {
            $replacement = $rewrite($path);
            if ($replacement !== null) {
                $params['media']['type']['params']['file']['path'] = $replacement;
            }
        }

        return $params;
    }
}
