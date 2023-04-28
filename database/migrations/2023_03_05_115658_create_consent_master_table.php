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
        Schema::create('consent_master', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('ID');
            $table->integer('student_id')->nullable();
            $table->integer('syear')->nullable();
            $table->integer('standard_id')->nullable();
            $table->integer('sub_institute_id')->nullable();
            $table->integer('division_id')->nullable();
            $table->string('title', 50)->nullable();
            $table->date('date')->nullable();
            $table->string('accountable_status', 50)->nullable();
            $table->integer('amount')->nullable();
            $table->integer('imprest_head_id')->nullable();
            $table->string('status', 50)->nullable();
            $table->timestamp('created_on')->nullable()->useCurrent();
            $table->string('created_by', 50)->nullable();
            $table->string('created_ip', 150)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('consent_master');
    }
};
