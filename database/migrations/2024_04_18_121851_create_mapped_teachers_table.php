<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mapped_teachers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('teacher_id');
            $table->unsignedBigInteger('standard_id');
            $table->unsignedBigInteger('subject_id');
            $table->integer('load')->nullable();
            $table->string('created_by',100)->nullable();
            $table->unsignedBigInteger('sub_institute_id');
            $table->integer('syear');
            $table->timestamps();
        });

        // add field load in tbluser
        Schema::table('tbluser', function (Blueprint $table) {
            $table->integer('load')->nullable()->after('employee_id'); // Add 'load' column after 'email' column
        });

        // add field load in sub_std_map
        Schema::table('sub_std_map', function (Blueprint $table) {
            $table->integer('load')->nullable()->after('display_name'); // Add 'load' column after 'email' column
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('mapped_teachers');
        // for tbluser 
        Schema::table('tbluser', function (Blueprint $table) {
            $table->dropColumn('load');
        });
        // for sub_std_map 
        Schema::table('sub_std_map', function (Blueprint $table) {
            $table->dropColumn('load');
        });
    }
};
