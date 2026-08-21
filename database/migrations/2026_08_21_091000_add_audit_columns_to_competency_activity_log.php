<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ported from G2G's
 * `2026_07_29_120000_add_audit_columns_to_competency_activity_log.php`.
 *
 * Additive columns on s_competency_activity_log:
 *  - subject_name -> the "Record Name" column/field on history views.
 *  - changes      -> the field-level before/after diff
 *                     (ResolvesCompetencyContext::diffChanges()) rendered as
 *                     a "Change Summary" wherever a controller's update()
 *                     logs one.
 *
 * Both columns are nullable with no default; every reader in this port
 * (CertificationController::history, DevelopmentPlanController::history)
 * selects explicit columns, so this is purely additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('s_competency_activity_log', function (Blueprint $table) {
            if (!Schema::hasColumn('s_competency_activity_log', 'subject_name')) {
                $table->string('subject_name', 191)->nullable()->after('subject_id');
            }
            if (!Schema::hasColumn('s_competency_activity_log', 'changes')) {
                // [{field, label, old, new}, ...]
                $table->json('changes')->nullable()->after('subject_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('s_competency_activity_log', function (Blueprint $table) {
            foreach (['subject_name', 'changes'] as $column) {
                if (Schema::hasColumn('s_competency_activity_log', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
