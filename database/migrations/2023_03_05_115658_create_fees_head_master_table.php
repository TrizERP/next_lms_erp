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
        Schema::create('fees_head_master', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->string('code', 50)->nullable();
            $table->string('head_title', 50)->nullable();
            $table->string('description', 50)->nullable();
            $table->string('mandatory', 50)->nullable()->default('0');
            $table->integer('syear')->nullable();
            $table->integer('sub_institute_id')->nullable();
            $table->integer('created_by')->nullable();
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
        Schema::dropIfExists('fees_head_master');
    }
};
