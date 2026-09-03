<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_08_04_000100_precision_on_decimal_columns.php
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Give every fractional column a scale. Twenty-seven of them had none.
 *
 * THIS IS THE BUG THAT MADE THE PRODUCT LOOK BROKEN.
 *
 * An unqualified NUMERIC/DECIMAL in MySQL and MariaDB means DECIMAL(10,0) —
 * ten digits, ZERO of them after the point. Every value written to such a
 * column is rounded to a whole number on the way in. Measured against the live
 * database before this migration:
 *
 *     INSERT 0.85, 0.30, 0.49, 0.78  ->  SELECT 1, 0, 0, 1
 *
 * Every confidence score in the Brain was one of those columns. So were the
 * five KASBA levels, evidence confidence, risk probability, and reasoning step
 * confidence. Confidence is not decoration here — it is the number that decides
 * whether a claim may be shown at all, which band it renders in, and whether a
 * policy gate opens. Reduced to a coin flip, every screen that displays it is
 * either overstating certainty (0.51 -> 1.00) or destroying a real finding
 * (0.49 -> 0.00), with no way to tell which from the stored value.
 *
 * It also silently defeats the confidence ceiling: a system designed never to
 * claim more than 0.95 certainty was storing 1.
 *
 * WHY IT SURVIVED. These tables predate this repository's migrations — they
 * come from the older schema tracked in hpbrain_schema_migrations. The test
 * suite builds its own schema on SQLite, where DECIMAL is advisory and 0.85
 * round-trips as 0.85 regardless of the declared scale. Nothing in the suite
 * could see this, and nothing in it can regress it either; the guard is the
 * assertion in PrecisionTest, which checks the declared scale rather than a
 * round-trip.
 *
 * ALREADY-WRITTEN DATA CANNOT BE RECOVERED. A stored 1 might have been 0.51 or
 * 0.99. This migration widens the column so future writes are faithful; it does
 * not invent history. Affected rows at the time of writing: one signal and two
 * hypotheses, all reading exactly 1. Those want recomputing from their evidence,
 * not back-filling with a guess.
 *
 * The scale chosen per column follows what the value MEANS, not one blanket
 * type — see GROUPS below.
 */
return new class extends Migration
{
    /**
     * column semantics => [precision, scale, [table.column, ...]]
     *
     * Probabilities get four decimal places because that is what the rest of
     * this codebase standardised on (hpbrain_signal_rules.confidence,
     * hpbrain_metric_snapshots.confidence) and a confidence ceiling of 0.95
     * against a floor of 0.30 needs finer steps than two places to move at all
     * as evidence accumulates.
     *
     * @var array<string, array{0:int,1:int,2:array<int,string>}>
     */
    private const GROUPS = [
        // 0.0000 .. 1.0000
        'probability' => [6, 4, [
            'hpbrain_capability_proficiency.evidence_confidence',
            'hpbrain_decisions.confidence',
            'hpbrain_evidence.confidence',
            'hpbrain_hypotheses.confidence',
            'hpbrain_knowledge_assets.confidence',
            'hpbrain_learnings.confidence',
            'hpbrain_mental_models.confidence',
            'hpbrain_outcomes.confidence',
            'hpbrain_reasoning_steps.confidence_score',
            'hpbrain_recommendations.confidence',
            'hpbrain_risks.probability',
            'hpbrain_signals.confidence',
        ]],

        // 0.00 .. 5.00 — KASBA levels and the requirement they are measured against.
        // Both sides of a gap calculation must carry the same scale or the
        // subtraction reintroduces the rounding this migration removes.
        'level' => [4, 2, [
            'hpbrain_capability_proficiency.knowledge_level',
            'hpbrain_capability_proficiency.ability_level',
            'hpbrain_capability_proficiency.skill_level',
            'hpbrain_capability_proficiency.behaviour_level',
            'hpbrain_capability_proficiency.attitude_level',
            'hpbrain_job_role_capability_requirements.required_level',
            'hpbrain_occupation_capability_requirements.required_level',
        ]],

        // Small bounded scalars that are not probabilities: a trust ladder and
        // a sampling temperature.
        'scalar' => [4, 2, [
            'hpbrain_executors.trust_level',
            'hpbrain_prompt_templates.default_temperature',
        ]],

        // probability x impact, so up to 25.00
        'score' => [6, 2, [
            'hpbrain_risks.score',
        ]],

        // Arbitrary measured quantities — rates, counts, ratios all share this
        // column, so it needs the widest sensible range.
        'measure' => [18, 4, [
            'hpbrain_metrics.metric_value',
        ]],

        // Currency. Two places, and enough digits for annual figures.
        'money' => [14, 2, [
            'hpbrain_placement_job_roles.min_salary',
            'hpbrain_placement_job_roles.max_salary',
            'hpbrain_recommendations.expected_roi',
        ]],

        // Per-call model spend is frequently a fraction of a cent, so two
        // decimal places would round almost every row to zero.
        'micro_money' => [14, 6, [
            'hpbrain_ai_executions.estimated_cost_usd',
        ]],
    ];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::GROUPS as $semantic => [$precision, $scale, $columns]) {
            foreach ($columns as $ref) {
                [$table, $column] = explode('.', $ref, 2);

                if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                    logger()->info('precision_skipped_absent', ['column' => $ref]);

                    continue;
                }

                $this->widen($table, $column, $precision, $scale, $semantic);
            }
        }
    }

    /**
     * Rebuild the column at the new precision, preserving everything else.
     *
     * Nullability and default are read back from information_schema rather than
     * assumed, because MODIFY COLUMN restates the WHOLE definition: any
     * attribute omitted here is silently dropped. Getting this wrong would turn
     * a nullable confidence into NOT NULL DEFAULT 0 — which is precisely the
     * null-is-not-zero confusion the rest of the system works to avoid.
     */
    private function widen(string $table, string $column, int $precision, int $scale, string $semantic): void
    {
        $meta = DB::selectOne(
            'SELECT is_nullable, column_default, column_type, extra
               FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            [$table, $column],
        );

        if ($meta === null) {
            return;
        }

        // Already has a scale — either this migration ran before, or the column
        // was declared correctly to begin with. Either way, leave it alone.
        if (! preg_match('/^decimal\((\d+),(\d+)\)/i', (string) $meta->column_type, $m) || (int) $m[2] !== 0) {
            return;
        }

        $sql = sprintf(
            'ALTER TABLE `%s` MODIFY `%s` DECIMAL(%d,%d) %s',
            $table,
            $column,
            $precision,
            $scale,
            $meta->is_nullable === 'YES' ? 'NULL' : 'NOT NULL',
        );

        // MariaDB reports the ABSENCE of a default on a nullable column as the
        // four-character string 'NULL', not as SQL NULL. Quoting that produces
        // DEFAULT 'NULL', which the server rejects as an invalid default for a
        // numeric column (error 1067). A column with no default needs no DEFAULT
        // clause at all.
        $default = $meta->column_default;
        $hasDefault = $default !== null && strcasecmp(trim((string) $default), 'NULL') !== 0;

        if ($hasDefault) {
            $sql .= ' DEFAULT '.(is_numeric($default)
                ? $default
                : "'".addslashes((string) $default)."'");
        }

        try {
            DB::unprepared($sql);
            logger()->info('precision_applied', [
                'column' => "{$table}.{$column}", 'semantic' => $semantic, 'to' => "DECIMAL({$precision},{$scale})",
            ]);
        } catch (\Throwable $e) {
            // Guarded per column so one unexpected definition cannot strand the
            // other twenty-six.
            logger()->warning('precision_failed', [
                'column' => "{$table}.{$column}", 'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Deliberately empty.
     *
     * Rolling back would mean narrowing these columns again, which destroys
     * every fractional value written since. There is no version of this worth
     * automating.
     */
    public function down(): void
    {
    }
};
