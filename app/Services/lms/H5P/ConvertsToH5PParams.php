<?php

namespace App\Services\lms\H5P;

/**
 * The parts of building H5P params that are the same whatever the library is.
 *
 * H5PDragQuestionBuilder and H5PTextActivityBuilder each grew their own copy
 * of these five helpers before there were four more builders to write. Rather
 * than a sixth copy, they live here once.
 *
 * The two existing builders are deliberately NOT changed to use this trait in
 * this vertical: they are covered by passing unit tests, the helpers are
 * private to them, and rewriting working code to save duplication that has
 * already been paid for is a change with risk and no user-visible result. The
 * trait is where a future tidy starts.
 */
trait ConvertsToH5PParams
{
    /**
     * A stable UUIDv4-shaped sub-content id.
     *
     * H5P requires the format but not randomness, and deriving it from the row
     * means exporting the same item twice produces identical packages -- which
     * is what makes an export diffable and a re-import idempotent.
     */
    protected function subContentId(string $kind, int $id): string
    {
        $hash = md5('eduerp:h5p:' . $kind . ':' . $id);

        return sprintf(
            '%s-%s-4%s-a%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 13, 3),
            substr($hash, 17, 3),
            substr($hash, 20, 12)
        );
    }

    /**
     * The mime type H5P.Image / H5P.Video / H5P.Audio want beside a path.
     *
     * Derived from the extension rather than sniffed, because at export time
     * the bytes may not be local -- the path can still be the remote URL the
     * media lives at. An unknown extension falls back by family rather than
     * guessing wrong across families.
     */
    protected function mimeFor(string $path, string $family = 'image'): string
    {
        $extension = strtolower((string) pathinfo(parse_url($path, PHP_URL_PATH) ?: $path, PATHINFO_EXTENSION));

        return match ($extension) {
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'jpg', 'jpeg' => 'image/jpeg',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'ogv' => 'video/ogg',
            'mp3' => 'audio/mpeg',
            'ogg' => 'audio/ogg',
            'wav' => 'audio/wav',
            'm4a' => 'audio/mp4',
            default => match ($family) {
                'video' => 'video/mp4',
                'audio' => 'audio/mpeg',
                default => 'image/jpeg',
            },
        };
    }

    /** Percentages, to the precision the columns hold. */
    protected function num(mixed $value): float
    {
        return round((float) $value, 4);
    }

    /**
     * H5P `overallFeedback`, which is never allowed to be empty.
     *
     * A library given no bands renders no message at the end of an attempt,
     * which reads as the activity having failed to finish. One band covering
     * the whole range is the floor.
     *
     * @param  mixed  $bands
     * @return list<array{from:int,to:int,feedback:string}>
     */
    protected function feedbackBands(mixed $bands): array
    {
        $out = [];
        foreach ((array) ($bands ?? []) as $band) {
            if (! is_array($band)) {
                continue;
            }
            $out[] = [
                'from' => max(0, min(100, (int) ($band['from'] ?? 0))),
                'to' => max(0, min(100, (int) ($band['to'] ?? 100))),
                'feedback' => (string) ($band['feedback'] ?? ''),
            ];
        }

        return $out !== [] ? $out : [['from' => 0, 'to' => 100, 'feedback' => 'You got @score of @total points.']];
    }

    /**
     * Recover a pass percentage from imported feedback bands.
     *
     * A package authored elsewhere has no pass_percentage column -- what it
     * has is the band that reaches 100, and where that band STARTS is the
     * score the author treated as success.
     *
     * @param  list<mixed>  $bands
     */
    protected function passFromFeedback(array $bands, int $default = 100): int
    {
        $pass = $default;
        foreach ($bands as $band) {
            if (is_array($band) && (int) ($band['to'] ?? 0) >= 100 && isset($band['from'])) {
                $pass = (int) $band['from'];
            }
        }

        return $pass;
    }

    /**
     * Re-attach keys an imported package carried that this schema does not
     * model, without letting them overwrite anything it does.
     *
     * This is what stops a round trip through this product from quietly
     * deleting fields an official H5P editor wrote.
     *
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    protected function mergePreservedKeys(array $params, ?string $cachedJson): array
    {
        if (! $cachedJson) {
            return $params;
        }

        $cached = json_decode($cachedJson, true);
        if (! is_array($cached)) {
            return $params;
        }

        foreach ($cached as $key => $value) {
            if (! array_key_exists($key, $params)) {
                $params[$key] = $value;
            }
        }

        return $params;
    }

    /**
     * "H5P.MemoryGame 1.3" for the `library` column and the export manifest.
     *
     * One implementation over config/h5p_libraries.php, so a version bump is
     * a config edit and nothing else.
     */
    public function libraryVersionString(string $registryCode): string
    {
        $library = (array) config('h5p_libraries.libraries.' . $registryCode, []);

        return sprintf(
            '%s %d.%d',
            $library['machine_name'] ?? 'H5P.Unknown',
            $library['major_version'] ?? 1,
            $library['minor_version'] ?? 0
        );
    }

    /** The bare machine name, e.g. "H5P.MemoryGame". */
    public function machineName(string $registryCode): string
    {
        return (string) (config('h5p_libraries.libraries.' . $registryCode . '.machine_name') ?? 'H5P.Unknown');
    }
}
