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
        Schema::create('err_log', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('user_id')->nullable();
            $table->longText('code')->nullable();
            $table->longText('file')->nullable();
            $table->longText('line')->nullable();
            $table->longText('message')->nullable();
            $table->longText('screen_short')->nullable();
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
        Schema::dropIfExists('err_log');
    }
};
