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
        Schema::create('email_sent_parents', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('ID');
            $table->integer('SYEAR')->nullable();
            $table->mediumText('EMAIL')->nullable();
            $table->mediumText('SUBJECT')->nullable();
            $table->mediumText('EMAIL_TEXT')->nullable();
            $table->string('ATTECHMENT', 255)->nullable();
            $table->integer('USER_ID')->nullable();
            $table->string('IP', 50)->nullable();
            $table->timestamp('CREATED_ON')->useCurrent();
            $table->integer('sub_institute_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('email_sent_parents');
    }
};
