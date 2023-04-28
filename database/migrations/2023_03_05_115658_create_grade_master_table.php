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
        Schema::create('grade_master', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->string('grade_name', 255);
            $table->integer('sub_institute_id')->default(0);
            $table->integer('sort_order')->default(0);
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
        Schema::dropIfExists('grade_master');
    }
};
