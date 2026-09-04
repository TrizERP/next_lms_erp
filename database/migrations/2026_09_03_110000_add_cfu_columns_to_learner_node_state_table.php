<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adaptive Learning Engine — Check For Understanding (CFU) phase markers.
 *
 * Until now the engine decided "teach vs practice" from `attempts === 0`
 * alone, which conflated three genuinely different situations: never taught,
 * taught but not yet checked, and checked and now practising. Worse, it was
 * not even reachable in the normal path: scoreDiagnostic() runs applyUpdate(),
 * so every node touched by a diagnostic already has attempts >= 1 and skipped
 * straight to practice.
 *
 * These three columns make the phase explicit instead of inferred. They are
 * deliberately NOT part of any D1-D5 threshold: `cfu_passed_at` gates which
 * screen is served, never whether the student has mastered anything.
 * mastery_estimate / attempts / consecutive_correct remain the only mastery
 * evidence, exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learner_node_state', function (Blueprint $table) {
            // Stamped when the teach card is actually served to the student.
            $table->timestamp('taught_at')->nullable()->after('status');
            // Stamped when a CFU cycle is answered fully correctly.
            $table->timestamp('cfu_passed_at')->nullable()->after('taught_at');
            // How many CFU cycles have been attempted on this node. Used only
            // by the CFU_MAX_CYCLES safety valve, never by a mastery rule.
            $table->unsignedInteger('cfu_attempts')->default(0)->after('cfu_passed_at');
        });

        // Grandfather learners already mid-flight. Without this, every student
        // with existing practice history would be thrown back to a teach card
        // and a CFU on their next call — a regression dressed up as a feature.
        // Any node with recorded attempts has demonstrably been taught and
        // practised under the old flow, so it enters the new machine at its
        // final phase.
        DB::table('learner_node_state')
            ->where('attempts', '>', 0)
            ->update([
                'taught_at' => DB::raw('COALESCE(last_seen_at, NOW())'),
                'cfu_passed_at' => DB::raw('COALESCE(last_seen_at, NOW())'),
            ]);
    }

    public function down(): void
    {
        Schema::table('learner_node_state', function (Blueprint $table) {
            $table->dropColumn(['taught_at', 'cfu_passed_at', 'cfu_attempts']);
        });
    }
};
