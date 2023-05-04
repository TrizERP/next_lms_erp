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
        Schema::create('lms_doubt_conversation', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('doubt_id');
            $table->longText('message');
            $table->integer('user_id');
            $table->integer('user_profile_id');
            $table->integer('syear');
            $table->integer('sub_institute_id');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('lms_doubt_conversation');
    }
};
