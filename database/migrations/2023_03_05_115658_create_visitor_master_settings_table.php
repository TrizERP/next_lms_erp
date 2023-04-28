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
        Schema::create('visitor_master_settings', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->mediumText('welcome_staff_msg')->nullable();
            $table->mediumText('welcome_visitor_msg')->nullable();
            $table->mediumText('exit_visitor_msg')->nullable();
            $table->integer('welcome_staff_msg_enable')->nullable();
            $table->integer('welcome_visitor_msg_enable')->nullable();
            $table->integer('exit_visitor_msg_enable')->nullable();
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
        Schema::dropIfExists('visitor_master_settings');
    }
};
