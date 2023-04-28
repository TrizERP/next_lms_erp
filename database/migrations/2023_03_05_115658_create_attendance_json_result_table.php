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
        Schema::create('attendance_json_result', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('syear')->nullable();
            $table->integer('sub_institute_id')->nullable();
            $table->longText('json_data')->nullable();
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
        Schema::dropIfExists('attendance_json_result');
    }
};
