<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The contextual columns LearnerStateEngine already reads.
 *
 * LearnerStateEngine::calculateBandwidthQuality() filters sessions on
 * `load_time_ms` and getTimeOfDayPattern() averages `performance_score`, but
 * neither column existed on pal_learning_sessions. Eloquent silently matches
 * nothing on a missing attribute, so the bandwidth check counted zero slow
 * sessions and would have reported "good" bandwidth for every learner the
 * moment session capture started producing rows -- a wrong reading rather than
 * an absent one.
 *
 * NOTE ON STATE: recorded as applied on vivek_erp (batch 365) while its file
 * was missing from the repo. Guarded with hasColumn so it is a no-op there and
 * correct on a fresh environment.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pal_learning_sessions')) {
            return;
        }

        Schema::table('pal_learning_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('pal_learning_sessions', 'device_type')) {
                $table->string('device_type')->nullable()->after('mastery_score');
            }

            // Nullable with no default on purpose: 0 would read as an
            // instantaneous load, and the engine's slow-session ratio would
            // then treat every untracked session as fast.
            if (!Schema::hasColumn('pal_learning_sessions', 'load_time_ms')) {
                $table->unsignedInteger('load_time_ms')->nullable()->after('device_type');
            }

            if (!Schema::hasColumn('pal_learning_sessions', 'performance_score')) {
                $table->double('performance_score', 8, 2)->nullable()->after('load_time_ms');
            }

            if (!Schema::hasColumn('pal_learning_sessions', 'initiated_by')) {
                $table->string('initiated_by')->nullable()->after('performance_score');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('pal_learning_sessions')) {
            return;
        }

        Schema::table('pal_learning_sessions', function (Blueprint $table) {
            foreach (['initiated_by', 'performance_score', 'load_time_ms'] as $column) {
                if (Schema::hasColumn('pal_learning_sessions', $column)) {
                    $table->dropColumn($column);
                }
            }
            // device_type is intentionally left in place -- it predates this
            // migration on some environments.
        });
    }
};
