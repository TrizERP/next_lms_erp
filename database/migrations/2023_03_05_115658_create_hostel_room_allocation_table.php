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
        Schema::create('hostel_room_allocation', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('user_id')->default(0);
            $table->integer('user_group_id')->default(0);
            $table->integer('admission_category_id')->default(0);
            $table->integer('hostel_id')->default(0);
            $table->integer('room_id')->default(0);
            $table->string('bed_no', 100)->nullable();
            $table->string('locker_no', 100)->nullable();
            $table->string('table_no', 100)->nullable();
            $table->string('bedsheet_no', 100)->nullable();
            $table->integer('term_id')->nullable();
            $table->integer('syear')->nullable();
            $table->integer('sub_institute_id')->nullable();
            $table->timestamp('created_on')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('hostel_room_allocation');
    }
};
