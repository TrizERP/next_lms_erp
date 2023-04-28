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
        Schema::create('dashboard_master', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->string('module_name', 50)->nullable();
            $table->string('module_description', 50)->nullable();
            $table->string('type', 50)->nullable();
            $table->string('image', 50)->nullable();
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
        Schema::dropIfExists('dashboard_master');
    }
};
