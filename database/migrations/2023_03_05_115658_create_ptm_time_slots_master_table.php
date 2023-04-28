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
        Schema::create('ptm_time_slots_master', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->string('syear', 50)->nullable();
            $table->integer('sub_institute_id')->nullable();
            $table->date('ptm_date')->nullable();
            $table->integer('standard_id')->nullable();
            $table->integer('division_id')->nullable();
            $table->string('title', 150)->nullable();
            $table->time('from_time')->nullable();
            $table->time('to_time')->nullable();
            $table->integer('created_by')->nullable();
            $table->timestamp('created_on')->nullable()->useCurrent();
            $table->string('created_ip', 100)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ptm_time_slots_master');
    }
};
