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
        Schema::create('tbluser', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->string('user_name', 100);
            $table->string('password', 100);
            $table->string('name_suffix', 50)->nullable();
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('email', 100);
            $table->string('mobile', 50);
            $table->string('gender', 10)->nullable();
            $table->date('birthdate')->nullable();
            $table->string('address', 255)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('pincode', 50)->nullable();
            $table->string('otp', 10)->nullable();
            $table->unsignedInteger('user_profile_id')->index('user_profile_id');
            $table->string('join_year', 50);
            $table->string('image', 50)->default('');
            $table->string('plain_password', 100)->nullable();
            $table->integer('sub_institute_id')->default(0);
            $table->integer('client_id')->default(0);
            $table->integer('is_admin')->nullable();
            $table->integer('status');
            $table->string('last_login', 255)->nullable();
            $table->string('landmark', 100)->nullable();
            $table->string('address_2', 100)->nullable();
            $table->timestamp('created_on')->nullable()->useCurrent();
            $table->date('expire_date')->nullable();
            $table->integer('total_lecture')->nullable();
            $table->text('subject_ids')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tbluser');
    }
};
