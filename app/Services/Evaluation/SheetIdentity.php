<?php

namespace App\Services\Evaluation;

use Illuminate\Support\Facades\Schema;

/**
 * What it means for an answer sheet to have a student.
 *
 * `student_id` on a sheet holds one of two different things, and everything that
 * writes to a sheet has to know which:
 *
 *  - a SUGGESTION -- the matcher's best reading of the identity block, which
 *    nobody has looked at yet; or
 *  - a CONFIRMED assignment -- a teacher agreed with the suggestion, or chose
 *    the student themselves.
 *
 * Only the second may ever be relied on. A confirmed assignment is never
 * replaced by a re-read, a retry or a late-finishing job; a suggestion is
 * refreshed freely until someone confirms it.
 *
 * "Confirmed" is `identity_confirmed_at` where that column exists (see the
 * 2026_10_13_110000 migration) and, always, `identity_source = 'manual'` -- a
 * teacher choosing a student by hand was the only teacher decision recorded
 * before the column, and it must keep meaning the same thing.
 */
final class SheetIdentity
{
    /** The sheet's identity is being read in the background. */
    public const STATUS_IDENTIFYING = 'Identifying';

    /** Read and matched; waiting for a teacher before any marking begins. */
    public const STATUS_AWAITING = 'Awaiting identity';

    /** Statuses in which marks do not exist yet, so none can be edited or approved. */
    public const PRE_EVALUATION = ['Identifying', 'Awaiting identity'];

    /**
     * Said when a sheet's stored scan is not on the machine running the job. Scans live on the
     * local disk of whichever server took the upload (the database is shared, the files are
     * not), so a sheet uploaded elsewhere -- or since removed -- cannot be read here. The fix
     * is to put the sheet in again, which a teacher can do; so the message says so.
     * Kept under the 250 characters `failure_reason` holds.
     */
    public const MISSING_FILE_MESSAGE = 'The uploaded file could not be found on the server. It may have been uploaded from another machine, or removed. Delete this sheet and upload it again.';

    /** Statuses in which the system, not a person, is expected to move the sheet on. */
    public const IN_FLIGHT = ['Identifying', 'Pending', 'Processing'];

    /**
     * How long an in-flight sheet may go untouched before it is treated as stuck.
     *
     * The slowest honest case is the identity job: four tries of up to 300s with 30s, 120s and
     * 300s of backoff between them is about 28 minutes, and every evaluation attempt re-stamps
     * the sheet. 45 minutes is comfortably past that, so a sheet that is merely slow is never
     * called stuck, while one whose worker has died or was never started can be put back on the
     * queue by a teacher instead of waiting for someone to notice.
     */
    public const STALLED_AFTER_MINUTES = 45;

    // Why a sheet could not be matched cleanly. Shown to the teacher as words.
    public const ISSUE_UNREADABLE = 'unreadable';
    public const ISSUE_UNAVAILABLE = 'unavailable';
    public const ISSUE_NOT_ON_ROLL = 'not_on_roll';
    public const ISSUE_AMBIGUOUS = 'ambiguous';
    public const ISSUE_CONFLICT = 'conflict';
    public const ISSUE_DUPLICATE = 'duplicate';
    public const ISSUE_LOW_CONFIDENCE = 'low_confidence';
    public const ISSUE_MULTIPLE_STUDENTS = 'multiple_students';

    /**
     * Sources strong enough to be confirmed in bulk. A handwritten name is the
     * weakest evidence there is, so a name-only match is always confirmed one
     * sheet at a time, by someone looking at the scan.
     */
    public const BULK_CONFIRMABLE_SOURCES = ['enrollment_no', 'roll_no'];

    /** How long "the columns are not there" is believed before asking the database again. */
    private const ABSENT_RECHECK_SECONDS = 60;

    private static ?bool $columns = null;

    private static int $checkedAt = 0;

    /**
     * Whether the migration that adds the confirmation columns has been applied.
     *
     * "Present" is remembered for the life of the process: a column does not un-exist.
     * "Absent" is only remembered for a minute, because a queue worker or a long-lived
     * server process that started before the migration ran would otherwise go on writing
     * the safe fallback (`manual`) until somebody restarted it. Worst case after migrating
     * is now a minute of the fallback, not "until the next restart".
     */
    public static function columnsPresent(): bool
    {
        if (self::$columns === true) {
            return true;
        }

        $now = now()->getTimestamp();

        if (self::$columns === false && ($now - self::$checkedAt) < self::ABSENT_RECHECK_SECONDS) {
            return false;
        }

        self::$checkedAt = $now;

        return self::$columns = Schema::hasColumn('exam_evaluation_sheet', 'identity_confirmed_at');
    }

    /** For tests that change the schema between cases. */
    public static function forgetSchema(): void
    {
        self::$columns = null;
        self::$checkedAt = 0;
    }

    /**
     * Whether an in-flight sheet has gone so long without a change that its job is presumed
     * lost (see STALLED_AFTER_MINUTES). Only ever true for a sheet the system is meant to be
     * working on; a sheet waiting for a person, or finished, is never "stalled".
     *
     * @param  object|array<string,mixed>  $sheet
     */
    public static function isStalled(object|array $sheet): bool
    {
        $read = static fn (string $key) => is_array($sheet) ? ($sheet[$key] ?? null) : ($sheet->{$key} ?? null);

        if (! in_array($read('status'), self::IN_FLIGHT, true) || empty($read('updated_at'))) {
            return false;
        }

        try {
            return \Illuminate\Support\Carbon::parse((string) $read('updated_at'))->lt(now()->subMinutes(self::STALLED_AFTER_MINUTES));
        } catch (\Throwable) {
            return false;
        }
    }

    /** @param  object|array<string,mixed>|null  $sheet */
    public static function isConfirmed(object|array|null $sheet): bool
    {
        if ($sheet === null) {
            return false;
        }

        $read = static fn (string $key) => is_array($sheet) ? ($sheet[$key] ?? null) : ($sheet->{$key} ?? null);

        if (! $read('student_id')) {
            return false;
        }

        return $read('identity_source') === 'manual' || ! empty($read('identity_confirmed_at'));
    }

    /**
     * The columns that record a teacher agreeing with the sheet's CURRENT student.
     *
     * Before the migration there is nowhere to say "confirmed" apart from the
     * method, so the safe equivalent is used: the sheet is marked `manual`,
     * which every job already refuses to overwrite.
     *
     * @return array<string,mixed>
     */
    public static function confirmationFields(?int $actor): array
    {
        if (self::columnsPresent()) {
            return ['identity_confirmed_at' => now(), 'identity_confirmed_by' => $actor];
        }

        return ['identity_source' => 'manual'];
    }

    /**
     * The columns to reset when the assignment changes or is removed.
     *
     * @return array<string,mixed>
     */
    public static function clearedConfirmation(): array
    {
        return self::columnsPresent()
            ? ['identity_confirmed_at' => null, 'identity_confirmed_by' => null]
            : [];
    }

    /**
     * Whether a suggestion may be confirmed along with others in one click: a
     * clean match on a number, with nothing flagged against it.
     *
     * @param  object|array<string,mixed>  $sheet
     */
    public static function isBulkConfirmable(object|array $sheet): bool
    {
        $read = static fn (string $key) => is_array($sheet) ? ($sheet[$key] ?? null) : ($sheet->{$key} ?? null);

        $json = $read('ai_result_json');

        return $read('status') === self::STATUS_AWAITING
            && $read('student_id')
            && ! self::isConfirmed($sheet)
            && in_array($read('identity_source'), self::BULK_CONFIRMABLE_SOURCES, true)
            && (self::block(is_string($json) ? $json : null)['issue'] ?? null) === null;
    }

    /**
     * What the identity stage recorded beyond the columns: why a sheet could
     * not be matched, which students were in the running, and where on the file
     * the details were found. Kept inside `ai_result_json` -- it is the AI's
     * reading of the sheet, not a teacher's decision -- so it needs no column.
     *
     * @return array<string,mixed>
     */
    public static function block(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) && is_array($decoded['identity'] ?? null) ? $decoded['identity'] : [];
    }

    /**
     * `ai_result_json` for a sheet, rewritten with the evaluation's result while
     * keeping the identity block the earlier stage left in it.
     *
     * @param  array<string,mixed>  $result
     */
    public static function withIdentityBlock(?string $existingJson, array $result): string
    {
        $identity = self::block($existingJson);

        if ($identity !== []) {
            $result['identity'] = $identity;
        }

        return (string) json_encode($result);
    }
}
