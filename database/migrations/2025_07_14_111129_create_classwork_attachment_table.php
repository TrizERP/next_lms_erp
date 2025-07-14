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
        Schema::create('classwork_attachment', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('student_id')->index()->nullable();
            $table->string('title')->index()->nullable();
            $table->mediumText('file_path')->nullable();
            $table->bigInteger('sub_institute_id')->index()->nullable();
            $table->bigInteger('syear')->index()->nullable();
            $table->bigInteger('created_by')->index()->nullable();
            $table->bigInteger('updated_by')->index()->nullable();
            $table->bigInteger('deleted_by')->index()->nullable();
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
        Schema::dropIfExists('classwork_attachment');
    }
};
