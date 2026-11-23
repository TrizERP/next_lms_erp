<?php

namespace App\Services\PAL\LabSpec;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Where lab specifications live, and the only place that decides which one students may use.
 *
 *   - Built-in specs: JSON files in resources/pal-labs (version 1, source "builtin"), the baseline the two original
 *     experiments were converted to. They are used when no approved row exists for the chapter.
 *   - Rows in pal_experiment_lab_spec: versions authored by teachers/admins (by hand or from an AI draft).
 *
 * Rules (each enforced here, not in the controller):
 *   - A saved version is never edited. Changing a lab saves a NEW version in "draft".
 *   - A draft becomes "approved" only through approve(), which re-validates it and checks it against the chapter's
 *     concepts, and which retires the lab's previously approved version in the same step.
 *   - Students are only ever given approved specs (or, to finish what they started, the retired version they began on).
 *
 * Without the table (migration not applied yet) only the built-ins exist and authoring is refused.
 */
class LabSpecRepository
{
    private const TABLE = 'pal_experiment_lab_spec';

    /** @var array<string,array<string,mixed>>|null */
    private static ?array $builtin = null;

    public static function tableReady(): bool
    {
        try {
            return Schema::hasTable(self::TABLE);
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array<string,array<string,mixed>> lab_key => spec */
    public static function builtins(): array
    {
        if (self::$builtin === null) {
            self::$builtin = [];
            foreach (glob(dirname(__DIR__, 4).'/resources/pal-labs/*.json') ?: [] as $file) {
                $spec = json_decode((string) file_get_contents($file), true);
                if (is_array($spec) && isset($spec['lab_key'])) {
                    self::$builtin[$spec['lab_key']] = $spec;
                }
            }
        }

        return self::$builtin;
    }

    /** For tests that need to look at the built-ins fresh. */
    public static function forget(): void
    {
        self::$builtin = null;
    }

    /**
     * @param  array<string,mixed>  $spec
     * @return array<string,mixed>
     */
    private static function wrap(array $spec, int $version, string $source, ?int $rowId = null, string $status = 'approved'): array
    {
        return ['id' => $rowId, 'lab_key' => $spec['lab_key'], 'version' => $version, 'source' => $source, 'status' => $status, 'spec' => $spec];
    }

    /**
     * A specification as a student may receive it: the marker's key (what the experiment should show, and why) removed.
     * Everything a student's browser needs to run the lab is left; nothing that would give the answer is.
     *
     * @param  array<string,mixed>  $spec
     * @return array<string,mixed>
     */
    public static function studentView(array $spec): array
    {
        unset($spec['marker']);

        return $spec;
    }

    /**
     * The approved lab for a chapter, as students see it. A school's own approved lab wins over the shared one.
     *
     * @return array<string,mixed>|null
     */
    public static function approvedForChapter(int $chapterId, int $subInstituteId = 0): ?array
    {
        if (self::tableReady()) {
            $row = DB::table(self::TABLE)->where('chapter_id', $chapterId)->where('status', 'approved')
                ->whereIn('sub_institute_id', array_unique([$subInstituteId, 0]))
                ->orderByDesc('sub_institute_id')->orderByDesc('version')->first();
            if ($row !== null) {
                return self::wrap((array) json_decode($row->spec, true), (int) $row->version, (string) $row->source, (int) $row->id);
            }
        }
        foreach (self::builtins() as $spec) {
            if (in_array($chapterId, $spec['chapter_ids'], true)) {
                return self::wrap($spec, 1, 'builtin');
            }
        }

        return null;
    }

    /**
     * A lab by key: the given version if it is live or was live (so a student can finish it), else the approved one.
     *
     * @return array<string,mixed>|null
     */
    public static function byKey(string $labKey, ?int $version = null, int $subInstituteId = 0): ?array
    {
        if (self::tableReady()) {
            $q = DB::table(self::TABLE)->where('lab_key', $labKey)->whereIn('sub_institute_id', array_unique([$subInstituteId, 0]));
            if ($version !== null) {
                $row = (clone $q)->where('version', $version)->whereIn('status', ['approved', 'retired'])->orderByDesc('sub_institute_id')->first();
                if ($row !== null) {
                    return self::wrap((array) json_decode($row->spec, true), (int) $row->version, (string) $row->source, (int) $row->id, $row->status);
                }
            }
            $row = (clone $q)->where('status', 'approved')->orderByDesc('sub_institute_id')->orderByDesc('version')->first();
            if ($row !== null) {
                return self::wrap((array) json_decode($row->spec, true), (int) $row->version, (string) $row->source, (int) $row->id);
            }
        }
        $b = self::builtins()[$labKey] ?? null;

        return $b === null ? null : self::wrap($b, 1, 'builtin');
    }

    /**
     * Every version of the labs for a chapter, for the teacher: the built-in baseline plus saved rows.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function listForChapter(int $chapterId, int $subInstituteId): array
    {
        $out = [];
        foreach (self::builtins() as $spec) {
            if (in_array($chapterId, $spec['chapter_ids'], true)) {
                $out[] = ['id' => null, 'lab_key' => $spec['lab_key'], 'version' => 1, 'status' => 'approved', 'source' => 'builtin', 'title' => $spec['title'], 'created_at' => null, 'approved_at' => null, 'validation' => null];
            }
        }
        if (self::tableReady()) {
            $rows = DB::table(self::TABLE)->where('chapter_id', $chapterId)->whereIn('sub_institute_id', array_unique([$subInstituteId, 0]))
                ->orderByDesc('id')->get();
            foreach ($rows as $r) {
                $spec = (array) json_decode($r->spec, true);
                $out[] = [
                    'id' => (int) $r->id, 'lab_key' => $r->lab_key, 'version' => (int) $r->version, 'status' => $r->status, 'source' => $r->source,
                    'title' => $spec['title'] ?? $r->lab_key, 'created_at' => $r->created_at, 'approved_at' => $r->approved_at,
                    'reject_reason' => $r->reject_reason, 'validation' => json_decode((string) $r->validation, true),
                ];
            }
        }

        return $out;
    }

    /** @return array<string,mixed>|null a stored row with its spec decoded */
    public static function row(int $id, int $subInstituteId): ?array
    {
        if (! self::tableReady()) {
            return null;
        }
        $r = DB::table(self::TABLE)->where('id', $id)->whereIn('sub_institute_id', array_unique([$subInstituteId, 0]))->first();

        return $r === null ? null : [
            'id' => (int) $r->id, 'lab_key' => $r->lab_key, 'version' => (int) $r->version, 'chapter_id' => (int) $r->chapter_id,
            'sub_institute_id' => (int) $r->sub_institute_id, 'status' => $r->status, 'source' => $r->source,
            'spec' => (array) json_decode($r->spec, true), 'validation' => json_decode((string) $r->validation, true),
            'created_by' => $r->created_by, 'approved_by' => $r->approved_by, 'approved_at' => $r->approved_at, 'reject_reason' => $r->reject_reason,
        ];
    }

    /**
     * Saves a new draft version of a lab. Never touches an existing version.
     *
     * @param  array<string,mixed>  $spec
     * @param  array<string,mixed>  $validation
     * @return array<string,mixed> the new row
     */
    public static function saveDraft(array $spec, string $source, int $userId, int $subInstituteId, array $validation): array
    {
        if (! self::tableReady()) {
            throw new \RuntimeException('Lab storage is not ready (the pal_experiment_lab_spec migration has not been applied).');
        }
        $labKey = (string) $spec['lab_key'];
        $chapterId = (int) ($spec['chapter_ids'][0] ?? 0);

        return DB::transaction(function () use ($spec, $source, $userId, $subInstituteId, $validation, $labKey, $chapterId) {
            $last = (int) DB::table(self::TABLE)->where('lab_key', $labKey)->where('sub_institute_id', $subInstituteId)->max('version');
            // The first version of a school's copy of a built-in lab continues from the built-in's version 1.
            $version = max($last, isset(self::builtins()[$labKey]) ? 1 : 0) + 1;
            $id = DB::table(self::TABLE)->insertGetId([
                'lab_key' => $labKey, 'version' => $version, 'chapter_id' => $chapterId, 'sub_institute_id' => $subInstituteId,
                'status' => 'draft', 'source' => $source,
                'spec' => json_encode($spec, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'spec_hash' => sha1(json_encode($spec)),
                'validation' => json_encode($validation, JSON_UNESCAPED_UNICODE), 'created_by' => $userId,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            return self::row($id, $subInstituteId);
        });
    }

    /**
     * Approves a draft: it must validate with no errors, and its concepts must be concepts of its chapter. The lab's
     * previously approved version is retired in the same transaction, so there is never a moment with two live versions.
     *
     * @param  array<int,string>  $chapterConcepts  the concept names of the chapter, for the mapping check
     * @return array{ok:bool,errors:array<int,string>}
     */
    public static function approve(int $id, int $userId, int $subInstituteId, array $chapterConcepts): array
    {
        $row = self::row($id, $subInstituteId);
        if ($row === null) {
            return ['ok' => false, 'errors' => ['That lab version was not found.']];
        }
        if ($row['status'] !== 'draft') {
            return ['ok' => false, 'errors' => ["Only a draft can be approved (this one is {$row['status']})."]];
        }
        $v = LabSpecValidator::validate($row['spec']);
        $errors = $v['errors'];
        $known = array_map('mb_strtolower', $chapterConcepts);
        foreach ($row['spec']['concepts'] ?? [] as $c) {
            if (! in_array(mb_strtolower((string) $c), $known, true)) {
                $errors[] = "concepts: '$c' is not a concept of this chapter.";
            }
        }
        if (! in_array($row['chapter_id'], $row['spec']['chapter_ids'] ?? [], true)) {
            $errors[] = 'chapter_ids must include the chapter this version belongs to.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        DB::transaction(function () use ($row, $userId) {
            DB::table(self::TABLE)->where('lab_key', $row['lab_key'])->where('sub_institute_id', $row['sub_institute_id'])
                ->where('status', 'approved')->update(['status' => 'retired', 'updated_at' => now()]);
            DB::table(self::TABLE)->where('id', $row['id'])->update([
                'status' => 'approved', 'approved_by' => $userId, 'approved_at' => now(), 'updated_at' => now(),
                'validation' => json_encode(LabSpecValidator::validate($row['spec']), JSON_UNESCAPED_UNICODE),
            ]);
        });

        return ['ok' => true, 'errors' => []];
    }

    public static function reject(int $id, int $subInstituteId, string $reason): bool
    {
        $row = self::row($id, $subInstituteId);
        if ($row === null || $row['status'] !== 'draft') {
            return false;
        }
        DB::table(self::TABLE)->where('id', $id)->update(['status' => 'rejected', 'reject_reason' => mb_substr($reason, 0, 300), 'updated_at' => now()]);

        return true;
    }
}
