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
        Schema::create('sms_sent_parents', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('ID');
            $table->integer('SYEAR')->nullable();
            $table->integer('STUDENT_ID')->nullable();
            $table->string('SMS_TEXT', 255)->nullable();
            $table->string('SMS_NO', 15)->nullable();
            $table->string('MODULE_NAME', 255)->nullable();
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
        Schema::dropIfExists('sms_sent_parents');
    }
};
