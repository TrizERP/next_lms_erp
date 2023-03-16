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
        Schema::create('enrollment_prefix_master', function (Blueprint $table) {
            $table->comment('');
            $table->integer('id', true);
            $table->integer('sub_institute_id')->nullable();
            $table->string('standard_from', 50)->nullable();
            $table->string('standard_to', 50)->nullable();
            $table->string('standards', 100)->nullable();
            $table->string('prefix', 50)->nullable();
            $table->timestamp('created_on')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('enrollment_prefix_master');
    }
};
