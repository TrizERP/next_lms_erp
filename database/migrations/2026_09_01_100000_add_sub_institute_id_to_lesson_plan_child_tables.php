<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Denormalise sub_institute_id onto the two lesson-plan child tables.
 *
 * Tenancy on lms_lesson_plan_periods and lms_lesson_plan_concepts has so far
 * been transitive: a period is owned by whichever institute owns its parent
 * lms_intelligence_lesson_plans row, and a concept by whichever institute owns
 * its parent period. That is correct but fragile - every read has to remember
 * to join two or three tables up the chain to stay inside its own tenant, and
 * an endpoint addressed by period id (or plan id) has nothing to filter on at
 * all, so forgetting the join silently exposes another institute's data.
 *
 * Carrying the owning institute on the row itself makes the filter local and
 * cheap, and lets every query fail closed.
 *
 * The column is backfilled from the existing parent chain, which is complete:
 * both foreign keys are NOT NULL and enforced, so no row can be orphaned.
 * It is left nullable rather than NOT NULL on purpose - a NULL is invisible to
 * an institute-scoped read, which is the safe direction to fail if some future
 * writer ever forgets to set it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lms_lesson_plan_periods')
            && !Schema::hasColumn('lms_lesson_plan_periods', 'sub_institute_id')) {
            Schema::table('lms_lesson_plan_periods', function (Blueprint $table) {
                // Matches lms_intelligence_lesson_plans.sub_institute_id (integer).
                $table->integer('sub_institute_id')->nullable()->after('lms_intelligence_lesson_plans_id');
                $table->index(['sub_institute_id', 'scheduled_date'], 'idx_period_institute_date');
            });

            // Backfill from the owning plan.
            DB::statement('
                UPDATE lms_lesson_plan_periods AS pp
                JOIN lms_intelligence_lesson_plans AS lp
                  ON lp.id = pp.lms_intelligence_lesson_plans_id
                SET pp.sub_institute_id = lp.sub_institute_id
                WHERE pp.sub_institute_id IS NULL
            ');
        }

        if (Schema::hasTable('lms_lesson_plan_concepts')
            && !Schema::hasColumn('lms_lesson_plan_concepts', 'sub_institute_id')) {
            Schema::table('lms_lesson_plan_concepts', function (Blueprint $table) {
                $table->integer('sub_institute_id')->nullable()->after('lms_lesson_plan_periods_id');
                $table->index(['sub_institute_id', 'concept_id'], 'idx_concept_institute');
            });

            // Backfill from the owning period, which the step above has just
            // stamped - so this inherits the same values without re-walking to
            // the plan.
            DB::statement('
                UPDATE lms_lesson_plan_concepts AS pc
                JOIN lms_lesson_plan_periods AS pp
                  ON pp.id = pc.lms_lesson_plan_periods_id
                SET pc.sub_institute_id = pp.sub_institute_id
                WHERE pc.sub_institute_id IS NULL
            ');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('lms_lesson_plan_concepts')
            && Schema::hasColumn('lms_lesson_plan_concepts', 'sub_institute_id')) {
            Schema::table('lms_lesson_plan_concepts', function (Blueprint $table) {
                $table->dropIndex('idx_concept_institute');
                $table->dropColumn('sub_institute_id');
            });
        }

        if (Schema::hasTable('lms_lesson_plan_periods')
            && Schema::hasColumn('lms_lesson_plan_periods', 'sub_institute_id')) {
            Schema::table('lms_lesson_plan_periods', function (Blueprint $table) {
                $table->dropIndex('idx_period_institute_date');
                $table->dropColumn('sub_institute_id');
            });
        }
    }
};
