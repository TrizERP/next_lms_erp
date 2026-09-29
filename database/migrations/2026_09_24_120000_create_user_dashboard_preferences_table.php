<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user dashboard customisation for the lms_k12 Next.js dashboards: which
 * KPI cards / charts / panels ONE user has chosen to hide on ONE dashboard.
 * Written only by UserDashboardPreferenceApiController, which takes the owner
 * from the verified JWT (session()), so one user's choices can never change
 * what another user sees.
 *
 * Stores the HIDDEN widget ids, not the visible ones: a widget added to a
 * dashboard later shows up for everyone by default instead of being silently
 * missing for every user who ever saved a preference.
 *
 * Deliberately separate from the legacy `dynamic_dashboard` table, which is
 * one row per tblmenumaster.dashboard_menu box for the Blade dashboard and has
 * no notion of a dashboard key or of student logins.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_dashboard_preferences')) {
            return;
        }

        Schema::create('user_dashboard_preferences', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sub_institute_id');
            $table->unsignedBigInteger('user_id');

            // tbluser and tblstudent ids overlap, and only the JWT's
            // is_student claim tells them apart -- so it is part of the key.
            // staff | student
            $table->string('user_type', 10)->default('staff');

            // Which dashboard, e.g. "home.admin", "home.teacher". Chosen by
            // the frontend; one row per user per dashboard.
            $table->string('dashboard_key', 100);

            // JSON array of widget ids, e.g. ["kpi.total_staff","chart.fee_collection"].
            $table->json('hidden_widgets')->nullable();

            $table->timestamps();

            $table->unique(
                ['sub_institute_id', 'user_id', 'user_type', 'dashboard_key'],
                'user_dashboard_preferences_owner_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_dashboard_preferences');
    }
};
