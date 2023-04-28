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
        Schema::create('dynamic_dashboard', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('user_id')->nullable();
            $table->integer('user_profile_id')->nullable();
            $table->integer('sub_institute_id')->nullable();
            $table->integer('menu_id')->nullable();
            $table->string('menu_title', 520)->nullable();
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
        Schema::dropIfExists('dynamic_dashboard');
    }
};
