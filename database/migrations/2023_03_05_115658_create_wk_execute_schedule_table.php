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
        Schema::create('wk_execute_schedule', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('main_id')->nullable();
            $table->string('run_workflow', 250)->nullable();
            $table->date('at_date')->nullable();
            $table->time('at_time')->nullable();
            $table->string('week_days', 250)->nullable();
            $table->string('month_days', 250)->nullable();
            $table->string('status', 50)->nullable();
            $table->integer('sub_institute_id')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->integer('created_by')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('wk_execute_schedule');
    }
};
