<?php

namespace App\Services\lms\Content;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The one place LMS content authoring writes an uploaded file.
 *
 * ============================ SCOPE — READ BEFORE EXTENDING ============================
 * This is a PLACEHOLDER for Track D's **Document / File Service** (Central Engines row 9:
 * "upload, view, delete/archive, version, access control, link-to-entity, audit").
 *
 * It serves ONLY the content-authoring path. There are 118 direct
 * `Storage::disk('digitalocean')` call sites in this repo — contentController.php:308,
 * 488, 500, 754-756, 770, 1699; TeacherResourceApiController.php:292; and many more. They
 * are NOT migrated here. Doing so would be a repo-wide refactor of live upload paths that
 * 56 tenants depend on, which is Track D's scope and not this phase's.
 *
 * When Track D's service lands, this class becomes an adapter over it: callers only ever
 * use store(), so the swap needs no caller changes.
 * =======================================================================================
 *
 * WHAT IT OWNS THAT THE INLINE CALLS DO NOT
 *  1. A per-authoring-type extension allowlist, from the registry rather than hardcoded.
 *     This matters: the mobile writer accepts pdf,mp3,mp4,html,jpg,jpeg,png,link
 *     (teacherapiController.php:343) while the web upload accepts only pdf,ppt,pptx
 *     (ApiLmsCourseController.php:1505). One global list would break one of them.
 *  2. A deterministic, tenant-scoped path, so an object can be traced back to its row.
 *  3. A verified write. The inline paths mostly assume putFileAs succeeded; a silent
 *     failure there produces a content row pointing at nothing.
 *  4. One return envelope, including a sha256 so a duplicate upload is detectable.
 */
class ContentUploadService
{
    /** Bytes. Matches the practical limit of the existing upload paths. */
    private const MAX_BYTES = 104857600; // 100 MB

    public function __construct(private AuthoringTypeRegistry $registry)
    {
    }

    /**
     * Store an uploaded file for an authoring request.
     *
     * @return array{disk:string, path:string, url:string, filename:string, extension:string, mime:?string, bytes:int, sha256:string}
     *
     * @throws RuntimeException
     */
    public function store(UploadedFile $file, string $authoringType, int|string $subInstituteId, int|string|null $chapterId): array
    {
        if (! $file->isValid()) {
            throw new RuntimeException('The uploaded file did not arrive intact.');
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        $allowed = $this->registry->uploadMimes($authoringType);

        if ($allowed !== [] && ! in_array($extension, $allowed, true)) {
            throw new RuntimeException(sprintf(
                'A .%s file is not accepted for %s. Allowed: %s.',
                $extension ?: '(none)',
                $authoringType,
                implode(', ', $allowed)
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

        // Deterministic and tenant-scoped, so an orphaned object can be traced back.
        // The existing paths write everything into one flat prefix, which makes an orphan
        // impossible to attribute.
        $filename = Str::uuid()->toString() . ($extension !== '' ? '.' . $extension : '');
        $path = sprintf(
            'public/lms_content_file/%s/%s/%s',
            (int) $subInstituteId,
            $chapterId === null ? 'unfiled' : (int) $chapterId,
            $filename
        );

        $disk = Storage::disk($this->disk());
        $sha256 = hash_file('sha256', $file->getRealPath()) ?: '';

        $disk->put($path, file_get_contents($file->getRealPath()), 'public');

        // Verify rather than assume. A silent put() failure otherwise produces a content
        // row pointing at nothing, which is worse than a rejected upload.
        if (! $disk->exists($path)) {
            throw new RuntimeException('The file could not be stored. Nothing was saved.');
        }

        return [
            'disk'      => $this->disk(),
            'path'      => $path,
            'url'       => $disk->url($path),
            'filename'  => $filename,
            'extension' => $extension,
            'mime'      => $file->getClientMimeType(),
            'bytes'     => $bytes,
            'sha256'    => $sha256,
        ];
    }

    /**
     * Best-effort cleanup when the database write fails after the object landed.
     *
     * Object storage is not transactional, so an insert failure after a successful put
     * leaves an orphan. This removes it where it can; where it cannot, the caller logs the
     * key so a sweeper can reconcile later.
     */
    public function forget(string $path): bool
    {
        try {
            return Storage::disk($this->disk())->delete($path);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function disk(): string
    {
        // 'digitalocean' is what all 118 existing call sites use; configurable so a test
        // or a local run can point at a fake without touching Spaces.
        return (string) config('lms_content.upload_disk', 'digitalocean');
    }
}
