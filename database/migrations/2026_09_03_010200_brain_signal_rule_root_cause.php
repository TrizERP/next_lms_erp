<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_08_12_000100_signal_rule_root_cause.php
 * (read-only reference; that repository is not modified).
 *
 * Every table here is hpbrain_-prefixed. The Brain was designed to share a
 * database with the institute ERP, so nothing below collides with, alters or
 * duplicates an LMS table — Organization, Departments, People, Positions and
 * Students continue to live in the LMS's own tables and are reached through
 * hpbrain_entity_mappings.
 */
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Where a rule's root-cause family is DECLARED, when it can be declared at all.
 *
 * WHY THIS BELONGS ON THE RULE AND NOT ON THE SIGNAL. A root-cause family is a
 * fixed property of the CONDITION a rule detects, not a judgement about the
 * individual occurrence — the reasoning RiskAnalyzer already states for its own
 * generators: "a missing column is always an Information cause, a single person
 * carrying the work is always Capacity". Recording it per signal would invite a
 * different answer each run for the same rule, which is how a taxonomy stops
 * being reproducible. Recording it per rule makes it reviewable in one query and
 * changeable by an UPDATE rather than a deploy, exactly as `owner_role` and
 * `recommended_action` already are.
 *
 * BOTH COLUMNS ARE NULLABLE, WITH NO DEFAULT, AND THAT IS THE POINT.
 *
 * Most of the shipped rules detect a SYMPTOM whose cause is genuinely unknown —
 * a breached service target, a zone carrying more complaints than its share.
 * OperationalSignalRules already refuses to name a cause for the second in its
 * own code ("consistent with a network fault, but also with the zone simply
 * having more subscribers ... so it must not claim to"). A NOT NULL column, or
 * one with a default family, would force every such rule to assert a cause it
 * does not have, which is the fabrication this build exists to prevent. NULL
 * here means "this rule does not know why", and it must stay distinguishable
 * from any family a human has actually chosen.
 *
 * PLATFORM/TENANT PRECEDENCE IS INHERITED, NOT REIMPLEMENTED. Precedence on this
 * table is a read-time property of RuleEvaluator::rulesFor() — a row owned by the
 * tenant wins over the shared 'platform' row of the same rule_key, and the
 * existing UNIQUE (tenant_id, rule_key) is what keeps that unambiguous. Columns
 * added here are carried by whichever row wins, so a tenant may override the
 * family its industry ships with by inserting its own rule row, and needs no new
 * mechanism to do it.
 *
 * hypothesis_confidence is DECIMAL(6,4), matching hpbrain_hypotheses.confidence
 * and hpbrain_signal_rules.confidence. Bare NUMERIC becomes DECIMAL(10,0) on
 * MySQL and would round every value to an integer — 0.6 stored as 1 — the defect
 * 2026_08_04_000100_precision_on_decimal_columns was written to correct.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hpbrain_signal_rules')) {
            return;
        }

        if (! Schema::hasColumn('hpbrain_signal_rules', 'root_cause_family')) {
            Schema::table('hpbrain_signal_rules', function ($table) {
                // Validated against config('brain.root_cause_families') by
                // whatever writes it, not by an enum here: the taxonomy is
                // configuration, and pinning it into DDL would mean a migration
                // every time a family is added.
                $table->string('root_cause_family', 100)->nullable()->after('recommended_action');
            });
        }

        if (! Schema::hasColumn('hpbrain_signal_rules', 'hypothesis_confidence')) {
            Schema::table('hpbrain_signal_rules', function ($table) {
                // The confidence a hypothesis proposed FROM this rule should
                // carry — a property of how well the rule's predicate pins the
                // cause, not of any one firing. Separate from the existing
                // `confidence` column, which is the DETECTION confidence: how
                // sure we are the condition is real, which is a different
                // question from how sure we are we know why.
                $table->decimal('hypothesis_confidence', 6, 4)->nullable()->after('root_cause_family');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('hpbrain_signal_rules')) {
            return;
        }

        foreach (['hypothesis_confidence', 'root_cause_family'] as $column) {
            if (Schema::hasColumn('hpbrain_signal_rules', $column)) {
                Schema::table('hpbrain_signal_rules', fn ($table) => $table->dropColumn($column));
            }
        }
    }
};
