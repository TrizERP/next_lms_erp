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
        Schema::create('gcm_users', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('sub_institute_id')->nullable();
            $table->longText('gcm_regid')->nullable();
            $table->string('imei_no', 50)->nullable();
            $table->string('mobile_no', 50)->nullable();
            $table->timestamp('created_on')->nullable()->useCurrent();
            $table->dateTime('updated_on')->nullable();
            $table->string('curr_version', 50)->nullable();
            $table->string('new_version', 50)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gcm_users');
    }
};
