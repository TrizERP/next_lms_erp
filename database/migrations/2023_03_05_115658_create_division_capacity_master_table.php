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
        Schema::create('division_capacity_master', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->string('syear', 50)->nullable();
            $table->integer('sub_institute_id')->nullable();
            $table->integer('grade_id')->nullable();
            $table->integer('standard_id')->nullable();
            $table->integer('division_id')->nullable();
            $table->string('capacity', 150)->nullable();
            $table->dateTime('created_on')->nullable();
            $table->integer('created_by')->nullable();
            $table->string('created_ip', 250)->nullable();
            $table->integer('updated_by')->nullable();
            $table->dateTime('updated_on')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('division_capacity_master');
    }
};
