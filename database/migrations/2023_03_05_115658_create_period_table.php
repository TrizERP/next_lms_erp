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
        Schema::create('period', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->string('title', 255);
            $table->string('short_name', 255);
            $table->integer('sort_order');
            $table->string('used_for_attendance', 255)->nullable();
            $table->time('start_time');
            $table->time('end_time');
            $table->string('length', 255)->nullable();
            $table->unsignedInteger('academic_section_id')->nullable();
            $table->unsignedInteger('academic_year_id')->index('period_academic_year_id_foreign');
            $table->integer('status')->nullable();
            $table->integer('sub_institute_id')->nullable();
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
        Schema::dropIfExists('period');
    }
};
