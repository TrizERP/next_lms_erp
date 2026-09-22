<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Homework assigned from an existing homework question paper records which
 * paper it came from.
 *
 * The questions themselves already live in `homework.question_ids` (added with
 * `source_type` for the question-bank flow), so this column is provenance, not
 * content: it is what lets a homework row be traced back to the
 * `question_paper` row a teacher picked, and what lets the reference PDF be
 * regenerated from the paper if it is ever lost.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('homework', function (Blueprint $table) {
            $table->unsignedBigInteger('exam_paper_id')->nullable()->after('question_ids');
        });
    }

    public function down()
    {
        Schema::table('homework', function (Blueprint $table) {
            $table->dropColumn('exam_paper_id');
        });
    }
};
