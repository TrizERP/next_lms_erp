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
        Schema::create('counselling_question_mapping', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('questionmaster_id')->default(0);
            $table->integer('mapping_type_id')->default(0);
            $table->integer('mapping_value_id')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('counselling_question_mapping');
    }
};
