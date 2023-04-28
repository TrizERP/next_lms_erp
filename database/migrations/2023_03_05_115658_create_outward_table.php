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
        Schema::create('outward', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('sub_institute_id')->nullable();
            $table->string('syear', 50)->nullable();
            $table->unsignedInteger('place_id')->index('outward_place_id_foreign');
            $table->unsignedInteger('file_location_id')->index('outward_file_location_id_foreign');
            $table->string('outward_number', 255);
            $table->string('title', 255);
            $table->string('description', 255);
            $table->string('attachment', 255);
            $table->text('attachment_size');
            $table->text('attachment_type');
            $table->string('acedemic_year', 255);
            $table->string('outward_date', 255);
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
        Schema::dropIfExists('outward');
    }
};
