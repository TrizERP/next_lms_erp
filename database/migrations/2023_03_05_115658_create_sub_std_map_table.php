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
        Schema::create('sub_std_map', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->unsignedInteger('subject_id')->index('sub_std_map_subject_id_foreign');
            $table->unsignedInteger('standard_id')->index('sub_std_map_standard_id_foreign');
            $table->string('allow_grades', 255)->nullable();
            $table->string('elective_subject', 255)->nullable();
            $table->string('display_name', 255);
            $table->string('add_content', 255);
            $table->string('allow_content', 255)->nullable();
            $table->string('subject_category', 255)->nullable();
            $table->string('display_image', 255)->nullable();
            $table->integer('sort_order')->nullable();
            $table->integer('sub_institute_id')->nullable();
            $table->integer('status')->nullable();
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
        Schema::dropIfExists('sub_std_map');
    }
};
