<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Closes the idempotency gap named in docs/context-graph/KNOWN_GAPS.md: nothing
 * stopped `DecisionGate::seedOutcome()` inserting a second `ai_outcomes` row for a
 * recommendation that was decided twice, silently corrupting
 * `OutcomeTracker::effectivenessByActionType()`'s aggregate. `DecisionGate::record()`
 * now guards the race at the application layer; this is the database-enforced
 * backstop, mirroring G2G's `g2g_event` unique constraint. MySQL permits multiple
 * NULLs under a unique index, so outcomes not tied to a recommendation (if any)
 * are unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_outcomes', function (Blueprint $table) {
            $table->unique('recommendation_id', 'ai_outcomes_recommendation_unique');
        });
    }

    public function down(): void
    {
        Schema::table('ai_outcomes', function (Blueprint $table) {
            $table->dropUnique('ai_outcomes_recommendation_unique');
        });
    }
};
