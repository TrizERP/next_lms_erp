<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Records WHICH authored rule produced a recommendation.
 *
 * `selection_reason` already carries the rule key inside a human-readable
 * sentence, which is fine for a person reading one row and useless for the
 * question that actually matters: "this rule changed what students saw — how
 * often did it fire, and what happened after?" Prose cannot be grouped.
 *
 * This is the audit half of the Pedagogy Engine rollout (tracker #15): a rule
 * that changes what a student sees has to be traceable, on the same principle
 * as AI Governance. With the rule key as a column, `pal_recommendation_log`
 * already holds mastery_before/mastery_after and outcome, so a tier can be
 * evaluated rather than merely switched on and hoped about.
 *
 * Nullable: recommendations resolved by the hardcoded path carry no authored
 * rule, and NULL says exactly that rather than pretending one fired.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pal_recommendation_log')) {
            return;
        }

        Schema::table('pal_recommendation_log', function (Blueprint $table) {
            if (! Schema::hasColumn('pal_recommendation_log', 'tier_1_rule_key')) {
                $table->string('tier_1_rule_key', 64)->nullable()->after('selection_reason');
            }

            if (! Schema::hasColumn('pal_recommendation_log', 'tier_4_rule_key')) {
                $table->string('tier_4_rule_key', 64)->nullable()->after('tier_1_rule_key');
            }
        });

        // Guarded like the column adds above, so a re-run after a partial
        // failure does not die on "Duplicate key name".
        $exists = false;
        foreach (DB::select('SHOW INDEX FROM `pal_recommendation_log`') as $row) {
            if (($row->Key_name ?? null) === 'pal_reco_tier1_outcome_idx') {
                $exists = true;
                break;
            }
        }

        if (! $exists) {
            Schema::table('pal_recommendation_log', function (Blueprint $table) {
                // "How did this rule perform?" — the query the column exists for.
                $table->index(['tier_1_rule_key', 'outcome'], 'pal_reco_tier1_outcome_idx');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('pal_recommendation_log')) {
            return;
        }

        Schema::table('pal_recommendation_log', function (Blueprint $table) {
            $table->dropIndex('pal_reco_tier1_outcome_idx');
        });

        Schema::table('pal_recommendation_log', function (Blueprint $table) {
            foreach (['tier_1_rule_key', 'tier_4_rule_key'] as $column) {
                if (Schema::hasColumn('pal_recommendation_log', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
