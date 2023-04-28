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
        Schema::create('school_sections', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('section_id')->nullable();
            $table->string('section_name', 10);
            $table->decimal('school_id', 10, 0);
            $table->decimal('syear', 4, 0);
            $table->integer('sub_institute_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('school_sections');
    }
};
