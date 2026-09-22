<?php

namespace App\Services\Evaluation;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Where scanned answer sheets live on disk.
 *
 * Sheets are kept on the local disk under `storage/app/exam_evaluation` --
 * outside `public/` deliberately. A scanned answer sheet carries a child's
 * name, roll number and marks, so it is served through the controller, which
 * checks the caller's school first, rather than through a guessable URL.
 *
 * File names are generated, never taken from the upload: a bulk scan arrives
 * with names from whatever the scanner produced, and those collide, carry
 * spaces and occasionally carry path separators.
 */
class ExamEvaluationStorage
{
    public const MAX_BYTES = 20 * 1024 * 1024;

    /** What a scanner or a phone camera actually produces. */
    public const ALLOWED_MIME_TYPES = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    public function directory(): string
    {
        return storage_path('app/exam_evaluation');
    }

    public function annotatedDirectory(): string
    {
        return storage_path('app/exam_evaluation/annotated');
    }

    /**
     * @return array{file_name:string, file_type:string, mime_type:string}
     */
    public function putSheet(int $batchId, UploadedFile $file): array
    {
        $mimeType = $this->detectMime($file);
        $extension = self::ALLOWED_MIME_TYPES[$mimeType];

        $this->ensureDirectory($this->directory());

        $fileName = sprintf('batch-%d-%s.%s', $batchId, Str::random(24), $extension);

        if (! $file->move($this->directory(), $fileName)) {
            throw new RuntimeException('The answer sheet could not be saved to disk.');
        }

        return [
            'file_name' => $fileName,
            'file_type' => $extension,
            'mime_type' => $mimeType,
        ];
    }

    public function putAnnotated(int $sheetId, string $pdfBinary): string
    {
        $this->ensureDirectory($this->annotatedDirectory());

        $fileName = sprintf('sheet-%d-%s.pdf', $sheetId, now()->format('YmdHis'));

        if (file_put_contents($this->annotatedDirectory() . DIRECTORY_SEPARATOR . $fileName, $pdfBinary) === false) {
            throw new RuntimeException('The marked-up answer sheet could not be saved to disk.');
        }

        return $fileName;
    }

    /** The absolute path of a stored sheet, or null when it is no longer there. */
    public function sheetPath(string $fileName): ?string
    {
        return $this->resolve($this->directory(), $fileName);
    }

    public function annotatedPath(string $fileName): ?string
    {
        return $this->resolve($this->annotatedDirectory(), $fileName);
    }

    public function delete(?string $fileName, ?string $annotatedFileName): void
    {
        foreach ([$this->sheetPath((string) $fileName), $this->annotatedPath((string) $annotatedFileName)] as $path) {
            if ($path !== null) {
                @unlink($path);
            }
        }
    }

    /**
     * The upload's real type, from its bytes.
     *
     * A scanner can label a PDF `application/octet-stream` and a browser will
     * pass that through, so the client's word is never the deciding vote.
     */
    public function detectMime(UploadedFile $file): string
    {
        $candidates = array_filter([
            $file->isValid() ? @mime_content_type($file->getPathname()) : null,
            $file->getMimeType(),
            $file->getClientMimeType(),
        ]);

        foreach ($candidates as $candidate) {
            if (isset(self::ALLOWED_MIME_TYPES[$candidate])) {
                return $candidate;
            }
        }

        throw new RuntimeException(
            'Only PDF, JPG and PNG scans can be evaluated. "'
            . $file->getClientOriginalName() . '" is not one of those.'
        );
    }

    /**
     * Joins a stored name onto its directory and refuses anything that tries to
     * climb out of it. The names are generated, so this should never fire --
     * which is exactly why it is cheap to keep.
     */
    private function resolve(string $directory, string $fileName): ?string
    {
        $fileName = basename(trim($fileName));

        if ($fileName === '' || $fileName === '.' || $fileName === '..') {
            return null;
        }

        $path = $directory . DIRECTORY_SEPARATOR . $fileName;

        return is_file($path) ? $path : null;
    }

    private function ensureDirectory(string $path): void
    {
        if (! is_dir($path) && ! mkdir($path, 0775, true) && ! is_dir($path)) {
            throw new RuntimeException("Unable to create the answer sheet folder: {$path}");
        }
    }
}
