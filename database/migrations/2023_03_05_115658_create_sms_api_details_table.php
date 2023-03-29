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
        Schema::create('sms_api_details', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->text('url');
            $table->text('pram');
            $table->text('mobile_var');
            $table->text('text_var');
            $table->text('last_var')->nullable();
            $table->integer('sub_institute_id')->default(0)->index('FK_sms_api_details_school_setup');
            $table->integer('is_active')->nullable()->default(1);
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->dateTime('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sms_api_details');
    }
};
