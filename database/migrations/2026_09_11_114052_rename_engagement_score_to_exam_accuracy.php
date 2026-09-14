<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracker #32: engagement_score currently holds exam accuracy (from pal:sync-learner-evidence).
     * Rename it to exam_accuracy, add new nullable engagement_score for future real engagement telemetry.
     */
    public function up(): void
    {
        if (!Schema::hasTable('pal_learning_sessions')) {
            return;
        }

        // Rename existing engagement_score to exam_accuracy
        Schema::table('pal_learning_sessions', function (Blueprint $table) {
            $table->renameColumn('engagement_score', 'exam_accuracy');
        });

        // Make exam_accuracy nullable (it had default 0, but NULL is more honest for sessions without exam data)
        Schema::table('pal_learning_sessions', function (Blueprint $table) {
            $table->float('exam_accuracy')->nullable()->change();
        });

        // Add new engagement_score column (null until real telemetry exists)
        Schema::table('pal_learning_sessions', function (Blueprint $table) {
            $table->float('engagement_score')->nullable()->after('exam_accuracy');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('pal_learning_sessions')) {
            return;
        }

        // Drop the new engagement_score column
        Schema::table('pal_learning_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('pal_learning_sessions', 'engagement_score')) {
                $table->dropColumn('engagement_score');
            }
        });

        // Rename exam_accuracy back to engagement_score
        Schema::table('pal_learning_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('pal_learning_sessions', 'exam_accuracy')) {
                $table->renameColumn('exam_accuracy', 'engagement_score');
                $table->float('engagement_score')->default(0)->change();
            }
        });
    }
};