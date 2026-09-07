<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI-generated capability assessment - three tables, for G2G-LMS Assessments.
 *
 * Ported EXACTLY from hp_erp's `2026_08_13_140000_create_ai_assessment_tables.php`
 * (`App\Http\Controllers\Api\Competency\AiAssessmentController`, the FIRST,
 * simpler shape of that migration - confirmed via
 * `git show 513899d2:...AiAssessmentController.php` in hp_erp - deliberately
 * NOT the later evolved shape (`2026_08_26_120000_assessment_scope_attempts_and_proposals.php`
 * adds `competency_assessment_attempt` / `competency_assessment_rating_proposal`
 * plus scope_type/citation columns): those two extra tables are NOT in this
 * package's approved table list, so this package ports the generate / mine /
 * submit loop against the original three-table shape only. See the final
 * report for exactly which AiAssessmentController endpoints this implies
 * are and are not implemented.
 *
 * `kasba_item_id` on the question table is NOT NULL on purpose - see the
 * source migration's own docblock ("no item, no question"): a question
 * cannot exist without a real, tenant-authored capability item behind it.
 * That item lives in this codebase's PRE-EXISTING `competency_kasba_item`
 * table (created by `2026_08_20_101000_create_competency_task_map_tables.php`,
 * extended with `kasba_type`/`item_label` by
 * `2026_08_21_130000_add_kasba_rating_support_columns.php`) - confirmed
 * present before writing this migration, so `generate()`/`mine()`/`submit()`
 * can resolve real items without this package creating any competency
 * catalogue table itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── the test: one per job role, generated once, taken by many ──────
        if (!Schema::hasTable('competency_assessment_test')) {
            Schema::create('competency_assessment_test', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sub_institute_id');
                $table->unsignedBigInteger('jobrole_id');
                $table->string('title', 191);
                $table->text('instructions')->nullable();
                // WHICH MODEL PRODUCED THIS.
                $table->string('model', 100)->nullable();
                $table->string('status', 30)->default('draft');
                $table->unsignedBigInteger('generated_by');
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['sub_institute_id', 'jobrole_id'], 'cat_tenant_jobrole_index');
                $table->index(['sub_institute_id', 'status'], 'cat_tenant_status_index');
            });
        }

        // ── the questions ────────────────────────────────────────────────
        if (!Schema::hasTable('competency_assessment_question')) {
            Schema::create('competency_assessment_question', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sub_institute_id');
                $table->unsignedBigInteger('test_id');
                // NOT NULL ON PURPOSE. See class docblock: no item, no question.
                $table->unsignedBigInteger('kasba_item_id');
                $table->string('format', 30);             // mcq | short_answer
                $table->text('question_text');
                $table->json('options')->nullable();       // MCQ choices; null for short_answer
                $table->string('correct_option', 50)->nullable();
                $table->text('model_answer')->nullable();  // short_answer reference
                $table->unsignedSmallInteger('max_score')->default(1);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['test_id', 'sort_order'], 'caq_test_order_index');
                $table->index('kasba_item_id', 'caq_item_index');
                $table->index('sub_institute_id', 'caq_tenant_index');
            });
        }

        // ── the answers ──────────────────────────────────────────────────
        if (!Schema::hasTable('competency_assessment_response')) {
            Schema::create('competency_assessment_response', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sub_institute_id');
                $table->unsignedBigInteger('test_id');
                $table->unsignedBigInteger('question_id');
                $table->unsignedBigInteger('user_id');
                $table->text('answer_text')->nullable();
                $table->string('selected_option', 50)->nullable();
                // NULLABLE ON PURPOSE. Unanswered is not zero.
                $table->decimal('score', 5, 2)->nullable();
                $table->string('scored_by', 30)->nullable();   // auto | manual
                $table->timestamp('answered_at')->nullable();
                $table->timestamps();

                $table->unique(['question_id', 'user_id'], 'car_question_user_unique');
                $table->index(['sub_institute_id', 'user_id'], 'car_tenant_user_index');
                $table->index(['test_id', 'user_id'], 'car_test_user_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('competency_assessment_response');
        Schema::dropIfExists('competency_assessment_question');
        Schema::dropIfExists('competency_assessment_test');
    }
};
