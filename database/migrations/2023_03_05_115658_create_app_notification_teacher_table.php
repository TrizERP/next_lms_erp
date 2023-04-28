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
        Schema::create('app_notification_teacher', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('ID');
            $table->string('NOTIFICATION_TYPE', 50)->nullable();
            $table->date('NOTIFICATION_DATE')->nullable();
            $table->integer('USER_ID')->nullable();
            $table->longText('NOTIFICATION_DESCRIPTION')->nullable();
            $table->integer('STATUS')->nullable();
            $table->integer('SUB_INSTITUTE_ID')->nullable();
            $table->integer('SYEAR')->nullable();
            $table->string('SCREEN_NAME', 100)->nullable();
            $table->timestamp('CREATED_AT')->nullable()->useCurrent();
            $table->dateTime('UPDATED_AT')->nullable();
            $table->string('CREATED_BY', 50)->nullable();
            $table->string('CREATED_IP', 250)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('app_notification_teacher');
    }
};
