<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
         Schema::table('homework', function (Blueprint $table) {
            $table->string('source_type', 30)->nullable()->default('attachment');
            // TEXT (not VARCHAR(250)) deliberately -- question_paper.question_ids is
            // VARCHAR(250) and truncates around 40-60 comma-separated ids; homework
            // needs to hold a full question-bank selection without that cap.
            $table->text('question_ids')->nullable();
         });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
         Schema::table('homework', function (Blueprint $table) {
            $table->dropColumn(['source_type', 'question_ids']);
         });
    }
};
