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
        Schema::create('complaint', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('ID');
            $table->integer('SUB_INSTITUTE_ID')->index('SUB_INSTITUTE_ID');
            $table->dateTime('DATE')->nullable();
            $table->string('TITLE', 150)->nullable();
            $table->mediumText('DESCRIPTION')->nullable();
            $table->string('ATTACHEMENT', 150)->nullable();
            $table->string('FILE_SIZE')->nullable();
            $table->text('FILE_TYPE')->nullable();
            $table->string('COMPLAINT_BY', 150)->nullable();
            $table->mediumText('COMPLAINT_SOLUTION')->nullable();
            $table->string('COMPLAINT_SOLUTION_BY', 50)->nullable();
            $table->string('COMPLAINT_SOLUTION_USER_GROUP_ID', 50)->nullable();
            $table->timestamp('CREATED_DATE')->nullable()->useCurrent();
            $table->string('CREATED_IP', 50)->nullable();
            $table->dateTime('UPDATED_ON')->nullable();
            $table->integer('SYEAR')->nullable();
            $table->integer('MARKING_PERIOD_ID')->nullable();

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
        Schema::dropIfExists('complaint');
    }
};
