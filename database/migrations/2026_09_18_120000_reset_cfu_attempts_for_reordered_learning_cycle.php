<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Grandfather learners mid-flight through the Learn -> Practice -> Check
 * reorder.
 *
 * ---------------------------------------------------------------------------
 * WHAT CHANGED UNDERNEATH THEM
 * ---------------------------------------------------------------------------
 * `cfu_attempts` used to count failed PRE-practice comprehension checks: the
 * gate sat immediately after teaching, and a failure re-served that same gate
 * with different wording.
 *
 * It now counts failed Learn -> Practice -> Check CYCLES, and it is what bounds
 * the repeat loop (EsoPolicyService::CFU_MAX_CYCLES). A learner carrying
 * attempts spent against the old gate would enter the new loop with part of
 * their allowance already gone, through no act of their own - and at
 * cfu_attempts = 1 they would get one repeat instead of two.
 *
 * ---------------------------------------------------------------------------
 * WHY THIS IS SAFE
 * ---------------------------------------------------------------------------
 * Only rows that never PASSED are touched, so no pass is revoked. `cfu_attempts`
 * is a loop guard and never a mastery rule (see the constant's docblock and
 * ADR-001 §4.1 - CFU responses are not mastery evidence), so nothing about any
 * learner's mastery, retention or evidence moves. The change is strictly
 * generous in one direction: it can only give a learner more chances, never
 * fewer.
 *
 * Deliberately NOT done here: nulling `cfu_passed_at` to force everyone back
 * through the new check. That would retroactively invalidate something earned
 * under the policy in force at the time - the same argument masteryVerdict()
 * already makes for legacy mastery - and would re-serve a check to learners
 * who are mid-practice, which reads as a regression. New nodes get the new
 * journey; the retention ladder brings the rest onto it.
 *
 * Also deliberately NOT done: trying to identify and un-stamp the
 * `cfu_passed_at` values that scoreDiagnostic()'s old skip path wrote. They are
 * indistinguishable in the data from genuine passes. That is fixed forward -
 * the skip path no longer stamps it at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('learner_node_state')) {
            return;
        }

        DB::table('learner_node_state')
            ->whereNull('cfu_passed_at')
            ->where('cfu_attempts', '>', 0)
            ->update(['cfu_attempts' => 0]);
    }

    /**
     * Deliberately a no-op.
     *
     * The prior per-row values are not recoverable, and restoring them would
     * only re-impose the defect this migration exists to clear.
     */
    public function down(): void
    {
        // Intentionally empty. See the docblock above.
    }
};
