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
        Schema::create('hostel_visitor_master', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('sub_institute_id')->nullable();
            $table->string('name', 255);
            $table->string('contact', 255);
            $table->string('email', 255);
            $table->string('coming_from', 255);
            $table->string('to_meet', 255)->nullable();
            $table->string('relation', 255);
            $table->string('meet_date', 255);
            $table->string('in_time', 255);
            $table->string('out_time', 255);
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
        Schema::dropIfExists('hostel_visitor_master');
    }
};
