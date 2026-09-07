<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adaptive Learning Engine — Developer Brief v1, D4.
 *
 * "advance on 2 consecutive correct; never fixed question counts" needs a
 * streak that a wrong answer resets — `attempts` alone can't tell guided
 * practice's "last 2 were both right" from "12 attempts, half wrong".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learner_node_state', function (Blueprint $table) {
            if (! Schema::hasColumn('learner_node_state', 'consecutive_correct')) {
                $table->unsignedInteger('consecutive_correct')->default(0)->after('attempts');
            }
            if (! Schema::hasColumn('learner_node_state', 'practice_mode')) {
                $table->string('practice_mode', 16)->default('guided')->after('consecutive_correct'); // guided|independent
            }
        });
    }

    public function down(): void
    {
        Schema::table('learner_node_state', function (Blueprint $table) {
            if (Schema::hasColumn('learner_node_state', 'practice_mode')) {
                $table->dropColumn('practice_mode');
            }
            if (Schema::hasColumn('learner_node_state', 'consecutive_correct')) {
                $table->dropColumn('consecutive_correct');
            }
        });
    }
};
