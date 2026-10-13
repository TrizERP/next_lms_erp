<?php

namespace App\Services\StudyDeck;

use Illuminate\Support\Facades\DB;

/**
 * The one place study-deck pictures are stored, found and removed: the `study_deck_images` table.
 *
 * Nothing in the study-deck pipeline writes a picture to a folder or a bucket any more. A deck refers to a
 * picture by `study-deck-image:<id>`; everything that needs the picture (the HTTP endpoint, the PDF, the
 * PowerPoint, the migration) asks this class for it.
 *
 * Rules that live here so they cannot drift between callers
 *   - What is accepted: PNG, JPEG or WebP, judged from the bytes (never from a name or a caller-supplied type),
 *     at most 8 MiB and 40 megapixels.
 *   - One picture per school: (sub_institute_id, sha256) is unique, so a repeat is a lookup, not a second copy.
 *     Two schools holding the same picture hold two rows, so neither can learn what the other stored.
 *   - Who may see a picture: the school that owns it, and the shared platform library (school 1) for a school
 *     that has the LMS (school_setup.is_lms = 'Y') - the same rule the Classroom Resource list applies to decks.
 *   - Memory: the picture column is read only by id, and large pictures are read in slices, so a response or a
 *     check never holds more than one slice of a large picture (and never a collection of pictures).
 */
final class StudyDeckImages
{
    public const REF_PREFIX = 'study-deck-image:';

    public const MAX_BYTES = 8 * 1024 * 1024;

    public const MAX_PIXELS = 40_000_000;

    /** Largest slice read from the database at once. */
    public const CHUNK_BYTES = 524288;

    /** The platform library's school. */
    public const PLATFORM_TENANT = 1;

    private const FORMATS = ['image/png' => 'png', 'image/jpeg' => 'jpeg', 'image/webp' => 'webp'];

    private const TABLE = 'study_deck_images';

    private const LINKS = 'study_deck_image_links';

    /** Everything but the picture itself. */
    private const META = ['id', 'sub_institute_id', 'chapter_id', 'sha256', 'mime_type', 'format', 'byte_size', 'width', 'height', 'created_at'];

    // -----------------------------------------------------------------------------------------------------
    // References

    public static function ref(int $id): string
    {
        return self::REF_PREFIX . $id;
    }

    /** The id inside a `study-deck-image:<id>` reference, or null when the value is anything else. */
    public static function idFromRef(mixed $value): ?int
    {
        if (!is_string($value) || !preg_match('/^' . preg_quote(self::REF_PREFIX, '/') . '([1-9][0-9]{0,17})$/', $value, $m)) {
            return null;
        }

        return (int) $m[1];
    }

    // -----------------------------------------------------------------------------------------------------
    // Writing

    /**
     * Check that bytes are a picture this store accepts.
     *
     * @return array{mime:string,format:string,width:int,height:int}
     * @throws \InvalidArgumentException
     */
    public function inspect(string $bytes): array
    {
        if ($bytes === '') {
            throw new \InvalidArgumentException('The picture is empty.');
        }
        if (strlen($bytes) > self::MAX_BYTES) {
            throw new \InvalidArgumentException(sprintf('The picture is %.1f MiB; the limit is %d MiB.', strlen($bytes) / 1048576, self::MAX_BYTES / 1048576));
        }
        $info = @getimagesizefromstring($bytes);
        if ($info === false) {
            throw new \InvalidArgumentException('The bytes are not a readable picture.');
        }
        $mime = (string) ($info['mime'] ?? '');
        if (!isset(self::FORMATS[$mime])) {
            throw new \InvalidArgumentException('The picture is "' . $mime . '"; only PNG, JPEG and WebP are accepted.');
        }
        $width = (int) $info[0];
        $height = (int) $info[1];
        if ($width < 1 || $height < 1 || $width > 65535 || $height > 65535 || $width * $height > self::MAX_PIXELS) {
            throw new \InvalidArgumentException("The picture is {$width}x{$height}, which is outside what a slide can use.");
        }

        return ['mime' => $mime, 'format' => self::FORMATS[$mime], 'width' => $width, 'height' => $height];
    }

    /**
     * Store a picture for a school, or find the identical one already stored.
     *
     * Safe to repeat and safe to race: the unique key decides, and a lost race becomes a lookup. A failed
     * write throws and stores nothing; the caller still holds the bytes.
     *
     * @return array{id:int,ref:string,sha256:string,mime:string,format:string,bytes:int,width:int,height:int,created:bool}
     * @throws \InvalidArgumentException when the bytes are not an acceptable picture
     */
    public function put(string $bytes, int $tenant, ?int $chapterId = null, ?string $sourceUrl = null): array
    {
        $info = $this->inspect($bytes);
        $sha = hash('sha256', $bytes);
        $size = strlen($bytes);
        $sourceHash = $sourceUrl !== null && $sourceUrl !== '' ? sha1($sourceUrl) : null;

        $existing = $this->findBySha($tenant, $sha);
        $created = false;

        if ($existing === null) {
            try {
                $id = $this->insert([
                    'sub_institute_id' => $tenant,
                    'chapter_id' => $chapterId,
                    'sha256' => $sha,
                    'mime_type' => $info['mime'],
                    'format' => $info['format'],
                    'byte_size' => $size,
                    'width' => $info['width'],
                    'height' => $info['height'],
                    'source_url_hash' => $sourceHash,
                    'source_url' => $sourceUrl !== null ? mb_substr($sourceUrl, 0, 2048) : null,
                ], $bytes);
                $created = true;
            } catch (\PDOException $e) {
                // Another run stored the same picture between our look and our insert: that is a lookup.
                $existing = $this->findBySha($tenant, $sha);
                if ($existing === null) {
                    throw $e;
                }
            }
        }

        if ($existing !== null) {
            $id = (int) $existing->id;
            if ((int) $existing->byte_size !== $size) {
                throw new \RuntimeException("Stored picture #{$id} has the same checksum but a different size; the store is inconsistent.");
            }
            $fill = [];
            if ($chapterId !== null && $existing->chapter_id === null) {
                $fill['chapter_id'] = $chapterId;
            }
            if ($sourceHash !== null && $existing->source_url_hash === null) {
                $fill += ['source_url_hash' => $sourceHash, 'source_url' => mb_substr((string) $sourceUrl, 0, 2048)];
            }
            if ($fill) {
                DB::table(self::TABLE)->where('id', $id)->update($fill);
            }
        }

        return ['id' => $id, 'ref' => self::ref($id), 'sha256' => $sha, 'mime' => $info['mime'], 'format' => $info['format'], 'bytes' => $size, 'width' => $info['width'], 'height' => $info['height'], 'created' => $created];
    }

    /**
     * Insert one row, sending the picture as a binary (LOB) parameter.
     *
     * The query builder binds every string as text, which a database is free to treat as characters: on SQLite the
     * picture would be stored as TEXT (and then cut in the wrong places when read in slices), and on MySQL it depends
     * on the connection's character set. Binding it as a LOB makes it bytes everywhere. It uses the connection's own
     * PDO, so it takes part in any transaction that is open.
     *
     * @param array<string,mixed> $columns every column but `data`
     * @throws \PDOException (SQLSTATE 23000 when the picture is already stored)
     */
    private function insert(array $columns, string $bytes): int
    {
        $pdo = DB::connection()->getPdo();
        $statement = $pdo->prepare(sprintf(
            'INSERT INTO %s (%s, data) VALUES (%s)',
            self::TABLE,
            implode(', ', array_keys($columns)),
            implode(', ', array_fill(0, count($columns) + 1, '?'))
        ));

        $n = 1;
        foreach ($columns as $value) {
            $statement->bindValue($n++, $value, $value === null ? \PDO::PARAM_NULL : (is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR));
        }
        $statement->bindValue($n, $bytes, \PDO::PARAM_LOB);
        $statement->execute();

        return (int) $pdo->lastInsertId();
    }

    private function findBySha(int $tenant, string $sha): ?object
    {
        return DB::table(self::TABLE)->where('sub_institute_id', $tenant)->where('sha256', $sha)
            ->first(['id', 'byte_size', 'chapter_id', 'source_url_hash']);
    }

    /** The id of the picture a school already holds with these exact bytes, or null. */
    public function idForBytes(string $bytes, int $tenant): ?int
    {
        $row = $this->findBySha($tenant, hash('sha256', $bytes));

        return $row ? (int) $row->id : null;
    }

    // -----------------------------------------------------------------------------------------------------
    // Reading

    /**
     * What is known about a picture, without the picture.
     *
     * @return array{id:int,tenant:int,chapter_id:?int,sha256:string,mime:string,format:string,bytes:int,width:int,height:int}|null
     */
    public function meta(int $id): ?array
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first(self::META);

        return $row ? $this->shape($row) : null;
    }

    /** {@see meta()}, but only when this school may see the picture. */
    public function visibleMeta(int $id, int $tenant): ?array
    {
        $meta = $this->meta($id);

        return $meta !== null && in_array($meta['tenant'], $this->visibleTenants($tenant), true) ? $meta : null;
    }

    /**
     * Which of these ids the school may see.
     *
     * @param array<int,int> $ids
     * @return array<int,array{id:int,tenant:int,chapter_id:?int,sha256:string,mime:string,format:string,bytes:int,width:int,height:int}> keyed by id
     */
    public function visibleMetas(array $ids, int $tenant): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids) {
            return [];
        }
        $out = [];
        foreach (array_chunk($ids, 500) as $part) {
            foreach (DB::table(self::TABLE)->whereIn('id', $part)->whereIn('sub_institute_id', $this->visibleTenants($tenant))->get(self::META) as $row) {
                $out[(int) $row->id] = $this->shape($row);
            }
        }

        return $out;
    }

    /** The schools whose pictures a school may see: its own, plus the platform library when it has the LMS. */
    public function visibleTenants(int $tenant): array
    {
        $tenants = [$tenant];
        if ($tenant !== self::PLATFORM_TENANT && DB::table('school_setup')->where('Id', $tenant)->value('is_Lms') === 'Y') {
            $tenants[] = self::PLATFORM_TENANT;
        }

        return $tenants;
    }

    /** The whole picture, for the renderers that need it all at once (one picture at a time). */
    public function bytes(int $id): ?string
    {
        $value = DB::table(self::TABLE)->where('id', $id)->value('data');
        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * The picture in slices, so serving or checking a large one never holds more than one slice.
     *
     * @return \Generator<int,string>
     */
    public function chunks(int $id, int $byteSize, int $chunk = self::CHUNK_BYTES): \Generator
    {
        if ($byteSize <= $chunk) {
            $whole = $this->bytes($id);
            if ($whole === null) {
                throw new \RuntimeException("Picture #{$id} has no data.");
            }
            yield $whole;

            return;
        }

        for ($offset = 1; $offset <= $byteSize; $offset += $chunk) {
            $piece = DB::table(self::TABLE)->where('id', $id)->selectRaw('SUBSTR(data, ?, ?) AS piece', [$offset, $chunk])->first();
            $slice = $piece->piece ?? null;
            if (is_resource($slice)) {
                $slice = stream_get_contents($slice);
            }
            if (!is_string($slice) || $slice === '') {
                throw new \RuntimeException("Picture #{$id} ended early at byte " . ($offset - 1) . " of {$byteSize}.");
            }
            yield $slice;
        }
    }

    /** Does the stored picture really have these bytes? Read back in slices; nothing is trusted from the insert. */
    public function verify(int $id, string $sha256, int $byteSize): bool
    {
        $meta = $this->meta($id);
        if ($meta === null || $meta['sha256'] !== $sha256 || $meta['bytes'] !== $byteSize) {
            return false;
        }
        try {
            $hash = hash_init('sha256');
            $read = 0;
            foreach ($this->chunks($id, $byteSize) as $piece) {
                hash_update($hash, $piece);
                $read += strlen($piece);
            }
        } catch (\Throwable) {
            return false;
        }

        return $read === $byteSize && hash_equals($sha256, hash_final($hash));
    }

    /**
     * A picture the document renderers can embed: bytes and type, with WebP turned into PNG because neither
     * PowerPoint nor the PDF library takes WebP. Null when the school may not see it or it cannot be read.
     *
     * @return array{bytes:string,mime:string}|null
     */
    public function forDocument(int $id, int $tenant): ?array
    {
        $meta = $this->visibleMeta($id, $tenant);
        $bytes = $meta ? $this->bytes($id) : null;
        if ($meta === null || $bytes === null) {
            return null;
        }
        if ($meta['mime'] !== 'image/webp') {
            return ['bytes' => $bytes, 'mime' => $meta['mime']];
        }

        $im = function_exists('imagecreatefromstring') ? @imagecreatefromstring($bytes) : false;
        if (!$im) {
            return null;
        }
        ob_start();
        imagepng($im);
        $png = (string) ob_get_clean();
        imagedestroy($im);

        return $png !== '' ? ['bytes' => $png, 'mime' => 'image/png'] : null;
    }

    /**
     * A picture this school downloaded from this address before, so a re-run does not fetch it again.
     *
     * @return array{bytes:string,mime:string,width:int,height:int}|null
     */
    public function cachedDownload(int $tenant, string $url): ?array
    {
        $row = DB::table(self::TABLE)->where('sub_institute_id', $tenant)->where('source_url_hash', sha1($url))
            ->orderByDesc('id')->first(['id', 'mime_type', 'width', 'height', 'byte_size']);
        if (!$row) {
            return null;
        }
        $bytes = $this->bytes((int) $row->id);
        if ($bytes === null || strlen($bytes) !== (int) $row->byte_size) {
            return null;
        }

        return ['bytes' => $bytes, 'mime' => (string) $row->mime_type, 'width' => (int) $row->width, 'height' => (int) $row->height];
    }

    // -----------------------------------------------------------------------------------------------------
    // Which decks use a picture

    /**
     * Record that a stored deck uses these pictures. Repeating it changes nothing.
     *
     * @param array<int,int> $imageIds
     * @return int how many links were new
     */
    public function link(int $contentId, array $imageIds, ?int $chapterId = null): int
    {
        $rows = [];
        foreach (array_unique(array_map('intval', $imageIds)) as $id) {
            $rows[] = ['image_id' => $id, 'content_id' => $contentId, 'chapter_id' => $chapterId];
        }
        $added = 0;
        foreach (array_chunk($rows, 200) as $part) {
            $added += (int) DB::table(self::LINKS)->insertOrIgnore($part);
        }

        return $added;
    }

    /**
     * Forget that a deck uses any picture.
     *
     * @return array<int,int> the pictures it had used
     */
    public function unlink(int $contentId): array
    {
        $ids = DB::table(self::LINKS)->where('content_id', $contentId)->pluck('image_id')->map(fn ($v) => (int) $v)->all();
        DB::table(self::LINKS)->where('content_id', $contentId)->delete();

        return $ids;
    }

    /**
     * Which of these pictures a deck other than this one still uses.
     *
     * @param array<int,int> $ids
     * @return array<int,int>
     */
    public function usedElsewhere(array $ids, int $exceptContentId): array
    {
        if (!$ids) {
            return [];
        }

        return DB::table(self::LINKS)->whereIn('image_id', array_map('intval', $ids))->where('content_id', '<>', $exceptContentId)
            ->distinct()->pluck('image_id')->map(fn ($v) => (int) $v)->all();
    }

    /** @return array<int,int> */
    public function linkedImageIds(int $contentId): array
    {
        return DB::table(self::LINKS)->where('content_id', $contentId)->pluck('image_id')->map(fn ($v) => (int) $v)->all();
    }

    /**
     * Delete those of these pictures that no deck uses. A picture another deck still uses is never touched.
     *
     * @param array<int,int> $ids
     * @return int how many were deleted
     */
    public function deleteUnlinked(array $ids): int
    {
        $deleted = 0;
        foreach (array_chunk(array_values(array_unique(array_map('intval', $ids))), 500) as $part) {
            $deleted += DB::table(self::TABLE)->whereIn('id', $part)
                ->whereNotIn('id', DB::table(self::LINKS)->select('image_id'))->delete();
        }

        return $deleted;
    }

    /**
     * Pictures no deck uses and that are older than a cut-off: what runs that were never published leave behind.
     *
     * @return array{count:int,bytes:int,ids:array<int,int>}
     */
    public function orphans(int $olderThanDays): array
    {
        $cutoff = now()->subDays(max(0, $olderThanDays));
        $rows = DB::table(self::TABLE)->where('created_at', '<', $cutoff)
            ->whereNotIn('id', DB::table(self::LINKS)->select('image_id'))->get(['id', 'byte_size']);

        return ['count' => $rows->count(), 'bytes' => (int) $rows->sum('byte_size'), 'ids' => $rows->pluck('id')->map(fn ($v) => (int) $v)->all()];
    }

    /** @return array{id:int,tenant:int,chapter_id:?int,sha256:string,mime:string,format:string,bytes:int,width:int,height:int} */
    private function shape(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'tenant' => (int) $row->sub_institute_id,
            'chapter_id' => $row->chapter_id !== null ? (int) $row->chapter_id : null,
            'sha256' => (string) $row->sha256,
            'mime' => (string) $row->mime_type,
            'format' => (string) $row->format,
            'bytes' => (int) $row->byte_size,
            'width' => (int) $row->width,
            'height' => (int) $row->height,
        ];
    }
}
