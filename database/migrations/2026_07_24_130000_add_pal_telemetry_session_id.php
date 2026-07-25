<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a numeric `session_id` to `pal_telemetry_events`.
 *
 * The xAPI `context.registration` (a UUID) is kept in `context_id` (json), but
 * `getSessionSummary(int $sessionId)` needs to look events up by the numeric
 * `pal_learning_sessions.id`. A dedicated, indexed column makes that query work
 * without overloading the UUID column. Nullable and additive — safe/reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pal_telemetry_events', function (Blueprint $table) {
            if (! Schema::hasColumn('pal_telemetry_events', 'session_id')) {
                $table->unsignedBigInteger('session_id')->nullable()->after('actor_id');
                $table->index(['session_id']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('pal_telemetry_events', function (Blueprint $table) {
            if (Schema::hasColumn('pal_telemetry_events', 'session_id')) {
                $table->dropIndex(['session_id']);
                $table->dropColumn('session_id');
            }
        });
    }
};
