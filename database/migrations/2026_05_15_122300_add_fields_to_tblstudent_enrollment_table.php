<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tblstudent_enrollment', function (Blueprint $table) {

            // Preferred pedagogy
            if (!Schema::hasColumn('tblstudent_enrollment', 'preferred_pedagogy')) {
                $table->string('preferred_pedagogy')->nullable()->after('student_id');
            }

            // Engagement score
            if (!Schema::hasColumn('tblstudent_enrollment', 'engagement_score')) {
                $table->float('engagement_score')->nullable()->after('preferred_pedagogy');
            }

            // Learning velocity
            if (!Schema::hasColumn('tblstudent_enrollment', 'learning_velocity')) {
                $table->float('learning_velocity')->nullable()->after('engagement_score');
            }

            // Updated at
            if (!Schema::hasColumn('tblstudent_enrollment', 'updated_at')) {
                $table->dateTime('updated_at')->nullable()->after('learning_velocity');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tblstudent_enrollment', function (Blueprint $table) {

            if (Schema::hasColumn('tblstudent_enrollment', 'preferred_pedagogy')) {
                $table->dropColumn('preferred_pedagogy');
            }

            if (Schema::hasColumn('tblstudent_enrollment', 'engagement_score')) {
                $table->dropColumn('engagement_score');
            }

            if (Schema::hasColumn('tblstudent_enrollment', 'learning_velocity')) {
                $table->dropColumn('learning_velocity');
            }

            if (Schema::hasColumn('tblstudent_enrollment', 'updated_at')) {
                $table->dropColumn('updated_at');
            }
        });
    }
};