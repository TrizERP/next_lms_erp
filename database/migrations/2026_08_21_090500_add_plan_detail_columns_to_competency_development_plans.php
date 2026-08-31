<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ported from G2G's
 * `2026_07_29_100200_add_plan_detail_columns_to_competency_development_plans.php`.
 *
 * Plan-detail columns for the workspace's "Plan Overview" tab: an Objective
 * paragraph, a Key Focus Areas list and a Linked Career Path.
 *
 * All three are nullable with no default change, so the plain
 * DevelopmentPlanController@index/store/destroy paths keep working unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('s_competency_development_plans', function (Blueprint $table) {
            if (!Schema::hasColumn('s_competency_development_plans', 'objective')) {
                $table->text('objective')->nullable()->after('title');
            }
            if (!Schema::hasColumn('s_competency_development_plans', 'focus_areas')) {
                // Comma-separated competency/theme labels, same style as
                // s_user_jobrole.related_jobrole.
                $table->text('focus_areas')->nullable()->after('objective');
            }
            if (!Schema::hasColumn('s_competency_development_plans', 'career_path_id')) {
                $table->unsignedBigInteger('career_path_id')->nullable()->index()->after('framework_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('s_competency_development_plans', function (Blueprint $table) {
            foreach (['objective', 'focus_areas', 'career_path_id'] as $column) {
                if (Schema::hasColumn('s_competency_development_plans', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
