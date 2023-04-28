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
        Schema::create('lms_portfolio', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('user_id')->nullable();
            $table->integer('sub_institute_id')->nullable();
            $table->integer('syear')->nullable();
            $table->integer('user_profile_id')->nullable();
            $table->string('title', 250)->nullable();
            $table->longText('description')->nullable();
            $table->string('file_name', 250)->nullable();
            $table->string('type', 250)->nullable();
            $table->string('feedback', 250)->nullable();
            $table->integer('feedback_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('lms_portfolio');
    }
};
