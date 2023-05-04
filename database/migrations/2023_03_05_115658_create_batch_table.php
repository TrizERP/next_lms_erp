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
        Schema::create('batch', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->string('title', 255);
            $table->unsignedInteger('standard_id')->index('batch_standard_id_foreign');
            $table->unsignedInteger('division_id')->index('batch_division_id_foreign');
            $table->integer('sub_institute_id');
            $table->integer('syear')->nullable();
            $table->integer('rollover_id')->nullable();
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
        Schema::dropIfExists('batch');
    }
};
