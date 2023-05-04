<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('result_master_confrigration', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('syear')->default(0);
            $table->integer('term_id')->default(0);
            $table->integer('sub_institute_id')->default(0);
            $table->integer('standard_id')->default(0);
            $table->date('result_date');
            $table->date('reopen_date');
            $table->date('vaction_start_date');
            $table->date('vaction_end_date');
            $table->string('teacher_sign', 255);
            $table->string('principal_sign', 255);
            $table->string('director_signatiure', 255);
            $table->string('result_remark', 255);
            $table->string('optional_subject_display', 255);
            $table->string('remove_fail_per', 255);
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->dateTime('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('result_master_confrigration');
    }
};
