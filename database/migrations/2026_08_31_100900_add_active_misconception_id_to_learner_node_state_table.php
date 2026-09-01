<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adaptive Learning Engine — D3.
 *
 * When a node's status is misconception_flagged, the resolver needs to know
 * WHICH misconception is active to re-serve the same contrast pair on
 * re-entry (e.g. the student navigated away mid-correction) without parsing
 * eso_decision_log JSON to find out. Nulled the moment a clean retest clears
 * the flag.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learner_node_state', function (Blueprint $table) {
            if (! Schema::hasColumn('learner_node_state', 'active_misconception_id')) {
                $table->unsignedBigInteger('active_misconception_id')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('learner_node_state', function (Blueprint $table) {
            if (Schema::hasColumn('learner_node_state', 'active_misconception_id')) {
                $table->dropColumn('active_misconception_id');
            }
        });
    }
};
