<?php

/*
 * Year-scope the Brain intelligence tables.
 *
 * WHY THIS EXISTS. Every hpbrain_* intelligence table was created with a tenant
 * and no academic year, while every LMS fact the pipeline reasons over
 * (fees_collect.syear, fees_breackoff.syear, attendance_student.syear …) is
 * year-stamped. The consequence was not cosmetic: SignalWriter deduplicates on
 * (tenant_id, rule_key) and REFRESHES the open signal rather than raising a
 * second one, so running the pipeline for 2020 after running it for 2021
 * overwrote the 2021 figures in place. One row cannot honestly hold two years
 * of a year-dependent measure, and a Fees Intelligence screen that persists its
 * findings cannot be built on top of that.
 *
 * WHAT IS AND IS NOT DONE HERE.
 *
 *   - `syear` is added NULLABLE to the ten tables that carry a year-dependent
 *     claim, plus an index on (tenant_id, syear) so the year-scoped reads this
 *     unlocks are not table scans.
 *
 *   - EXISTING ROWS ARE NOT BACKFILLED. They were written before any year was
 *     recorded, and nothing in the row, its evidence or the audit trail
 *     establishes which year the figures describe — guessing "the current year"
 *     would manufacture provenance rather than recover it. NULL is the accurate
 *     value: "this row predates year scoping". SignalWriter adopts such a row
 *     the first time its rule fires under a known year, and the adoption is
 *     truthful because the same run rewrites the row's figures from that year's
 *     data (see SignalWriter::openSignalFor).
 *
 *   - Decisions, executions and outcomes get the column too. A decision is
 *     provenance — "this was decided, on this evidence, for this year" — and a
 *     2021 decision surfacing on the 2022 screen is the same defect one step
 *     further down the loop.
 *
 * Reversible: down() drops only what up() added.
 */
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The intelligence loop, in order. Each row in each of these makes a claim
     * that is only true of one academic year.
     *
     * @var array<int, string>
     */
    private const TABLES = [
        'hpbrain_signals',
        'hpbrain_evidence',
        'hpbrain_cases',
        'hpbrain_hypotheses',
        'hpbrain_reasoning_steps',
        'hpbrain_recommendations',
        'hpbrain_decisions',
        'hpbrain_eso_executions',
        'hpbrain_outcomes',
        'hpbrain_learnings',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'syear')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                // Nullable, and deliberately so — see the note above. The type
                // matches academic_year.syear, which the LMS stores as an
                // integer year (2021), not a label ("2021-22").
                $blueprint->integer('syear')->nullable()->comment(
                    'Academic year (academic_year.syear) this row describes; NULL for rows written before year scoping.'
                );
                $blueprint->index(['tenant_id', 'syear'], $this->indexName($table));
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'syear')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropIndex($this->indexName($table));
                $blueprint->dropColumn('syear');
            });
        }
    }

    /** MySQL caps index names at 64 characters; the longest table here is well inside it. */
    private function indexName(string $table): string
    {
        return $table.'_tenant_syear_index';
    }
};
