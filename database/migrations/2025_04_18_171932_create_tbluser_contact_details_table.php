<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tbluser_contact_details', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->biginteger('user_id')->nullable();
            $table->string('country', 255)->nullable();
            $table->string('street1', 255)->nullable();
            $table->string('street2', 255)->nullable();
            $table->string('city', 255)->nullable();
            $table->string('state', 255)->nullable();
            $table->string('zipCode', 255)->nullable();
            $table->string('homeTelephone', 20)->nullable();
            $table->string('mobile', 20)->nullable();
            $table->string('workTelephone', 20)->nullable();
            $table->string('workEmail', 255)->nullable();
            $table->string('otherEmail', 255)->nullable();
            $table->string('other_status', 255)->nullable();
            $table->string('country_other', 255)->nullable();
            $table->string('street1_other', 255)->nullable();
            $table->string('street2_other', 255)->nullable();
            $table->string('city_other', 255)->nullable();
            $table->string('state_other', 255)->nullable();
            $table->string('zipCode_other', 255)->nullable();
            $table->biginteger('sub_institute_id')->nullable();
    
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tbluser_contact_details');
    }
};
