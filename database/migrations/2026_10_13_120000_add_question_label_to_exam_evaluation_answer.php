<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * exam_evaluation_answer.question_label -- the question number PRINTED on the answer sheet.
 *
 * `question_no` is the question's position in the paper (it keys the answer to the marking key
 * and must stay as it is). A sheet may number its questions differently -- Q.1(A) to Q.1(E),
 * then Q.2, Q.3, Q.4 for a paper whose eight questions sit at positions 1..8 -- and nothing in
 * the paper tables records that, so the reader reports what it sees and it is kept here.
 *
 * Nullable on purpose: null means "the sheet's number could not be read reliably", and the
 * review screen then says so instead of inventing one. Existing rows stay null until the sheet
 * is re-run.
 *
 * Guarded, so running it twice or against a database that already has it is safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('exam_evaluation_answer') && ! Schema::hasColumn('exam_evaluation_answer', 'question_label')) {
            Schema::table('exam_evaluation_answer', function (Blueprint $table) {
                $table->string('question_label', 40)->nullable()->after('question_no');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('exam_evaluation_answer') && Schema::hasColumn('exam_evaluation_answer', 'question_label')) {
            Schema::table('exam_evaluation_answer', function (Blueprint $table) {
                $table->dropColumn('question_label');
            });
        }
    }
};
