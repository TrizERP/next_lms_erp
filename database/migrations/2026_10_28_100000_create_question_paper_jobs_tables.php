<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persistent state for Question Paper uploads from the Document Library.
 *
 * question_paper_jobs  one row per uploaded paper: lifecycle, stage, real counters, lease, retry state.
 * question_paper_items one row per question found in the paper: the provenance link between a source
 *                      page/number and the lms_question_master row it became. Its UNIQUE
 *                      (job_id, content_hash) is what makes a retry unable to insert a question twice.
 *
 * Nothing here alters lms_question_master or answer_master.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('question_paper_jobs')) {
            Schema::create('question_paper_jobs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->char('uuid', 36)->unique();
                $table->unsignedBigInteger('document_id')->index();
                $table->unsignedInteger('sub_institute_id');
                $table->unsignedBigInteger('user_id');

                // queued | processing | retrying | completed | completed_with_warnings | failed
                $table->string('status', 32)->default('queued');
                // validating | extracting_text | parsing_questions | resolving_metadata | saving | finalizing
                $table->string('stage', 32)->nullable();

                // Academic context. Supplied by the uploader or resolved by exact name match; never guessed.
                $table->unsignedInteger('standard_id')->nullable();
                $table->unsignedInteger('subject_id')->nullable();
                $table->unsignedBigInteger('chapter_id')->nullable();
                $table->string('academic_year', 20)->nullable();
                $table->text('metadata_notes')->nullable();

                $table->unsignedInteger('total_pages')->nullable();
                $table->unsignedInteger('processed_pages')->default(0);
                $table->unsignedInteger('total_chunks')->nullable();
                $table->unsignedInteger('parsed_chunks')->default(0);
                $table->unsignedInteger('questions_extracted')->default(0);
                $table->unsignedInteger('questions_saved')->default(0);
                $table->unsignedInteger('questions_needing_review')->default(0);
                $table->unsignedInteger('questions_skipped')->default(0);
                $table->boolean('used_ocr')->default(false);

                $table->unsignedSmallInteger('attempts')->default(0);
                // Owner-triggered re-runs; part of the notification dedupe key so a re-run can notify again.
                $table->unsignedSmallInteger('manual_retries')->default(0);
                $table->boolean('retryable')->default(true);
                $table->text('last_error')->nullable();
                $table->longText('error_log')->nullable();

                // Single-worker guard: a worker owns the job until lease_expires_at.
                $table->char('lease_token', 36)->nullable();
                $table->timestamp('lease_expires_at')->nullable();
                $table->timestamp('last_dispatched_at')->nullable();

                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();

                $table->index(['sub_institute_id', 'user_id', 'created_at'], 'idx_qpj_owner');
                $table->index(['status', 'lease_expires_at'], 'idx_qpj_recovery');
            });
        }

        if (!Schema::hasTable('question_paper_items')) {
            Schema::create('question_paper_items', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('job_id');
                $table->unsignedInteger('sub_institute_id');
                $table->unsignedInteger('ordinal');
                $table->string('item_number', 16)->nullable();
                $table->unsignedSmallInteger('source_page')->nullable();
                $table->string('format_code', 48)->nullable();
                $table->char('content_hash', 64);
                // parsed     found, not yet written
                // saved      written to the bank, active
                // needs_review written to the bank INACTIVE (status 0): chapter or answer key unresolved
                // unresolved not written: standard or subject unknown (waits for a retry with metadata)
                // duplicate  identical question already in the bank for this school/standard/subject
                // skipped    not a usable question (reason says why)
                $table->string('state', 16)->default('parsed');
                $table->string('reason', 255)->nullable();
                $table->longText('payload');
                $table->unsignedBigInteger('question_id')->nullable();
                $table->timestamps();

                $table->unique(['job_id', 'content_hash'], 'uq_qpi_job_hash');
                $table->index(['job_id', 'state'], 'idx_qpi_state');
                $table->index('question_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('question_paper_items');
        Schema::dropIfExists('question_paper_jobs');
    }
};
