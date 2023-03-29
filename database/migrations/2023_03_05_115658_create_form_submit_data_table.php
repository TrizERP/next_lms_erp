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
        Schema::create('form_submit_data', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->bigInteger('form_id')->default(0);
            $table->bigInteger('user_id')->default(0);
            $table->bigInteger('standard')->default(0);
            $table->bigInteger('subject')->default(0);
            $table->bigInteger('chapter')->nullable();
            $table->longText('form_data');
            $table->bigInteger('sub_institute_id');
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->dateTime('updated_at')->nullable();

            $table->unique(['form_id', 'chapter', 'sub_institute_id'], 'key');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('form_submit_data');
    }
};
