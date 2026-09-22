<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which concept must be learned before which, for classes 6-10.
 *
 * ONE TABLE, BOTH DIRECTIONS
 * A post-requisite is the reverse of a prerequisite, not a separate fact. Storing
 * both directions guarantees that one day they disagree and nobody can say which is
 * right, so only one direction is stored:
 *
 *   prerequisites of X   ->  WHERE concept_id = X        (pc_prereq_idx)
 *   post-requisites of X ->  WHERE prerequisite_id = X   (pc_unlocks_idx)
 *   the whole chain      ->  WITH RECURSIVE over this table
 *
 * Many-to-many falls out for free: a concept with four prerequisites is four rows
 * sharing a concept_id, and a concept that unlocks three others is three rows
 * sharing a prerequisite_id.
 *
 * DIRECTION IS IN THE COLUMN NAMES
 * `concept_id` is the LATER concept; `prerequisite_id` is the EARLIER one. Read a
 * row as "concept_id requires prerequisite_id". Get this backwards and nothing
 * errors - the curriculum is simply taught in reverse - which is why the columns say
 * which end is which rather than using from/to.
 *
 * WHY BOTH GRADES ARE ON THE ROW
 * The cheapest correctness check there is: a prerequisite must never be taught in a
 * later class than the concept that needs it. Denormalised here so that check is one
 * WHERE clause over the whole table instead of two joins per edge. `lms_concept`
 * carries only `standard_id`, which is a per-tenant surrogate and not a class number.
 *
 * FOUR LINK TYPES, ALL DIRECTED
 *   requires      - a hard gate; the learner cannot start without it
 *   builds_on     - assumed, but a teacher can recover it inside the lesson
 *   spiral        - the same idea at an earlier class (the CBSE spiral)
 *   cross_subject - from another subject, almost always Mathematics into Science
 * All four point one way, so this table can never hold a symmetric pair and can
 * never acquire a two-row loop. `spiral` and `cross_subject` are separated from
 * `requires` because the REMEDIATION differs: a spiral gap sends the learner back to
 * the earlier version of the same topic, while a cross-subject gap means the learner
 * has no difficulty with this subject at all.
 *
 * `reason` IS REQUIRED
 * Not nullable. An edge whose justification is not written down cannot be reviewed
 * and cannot be argued with, which is the whole difference between a curated map and
 * a generated one.
 *
 * SCOPE
 * Self-contained. Nothing here reads, writes or replaces any existing prerequisite
 * store; this table stands alone and the services that read the old ones are
 * untouched. Rows seed at sub_institute_id = 0 / scope shared, because CBSE
 * prerequisites are board curriculum and every sub-institute inherits the same map.
 *
 * ROLLBACK
 * down() is correct but is DOCUMENTATION, not a safety net: AppServiceProvider.php:77-90
 * registers a DB::listen that throws on any SQL containing "DROP TABLE". Forward-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('concept_prerequisite')) {
            return;
        }

        Schema::create('concept_prerequisite', function (Blueprint $table) {
            $table->bigIncrements('id');

            // ---- the edge, named by role -----------------------------------
            $table->unsignedBigInteger('concept_id')->comment('lms_concept.id - the LATER concept');
            $table->unsignedBigInteger('prerequisite_id')->comment('lms_concept.id - the EARLIER concept it needs');

            $table->string('link_type', 16)->default('requires')
                ->comment('requires|builds_on|spiral|cross_subject');

            // Whether the learner is BLOCKED, as opposed to merely helped. Distinct
            // from link_type: a `requires` edge may still be taught through rather
            // than gated on, and only the gate stops a student.
            $table->boolean('is_gate')->default(false);

            // ---- class numbers, denormalised for the order check -----------
            $table->unsignedTinyInteger('concept_grade')->nullable()->comment('6..10');
            $table->unsignedTinyInteger('prerequisite_grade')->nullable()->comment('6..10');

            // ---- why it exists. Required. ----------------------------------
            $table->string('reason', 500)
                ->comment('one sentence: what the learner cannot do without the prerequisite');
            $table->string('source_ref', 191)
                ->comment('chapter and book edition the judgement was read from');

            $table->string('origin', 16)->default('expert')
                ->comment('expert|expert_reviewed_ai');
            $table->string('status', 16)->default('draft')
                ->comment('draft|approved');

            // ---- tenancy ----------------------------------------------------
            // Never a FK: lms_concept is a legacy table whose ids are also referenced
            // by soft-deleted rows, and a hard constraint would reject valid history.
            // 0 means shared across every sub-institute.
            $table->unsignedBigInteger('sub_institute_id')->default(0);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            // One edge per pair per tenant. The importer upserts on this, so
            // re-running after a correction updates in place rather than duplicating.
            $table->unique(['concept_id', 'prerequisite_id', 'sub_institute_id'], 'cp_edge_unique');

            // "What does this concept NEED?" - walks against the arrow.
            $table->index(['concept_id', 'link_type'], 'cp_prereq_idx');

            // "What does this concept UNLOCK?" - walks along the arrow. THIS is the
            // post-requisite index; there is no second table behind it.
            $table->index(['prerequisite_id', 'link_type'], 'cp_unlocks_idx');

            // The grade-order check, over the whole table in one scan.
            $table->index(['prerequisite_grade', 'concept_grade'], 'cp_grade_idx');
        });
    }

    public function down(): void
    {
        // Will throw under the DB::listen guard in AppServiceProvider. See the docblock.
        Schema::dropIfExists('concept_prerequisite');
    }
};
