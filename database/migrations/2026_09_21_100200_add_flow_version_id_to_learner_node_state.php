<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pin each learner's node to the flow version it was started under.
 *
 * ---------------------------------------------------------------------------
 * THE PROBLEM THIS SOLVES
 * ---------------------------------------------------------------------------
 * Once a school's flow is configurable, an administrator publishing a new
 * profile version would otherwise change the rules for learners who are
 * part-way through a concept — taught under one flow, assessed under another.
 *
 * pal_architecture_settings, the per-institute config overlay this estate
 * already has, does exactly that: it carries no version column, so an edit
 * silently re-rules everyone mid-flight. That is the defect this column exists
 * not to repeat.
 *
 * Mirrors workflow_runs.version_id (2026_08_20_000005_create_workflow_tables.php),
 * whose docblock states the invariant plainly: a running instance keeps
 * executing the version it started on even after the definition is edited.
 *
 * ---------------------------------------------------------------------------
 * NULLABLE AND DELIBERATELY UNBACKFILLED
 * ---------------------------------------------------------------------------
 * A NULL here is not a gap. It means "this node's state was created before
 * flow versioning existed", and it resolves to the shipped `standard` profile
 * for the life of that concept — which is exactly what those learners have
 * been getting all along, so nothing about their experience changes.
 *
 * Backfilling would be actively harmful, for two reasons:
 *
 *   1. It would assert something nobody recorded. Assigning a past cohort to
 *      version 1 claims they were taught under it; they were taught under
 *      hardcoded PHP that predates the concept of a version. The same
 *      reasoning pal_curriculum_versions gives at
 *      2026_09_08_130000_create_pal_curriculum_versions_table.php:66-68.
 *
 *   2. It would require a DB::table()->update() across learner_node_state,
 *      which bypasses the model's saved/deleted events and therefore does NOT
 *      bump LearnerNodeState::$writeVersion — the static counter
 *      EsoPolicyService::learnerStates() invalidates its cache against. Any
 *      request in flight would carry on serving pre-backfill learner state
 *      from memory. See the model's docblock, lines 46-76.
 *
 * ---------------------------------------------------------------------------
 * NO INDEX, ON PURPOSE
 * ---------------------------------------------------------------------------
 * This column is never a query predicate. It is read off rows that
 * learnerStates() has already loaded — one query for the learner's whole set,
 * cached — so an index would buy nothing and cost a write on every state
 * change, on the hottest table in the engine.
 *
 * If a future report needs "all learners on version N", add the index in its
 * own migration with that justification attached, rather than speculatively
 * here.
 *
 * ---------------------------------------------------------------------------
 * ROLLBACK - THIS ONE GENUINELY RUNS
 * ---------------------------------------------------------------------------
 * Unlike the create-table migrations in this estate, down() here is real.
 * AppServiceProvider.php:76-90 throws on SQL containing the literal string
 * "DROP TABLE"; dropColumn() emits `alter table ... drop ...`, which does not
 * match, so it executes normally.
 *
 * Additive: nothing existing is altered, and a host that runs this and stops
 * behaves exactly as it did before.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('learner_node_state')) {
            return;
        }

        if (Schema::hasColumn('learner_node_state', 'flow_version_id')) {
            return;
        }

        Schema::table('learner_node_state', function (Blueprint $table) {
            $table->unsignedBigInteger('flow_version_id')
                ->nullable()
                ->after('sub_institute_id')
                ->comment('pal_flow_profile_versions.id - the flow this learner started this node under; NULL predates versioning');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('learner_node_state')) {
            return;
        }

        if (! Schema::hasColumn('learner_node_state', 'flow_version_id')) {
            return;
        }

        // Genuinely runnable - see the ROLLBACK note in the docblock.
        Schema::table('learner_node_state', function (Blueprint $table) {
            $table->dropColumn('flow_version_id');
        });
    }
};
