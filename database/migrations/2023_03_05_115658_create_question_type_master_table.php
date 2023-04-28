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
        Schema::create('question_type_master', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->string('question_type', 250)->nullable();
            $table->integer('status')->nullable();
            $table->integer('sub_institute_id')->nullable();
            $table->integer('syear')->nullable();
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
        Schema::dropIfExists('question_type_master');
    }
};
