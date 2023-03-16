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
        Schema::create('subject', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->string('subject_name', 255)->nullable();
            $table->string('subject_code', 255)->nullable();
            $table->string('subject_type', 255)->nullable();
            $table->string('short_name', 255)->nullable();
            $table->bigInteger('sub_institute_id')->nullable()->index('FK_subject_school_setup');
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
        Schema::dropIfExists('subject');
    }
};
