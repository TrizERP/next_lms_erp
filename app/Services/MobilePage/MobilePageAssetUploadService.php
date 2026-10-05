<?php

namespace App\Services\MobilePage;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Stores an image an admin uploads while designing a Custom Mobile Page --
 * a page's background, or an ImageBlock's source. Modeled directly on
 * App\Services\lms\Content\ContentUploadService::store(): tenant-scoped
 * deterministic path, mime allowlist, size cap, verified write, one return
 * envelope. Kept as its own small service rather than reusing
 * ContentUploadService directly because that service is explicitly scoped to
 * LMS content authoring (its own AuthoringTypeRegistry dependency) -- this
 * one only ever handles images for one purpose.
 */
class MobilePageAssetUploadService
{
    /** Bytes. Generous for a background/UI image without inviting abuse. */
    private const MAX_BYTES = 10485760; // 10 MB

    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    /**
     * @return array{disk:string, path:string, url:string, filename:string, extension:string, mime:?string, bytes:int}
     *
     * @throws RuntimeException
     */
    public function store(UploadedFile $file, int|string $subInstituteId, int|string $pageId): array
    {
        if (! $file->isValid()) {
            throw new RuntimeException('The uploaded file did not arrive intact.');
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new RuntimeException(sprintf(
                'A .%s file is not accepted here. Allowed: %s.',
                $extension ?: '(none)',
                implode(', ', self::ALLOWED_EXTENSIONS)
            ));
        }

        $bytes = (int) $file->getSize();

        if ($bytes > self::MAX_BYTES) {
            throw new RuntimeException(sprintf(
                'The file is %.1f MB; the limit is %d MB.',
                $bytes / 1048576,
                self::MAX_BYTES / 1048576
            ));
        }

        $filename = Str::uuid()->toString() . '.' . $extension;
        $path = sprintf(
            'public/mobile_page_builder/%s/%s/%s',
            (int) $subInstituteId,
            (int) $pageId,
            $filename
        );

        $disk = Storage::disk($this->disk());
        $disk->put($path, file_get_contents($file->getRealPath()), 'public');

        if (! $disk->exists($path)) {
            throw new RuntimeException('The file could not be stored. Nothing was saved.');
        }

        return [
            'disk' => $this->disk(),
            'path' => $path,
            'url' => $disk->url($path),
            'filename' => $filename,
            'extension' => $extension,
            'mime' => $file->getClientMimeType(),
            'bytes' => $bytes,
        ];
    }

    private function disk(): string
    {
        return (string) config('mobile_page_builder.upload_disk', 'digitalocean');
    }
}
