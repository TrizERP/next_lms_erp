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
        Schema::create('tbluserprofilemaster', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->bigInteger('parent_id');
            $table->string('name', 255);
            $table->string('description', 255);
            $table->integer('sort_order');
            $table->integer('status');
            $table->bigInteger('sub_institute_id')->index('FK_tbluserprofilemaster_school_setup');
            $table->bigInteger('client_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tbluserprofilemaster');
    }
};
