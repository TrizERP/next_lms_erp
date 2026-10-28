<?php

namespace App\Services\lms\Simulation;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Stores a 3D model or an image for Simulation Learning and registers it: the file goes to the private bucket under its sha256 (`k12_sim_assets/`), and a row in
 * `lms_simulation_asset` says whose it is and under which licence and credit it may be shown. Two doors lead here and both use this class, so
 * they cannot disagree about what a file is or how it is kept:
 *
 *   - the artisan command `lms:simulations-assets-copy` (an operator with a shell; copies the platform's models from hp_erp's prefix to K12's
 *     and checks their sha256; no quota, no deep inspection);
 *   - POST /api/lms/prayogshala/simulations/assets (a platform-tenant author; upload() below: the same file checks, then the contents are inspected,
 *     the tenant's quota is respected, and the same file twice is the same asset).
 *
 * WHAT A FILE IS comes from its first bytes (SimulationStore::assetType), never from its name; the checks every door makes are in inspect().
 * The checks only an upload makes - the contents of a model (GlbInspector: embedded data only, extension allow-list, triangle, picture and
 * memory budgets) and the size of a picture - are in inspectContents(). The command does not run them: it is the operator's tool, and the
 * models it has put in the bucket were chosen and tested by the operator.
 *
 * QUOTA. An organisation (never the platform) may hold 200 files and 500 MB in all, counted from its live rows in the registry. It is checked
 * only when a file would be new; the same file again costs nothing. It is a soft limit: two uploads racing at the edge can both pass.
 */
final class SimulationAssetService
{
    public const MAX_FILES = 200;
    public const MAX_BYTES = 524288000;   // 500 MB

    /** The extensions of the kinds of file this takes: one of them on a file has to be the right one for what its bytes are. */
    private const EXTENSIONS = ['glb', 'png', 'jpg', 'jpeg', 'webp', 'hdr'];

    public function __construct(private readonly SimulationStore $store, private readonly GlbInspector $inspector)
    {
    }

    // ---------------------------------------------------------------- the details given with a file

    /**
     * What is wrong with the name, licence, credit and source given with a file, by field: the part of the sentence after the field's name
     * ("is 120 characters at most."). Emptiness is not checked here: the doors say that in their own words.
     *
     * @return array<string, string>
     */
    public function detailProblems(string $name, string $licence, string $credit, string $source): array
    {
        // text that is not valid UTF-8 would stop the database (and the JSON answer) with a server error: refuse it plainly
        $text = static fn (string $value): bool => ! mb_check_encoding($value, 'UTF-8');

        return array_filter([
            'name' => $text($name) ? 'must be valid UTF-8 text.' : (mb_strlen($name) > 120 ? 'is 120 characters at most.' : null),
            'licence' => $text($licence) ? 'must be valid UTF-8 text.' : (mb_strlen($licence) > 80 ? 'is 80 characters at most.' : null),
            'credit' => $text($credit) ? 'must be valid UTF-8 text.' : (mb_strlen($credit) > 300 ? 'is 300 characters at most.' : null),
            'source' => $text($source) ? 'must be valid UTF-8 text.' : ($source !== '' && (mb_strlen($source) > 500 || ! preg_match('#^https://[^\s"\'<>]+$#', $source)) ? 'must be an https address.' : null),
        ]);
    }

    // ---------------------------------------------------------------- the file

    /**
     * What the file at `$file` is, or why it is refused: the kind from its first bytes, a name that does not contradict it, a size within
     * the limit for its kind, and for a model a glTF 2 header whose length is the file's. `$fileName` is the name the person gave it.
     *
     * @return array{kind: string, mime: string, ext: string, exts: list<string>, max: int, label: string, bytes: int}
     *
     * @throws SimulationException  422, or 413 when the file is over the size limit
     */
    public function inspect(string $file, string $fileName): array
    {
        $bytes = (int) filesize($file);
        $in = fopen($file, 'rb');
        $head = (string) fread($in, 12);
        fclose($in);
        $type = $this->store->assetType($head);
        if ($type === null) {
            throw new SimulationException(['That is not a file this takes: a binary glTF model (.glb), a PNG, JPEG or WebP image, or a Radiance HDR image (.hdr). What it is comes from its first bytes, not its name (SVG, GIF and HTML are refused).']);
        }
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (in_array($extension, self::EXTENSIONS, true) && ! in_array($extension, $type['exts'], true)) {
            throw new SimulationException(["The file is named .{$extension} but it is {$type['label']}: check it is the file you meant, and name it .{$type['exts'][0]}."]);
        }
        if ($bytes > $type['max']) {
            throw new SimulationException(['The file is ' . round($bytes / 1048576, 1) . ' MB; the limit for ' . (['image' => 'an image', 'hdr' => 'an HDR image'][$type['kind']] ?? 'a model') . ' is ' . ($type['max'] / 1048576) . ' MB.'], 413);
        }
        if ($type['kind'] === 'model') {
            ['version' => $version, 'length' => $length] = unpack('Vversion/Vlength', substr($head, 4));
            if ($version !== 2) {
                throw new SimulationException(["Only glTF version 2 is accepted; this is version {$version}."]);
            }
            if ($length !== $bytes) {
                throw new SimulationException(["The file's header declares {$length} bytes but the file is {$bytes}: it is cut short or has something added."]);
            }
        }

        return $type + ['bytes' => $bytes];
    }

    /**
     * The checks an upload adds to inspect(): a model's contents (GlbInspector) or a picture's size.
     *
     * @param  array{kind: string}  $type  from inspect()
     *
     * @throws SimulationException
     */
    public function inspectContents(string $file, array $type, string $fileName): void
    {
        if ($type['kind'] === 'model') {
            $this->inspector->inspect($file, $fileName);
        } elseif ($type['kind'] === 'hdr') {
            $this->inspector->inspectHdr($file, $fileName);
        } else {
            $this->inspector->inspectPicture($file, $fileName);
        }
    }

    // ---------------------------------------------------------------- the registry

    /** The registry row for this file and owner (NULL = platform), removed or not: the unique index covers removed rows too. */
    public function find(?int $owner, string $sha256): ?object
    {
        return DB::table('lms_simulation_asset')->where('sha256', $sha256)
            ->where(fn ($w) => $owner === null ? $w->whereNull('sub_institute_id') : $w->where('sub_institute_id', $owner))->first();
    }

    /**
     * What an organisation holds now: its live files and their bytes.
     *
     * @return array{files: int, bytes: int}
     */
    public function usage(int $owner): array
    {
        $row = DB::table('lms_simulation_asset')->where('sub_institute_id', $owner)->whereNull('deleted_at')
            ->selectRaw('COUNT(*) as files, COALESCE(SUM(bytes), 0) as bytes')->first();

        return ['files' => (int) $row->files, 'bytes' => (int) $row->bytes];
    }

    /**
     * Whether one more file of `$bytes` fits in the organisation's quota.
     *
     * @throws SimulationException
     */
    public function assertRoom(int $owner, int $bytes): void
    {
        $use = $this->usage($owner);
        $problems = [];
        if ($use['files'] + 1 > self::MAX_FILES) {
            $problems[] = "Your organisation already keeps {$use['files']} uploaded files, which is the most it may hold (" . self::MAX_FILES . ').';
        }
        if ($use['bytes'] + $bytes > self::MAX_BYTES) {
            $problems[] = 'This file (' . $this->mb($bytes) . ' MB) would take your organisation\'s uploaded files to ' . $this->mb($use['bytes'] + $bytes) . ' MB; the most it may hold is ' . $this->mb(self::MAX_BYTES) . ' MB.';
        }
        if ($problems !== []) {
            throw new SimulationException($problems);
        }
    }

    /**
     * Puts the file on the disk, PRIVATE, unless it is already there.
     *
     * @return bool  whether it was uploaded now
     *
     * @throws \Throwable  whatever the disk throws, or \RuntimeException when it refuses the file
     */
    public function putFile(string $diskName, string $storagePath, string $file, string $mime): bool
    {
        $disk = Storage::disk($diskName);
        $uploaded = ! $disk->exists($storagePath);
        if ($uploaded) {
            $stream = fopen($file, 'rb');
            try {
                if ($disk->put($storagePath, $stream, ['visibility' => 'private', 'mimetype' => $mime]) === false) {
                    throw new \RuntimeException('the disk did not accept the file');
                }
            } finally {
                fclose($stream);
            }
        }

        return $uploaded;
    }

    /**
     * Writes the registration: a new row, or the existing row brought back when it had been removed (with the details given now), or - when
     * it is registered - nothing at all (its details are left as they were).
     *
     * @param  array{name: string, licence: string, credit: string, source: string}  $details
     * @param  array{kind: string, mime: string, bytes: int}  $type
     * @return array{outcome: 'registered'|'restored'|'existing', id: int}
     */
    public function record(?object $existing, ?int $owner, string $sha256, array $type, string $storagePath, array $details, ?int $userId): array
    {
        $now = now();
        $card = ['name' => $details['name'], 'licence' => $details['licence'], 'credit' => $details['credit'], 'source_url' => $details['source'] === '' ? null : $details['source'], 'updated_at' => $now];
        if (! $existing) {
            $id = (int) DB::table('lms_simulation_asset')->insertGetId($card + [
                'sub_institute_id' => $owner,
                'sha256' => $sha256,
                'kind' => $type['kind'],
                'mime' => $type['mime'],
                'bytes' => $type['bytes'],
                'storage_path' => $storagePath,
                'created_by' => $userId,
                'created_at' => $now,
            ]);

            return ['outcome' => 'registered', 'id' => $id];
        }
        if ($existing->deleted_at !== null) {
            // it had been removed; the unique index would refuse a second row for an organisation, and a platform one would be a twin
            DB::table('lms_simulation_asset')->where('id', $existing->id)->update($card + ['deleted_at' => null]);

            return ['outcome' => 'restored', 'id' => (int) $existing->id];
        }

        return ['outcome' => 'existing', 'id' => (int) $existing->id];
    }

    // ---------------------------------------------------------------- the upload door

    /**
     * An author's upload: everything above, in order, for an organisation's own file. Idempotent: the same file again for the same
     * organisation changes nothing and answers `created: false` (its details stay as they were, and the file is put back on the disk if it
     * had gone missing). Every problem with the details and the file is listed together; nothing is stored until all of it passes.
     *
     * @param  array{name?: ?string, licence?: ?string, credit?: ?string, source?: ?string}  $details
     * @return array{sha256: string, kind: string, mime: string, bytes: int, created: bool, name: string, licence: ?string, credit: ?string, source_url: ?string}
     *
     * @throws SimulationException  422 (413 for a file over the size limit, 503 when the file could not be stored)
     */
    public function upload(int $owner, ?int $userId, string $file, string $fileName, array $details): array
    {
        $fileName = mb_scrub($fileName);   // a name with broken bytes must not reach the database or a message
        $licence = trim((string) ($details['licence'] ?? ''));
        $credit = trim((string) ($details['credit'] ?? ''));
        $source = trim((string) ($details['source'] ?? ''));
        $name = trim((string) ($details['name'] ?? ''));
        if ($name === '') {
            $name = trim(pathinfo($fileName, PATHINFO_FILENAME)) ?: 'Untitled';
        }

        $problems = [];
        if ($licence === '') {
            $problems[] = '"licence" is required: the licence the file may be used under, for example "CC BY 4.0".';
        }
        if ($credit === '') {
            $problems[] = '"credit" is required: who made it, written as it should be shown to learners.';
        }
        foreach ($this->detailProblems($name, $licence, $credit, $source) as $field => $rest) {
            $problems[] = "\"{$field}\" {$rest}";
        }
        $status = 422;
        $type = null;
        try {
            $type = $this->inspect($file, $fileName);
            $this->inspectContents($file, $type, $fileName);
        } catch (SimulationException $e) {
            $problems = [...$problems, ...$e->problems];
            $status = $e->getCode() === 413 ? 413 : 422;
        }
        if ($problems !== []) {
            throw new SimulationException($problems, $status);
        }

        $sha256 = (string) hash_file('sha256', $file);
        $existing = $this->find($owner, $sha256);
        if ($existing === null || $existing->deleted_at !== null) {
            $this->assertRoom($owner, $type['bytes']);
        }

        $storagePath = $this->store->assetPath($sha256, $type['ext']);
        try {
            $putNow = $this->putFile(SimulationStore::ASSET_DISK, $storagePath, $file, $type['mime']);
        } catch (\Throwable $e) {
            Log::error('Simulation asset upload: the file could not be put on the disk.', ['organisation' => $owner, 'sha256' => $sha256, 'error' => $e->getMessage()]);

            throw new SimulationException(['The file could not be saved just now. Try again in a minute, and tell your administrator if it keeps happening.'], 503);
        }

        $details = ['name' => $name, 'licence' => $licence, 'credit' => $credit, 'source' => $source];
        try {
            $result = $this->record($existing, $owner, $sha256, $type, $storagePath, $details, $userId);
        } catch (UniqueConstraintViolationException $e) {
            // the same file was being registered by another request at the same moment: it is registered now, which is all this one wanted
            $existing = $this->find($owner, $sha256);
            if (! $existing) {
                throw $e;
            }
            $result = ['outcome' => 'existing', 'id' => (int) $existing->id];
        } catch (Throwable $e) {
            // the file is on the disk but has no row, so the quota cannot see it: take it away again - ONLY when this very upload put it there. An
            // object that was already on the disk (copied by lms:simulations-assets-copy, or registered once and removed) is never ours to delete.
            if ($existing === null && $putNow) {
                try {
                    Storage::disk(SimulationStore::ASSET_DISK)->delete($storagePath);
                } catch (Throwable) {
                    // nothing more can be done; the log below says which object
                }
            }
            Log::error('Simulation asset upload: the file was stored but could not be registered.', ['organisation' => $owner, 'sha256' => $sha256, 'error' => $e->getMessage()]);

            throw new SimulationException(['The file could not be registered just now. Try again in a minute, and tell your administrator if it keeps happening.'], 503);
        }
        $row = DB::table('lms_simulation_asset')->where('id', $result['id'])->first();

        return [
            'sha256' => $sha256,
            'kind' => $row->kind,
            'mime' => $row->mime,
            'bytes' => (int) $row->bytes,
            'created' => $result['outcome'] !== 'existing',
            'name' => $row->name,
            'licence' => $row->licence,
            'credit' => $row->credit,
            'source_url' => $row->source_url,
        ];
    }

    private function mb(int $bytes): string
    {
        return rtrim(rtrim(number_format($bytes / 1048576, 1, '.', ''), '0'), '.');
    }
}
