<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Simulation Learning in Prayogshala: the link between a simulation and the curriculum concepts it helps teach.
 *
 * A link is anchored on `concept_id` (a concept belongs to exactly one chapter and one topic, so one simulation linked to several concepts can span
 * topics and chapters). `standard_id`, `subject_id`, `chapter_id` and `topic_id` are COPIES of what lms_concept says, written by the SERVER on every
 * link write and never taken from a client; the same denormalisation lms_prayogshala_activity uses, so a list needs no join, and
 * `lms:simulations-reconcile` checks the copies against lms_concept. `topic_id` is NULL when the concept has no topic.
 *
 * A link starts in `review` and a learner sees nothing through it until a teacher publishes it (`review_status` = `published`). The pair
 * (simulation_id, concept_id) is unique; links are hard-deleted (no soft delete).
 *
 * No foreign keys and no JSON column, like the rest of this schema. Idempotent: an existing table is left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        if ($this->exists('lms_simulation_concept')) {
            return;
        }

        Schema::create('lms_simulation_concept', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('simulation_id');
            $table->unsignedBigInteger('concept_id');
            $table->unsignedBigInteger('standard_id');                       // copied from lms_concept by the server
            $table->unsignedBigInteger('subject_id');                        // copied from lms_concept by the server
            $table->unsignedBigInteger('chapter_id');                        // copied from lms_concept by the server
            $table->unsignedBigInteger('topic_id')->nullable();              // copied from lms_concept by the server; NULL = the concept has no topic
            $table->string('role', 12)->default('primary');                  // primary | supporting
            $table->string('citation', 255)->nullable();                     // the textbook section this simulation illustrates
            $table->string('reason', 500)->nullable();                       // why it is linked, for the reviewing teacher
            $table->string('review_status', 16)->default('review');          // review | published
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->integer('sort_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['simulation_id', 'concept_id'], 'lmssimcon_sim_concept');
            $table->index('concept_id', 'lmssimcon_concept');
            $table->index('chapter_id', 'lmssimcon_chapter');
            $table->index('topic_id', 'lmssimcon_topic');
            $table->index('simulation_id', 'lmssimcon_sim');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_simulation_concept');
    }

    private function exists(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable) {
            return DB::select(
                'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1',
                [$table]
            ) !== [];
        }
    }
};
