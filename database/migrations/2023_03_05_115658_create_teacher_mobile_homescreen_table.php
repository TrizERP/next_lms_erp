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
        Schema::create('teacher_mobile_homescreen', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('sub_institute_id')->nullable();
            $table->integer('user_profile_id')->nullable();
            $table->string('user_profile_name', 250)->nullable();
            $table->string('main_title', 100)->nullable();
            $table->string('menu_type', 100)->nullable()->default('Heading');
            $table->string('main_title_color_code', 50)->nullable();
            $table->mediumText('main_title_background_image')->nullable();
            $table->string('sub_title_of_main', 100)->nullable();
            $table->mediumText('sub_title_icon')->nullable();
            $table->mediumText('sub_title_api')->nullable();
            $table->mediumText('sub_title_api_param')->nullable();
            $table->integer('main_sort_order')->nullable();
            $table->integer('sub_title_sort_order')->nullable();
            $table->string('screen_name', 50)->nullable();
            $table->string('status', 5)->nullable();
            $table->string('temp_status', 5)->nullable();
            $table->timestamp('created_on')->nullable()->useCurrent();
            $table->dateTime('updated_on')->nullable();
            $table->integer('updated_by')->nullable();
            $table->string('updated_ip_address', 25)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('teacher_mobile_homescreen');
    }
};
