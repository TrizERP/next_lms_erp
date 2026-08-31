<?php

namespace App\Domain\K12\AcademicRisk;

/**
 * Whether a piece of homework counts as done.
 *
 * One definition, shared by the detector that can flag a child for missing work and the
 * MCP tool that lists homework in a chat reply. Two copies of this rule would be a
 * genuine hazard rather than a tidiness problem: a teacher could be told an assignment
 * was submitted by one surface while the risk agent was counting it as missed by the
 * other, and neither would be visibly wrong.
 *
 * The estate stores completion two ways and neither is reliable alone, so both are read:
 * an explicit `completion_status` where a school sets one, and otherwise the presence of
 * a `submission_date`. An unrecognised status falls through to the date rather than
 * being guessed at.
 */
final class HomeworkCompletion
{
    /** Values a school might use to mean "done". */
    private const DONE = ['1', 'y', 'yes', 'completed', 'complete', 'submitted', 'done'];

    /** Values a school might use to mean "not done". */
    private const NOT_DONE = ['0', 'n', 'no', 'pending', 'incomplete', 'not submitted'];

    /**
     * True when the item has not been submitted.
     *
     * @param  object|array<string, mixed>  $item  Needs `completion_status` and `submission_date`.
     */
    public static function isMissed(object|array $item): bool
    {
        $item = is_array($item) ? (object) $item : $item;

        $status = $item->completion_status ?? null;

        if ($status !== null && $status !== '') {
            $normalized = strtolower(trim((string) $status));

            if (in_array($normalized, self::DONE, true)) {
                return false;
            }

            if (in_array($normalized, self::NOT_DONE, true)) {
                return true;
            }
        }

        return empty($item->submission_date);
    }

    /**
     * A label for the item's state, for a reply a person reads.
     *
     * @param  object|array<string, mixed>  $item
     */
    public static function label(object|array $item): string
    {
        $item = is_array($item) ? (object) $item : $item;

        if (! self::isMissed($item)) {
            return empty($item->submission_date) ? 'completed' : 'submitted';
        }

        $due = $item->date ?? null;

        if ($due !== null && $due !== '' && $due < now()->toDateString()) {
            return 'overdue';
        }

        return 'pending';
    }
}
