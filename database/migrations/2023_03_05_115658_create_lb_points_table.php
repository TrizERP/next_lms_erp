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
        Schema::create('lb_points', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('user_id')->nullable();
            $table->integer('user_profile_id')->nullable();
            $table->integer('sub_institute_id')->nullable();
            $table->integer('syear')->nullable();
            $table->date('inserted_date')->nullable();
            $table->string('module_name', 250)->nullable();
            $table->integer('points')->nullable();
            $table->string('ip_address', 250)->nullable();
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
        Schema::dropIfExists('lb_points');
    }
};
