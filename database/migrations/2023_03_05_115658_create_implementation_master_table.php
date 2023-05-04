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
        Schema::create('implementation_master', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('sub_institute_id')->nullable();
            $table->string('total_male', 50)->nullable();
            $table->string('total_female', 50)->nullable();
            $table->string('syear', 50)->nullable();
            $table->string('total_boys', 50)->nullable();
            $table->string('total_girls', 50)->nullable();
            $table->string('total_strenght', 50)->nullable();
            $table->string('standard_id', 50)->nullable();
            $table->string('std_wise_total', 50)->nullable();
            $table->string('std_wise_total_boys', 50)->nullable();
            $table->string('std_wise_total_girls', 50)->nullable();
            $table->string('final_std_total_boys', 50)->nullable();
            $table->string('final_std_total_girls', 50)->nullable();
            $table->string('final_std_total', 50)->nullable();
            $table->timestamp('created_on')->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('implementation_master');
    }
};
