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
        Schema::create('counselling_course', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->string('title', 250)->nullable();
            $table->longText('description')->nullable();
            $table->string('image', 250)->nullable();
            $table->integer('sort_order')->nullable();
            $table->integer('sub_institute_id')->nullable();
            $table->integer('status')->nullable();
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
        Schema::dropIfExists('counselling_course');
    }
};
