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
        Schema::create('task', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('ID');
            $table->string('TASK_TITLE', 150)->nullable();
            $table->mediumText('TASK_DESCRIPTION')->nullable();
            $table->string('TASK_ATTACHMENT', 150)->nullable();
            $table->text('FILE_SIZE')->nullable();
            $table->text('FILE_TYPE')->nullable();
            $table->dateTime('TASK_DATE')->nullable();
            $table->string('STATUS', 50)->nullable()->default('PENDING');
            $table->integer('TASK_ALLOCATED')->nullable();
            $table->integer('TASK_ALLOCATED_TO')->nullable();
            $table->timestamp('CREATED_ON')->nullable()->useCurrent();
            $table->integer('CREATED_BY')->nullable();
            $table->string('CREATED_IP_ADDRESS', 150)->nullable();
            $table->integer('SYEAR')->nullable();
            $table->integer('MARKING_PERIOD_ID')->nullable();
            $table->integer('sub_institute_id')->nullable();
            $table->integer('approved_by')->nullable();
            $table->dateTime('approved_on')->nullable();

            $table->index(['ID'], 'ID');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('task');
    }
};
