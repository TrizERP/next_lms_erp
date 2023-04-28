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
        Schema::create('temp_signup', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->string('user_type', 250)->nullable();
            $table->string('first_name', 250)->nullable();
            $table->string('last_name', 250)->nullable();
            $table->string('gender', 5)->nullable();
            $table->date('birthdate')->nullable();
            $table->string('email', 250)->nullable();
            $table->string('mobile', 250)->nullable();
            $table->string('otp', 250)->nullable();
            $table->string('institute_name', 250)->nullable();
            $table->string('institute_image', 250)->nullable();
            $table->string('syear', 250)->nullable();
            $table->string('standard_id', 250)->nullable();
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
        Schema::dropIfExists('temp_signup');
    }
};
