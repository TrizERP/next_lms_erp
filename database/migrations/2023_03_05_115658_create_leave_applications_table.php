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
        Schema::create('leave_applications', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('syear')->default(0);
            $table->integer('student_id')->default(0);
            $table->string('title', 250);
            $table->text('message');
            $table->text('files');
            $table->text('file_size');
            $table->text('file_type');
            $table->date('apply_date');
            $table->date('from_date');
            $table->date('to_date');
            $table->text('reply')->nullable();
            $table->dateTime('reply_on')->nullable();
            $table->integer('reply_by')->nullable();
            $table->text('status')->nullable();
            $table->bigInteger('sub_institute_id');
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
        Schema::dropIfExists('leave_applications');
    }
};
