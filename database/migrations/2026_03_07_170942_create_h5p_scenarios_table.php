<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('h5p_scenarios', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('standard_id')->index()->nullable();
            $table->bigInteger('subject_id')->index()->nullable();
            $table->bigInteger('chapter_id')->index()->nullable();

            $table->string('title');
            $table->text('description')->nullable();

            $table->string('file_path');

            $table->bigInteger('sub_institute_id')->index()->nullable();
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->bigInteger('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('h5p_scenarios');
    }
};
