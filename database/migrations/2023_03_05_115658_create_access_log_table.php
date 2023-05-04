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
        Schema::create('access_log', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('ID');
            $table->integer('SYEAR')->nullable();
            $table->mediumText('CURRUNT_URL')->nullable();
            $table->mediumText('CURRUNT_ROUTE')->nullable();
            $table->mediumText('QUERY')->nullable();
            $table->mediumText('BINDINGS')->nullable();
            $table->integer('USER_ID')->nullable();
            $table->string('IP', 50)->nullable();
            $table->timestamp('CREATED_ON')->useCurrent();
            $table->integer('SUB_INSTITUTE_ID')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('access_log');
    }
};
