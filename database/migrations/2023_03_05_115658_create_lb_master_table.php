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
        Schema::create('lb_master', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('grade_id')->nullable();
            $table->integer('standard_id')->nullable();
            $table->string('module_name', 250)->nullable();
            $table->decimal('per_value', 10)->nullable();
            $table->integer('points')->nullable();
            $table->string('icon', 250)->nullable();
            $table->string('description', 250)->nullable();
            $table->integer('status')->nullable();
            $table->integer('sub_institute_id')->nullable();
            $table->timestamp('created_on')->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('lb_master');
    }
};
