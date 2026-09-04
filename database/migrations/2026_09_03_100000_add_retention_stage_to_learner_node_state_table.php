<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D5 spaced retention becomes a multi-stage ladder rather than one check.
 *
 * Before this, `next_review_at` was always scheduled a flat
 * EsoPolicyService::RETRIEVAL_DELAY_DAYS (4 days) out, exactly once — a node
 * that passed its single retrieval check went to `retained` with
 * next_review_at = null and was never reviewed again. `retention_stage`
 * records how far up the ladder (Day 2 → Week 1 → Month 1 → Month 2 →
 * Month 6) a node has climbed, so each passed check can schedule the next,
 * longer interval, and a failed check can restart the ladder at 0.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learner_node_state', function (Blueprint $table) {
            $table->unsignedTinyInteger('retention_stage')->default(0)->after('next_review_at');
        });
    }

    public function down(): void
    {
        Schema::table('learner_node_state', function (Blueprint $table) {
            $table->dropColumn('retention_stage');
        });
    }
};
