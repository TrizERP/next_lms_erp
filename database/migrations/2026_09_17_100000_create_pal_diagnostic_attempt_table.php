<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One sitting of the PAL chapter diagnostic.
 *
 * ---------------------------------------------------------------------------
 * WHY THIS MIGRATION IS A NO-OP ON THE LIVE ESTATE
 * ---------------------------------------------------------------------------
 * This table already exists on the production database and already holds
 * learner evidence. It was created out of band, without a migration, so the
 * repo could not build the schema on a fresh install or in the test suite.
 *
 * This file closes that gap and nothing else. The guard below means it does
 * nothing at all where the table is already present, so it cannot disturb the
 * rows that are there.
 *
 * The shape below MIRRORS THE LIVE DDL exactly, down to the column widths
 * (status varchar(16), level varchar(24), percentage decimal(5,2)) and the
 * index names. A migration that produced a different shape than production
 * would be worse than no migration at all, because CI would then be testing a
 * schema that exists nowhere.
 *
 * On MariaDB $table->json() emits `longtext ... CHECK (json_valid(...))`,
 * which is precisely what production carries - so json() here is not a
 * divergence from the live longtext columns, it is the same thing.
 *
 * Additive only: no existing table is altered.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pal_diagnostic_attempt')) {
            return;
        }

        Schema::create('pal_diagnostic_attempt', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('chapter_id')->nullable()
                ->comment('chapter_master.id - the chapter this diagnostic was drawn from');
            $table->unsignedBigInteger('standard_id')->nullable();
            $table->unsignedBigInteger('sub_institute_id')->default(0);
            $table->unsignedInteger('syear')->nullable();

            $table->string('status', 16)->default('in_progress');

            $table->unsignedSmallInteger('total_questions')->default(0);
            $table->unsignedSmallInteger('correct')->default(0);
            $table->unsignedSmallInteger('incorrect')->default(0);
            $table->unsignedSmallInteger('unanswered')->default(0);
            $table->decimal('percentage', 5, 2)->default(0);
            $table->string('level', 24)->nullable();

            // Per-band and per-concept scoring, written once at submit.
            $table->json('difficulty_breakdown')->nullable();
            $table->json('concept_breakdown')->nullable();

            // How the 15 questions were chosen: the seed, the ladder stage
            // each band was filled from, and what had to be borrowed. This is
            // the only record of why a given learner got a given paper.
            $table->json('selection_report')->nullable();

            $table->timestamp('started_at')->nullable()->useCurrent();
            $table->timestamp('submitted_at')->nullable();

            $table->index(['student_id', 'subject_id'], 'pda_student_subject_idx');
            $table->index(['student_id', 'status'], 'pda_student_status_idx');
            $table->index(['student_id', 'chapter_id'], 'pda_student_chapter_idx');
        });
    }

    /**
     * Deliberately a no-op, NOT dropIfExists.
     *
     * This table was not born in a migration - it already existed, with
     * learner evidence in it, before this file was written. A rollback that
     * dropped it would destroy data this migration never created and cannot
     * restore. Rolling back to "before this migration" correctly means
     * leaving the table exactly as it was found.
     */
    public function down(): void
    {
        // Intentionally empty. See the docblock above.
    }
};
