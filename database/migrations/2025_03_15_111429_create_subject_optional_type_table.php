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
        Schema::create('subject_optional_type', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->biginteger('syear');
            $table->biginteger('subject_id');
            $table->biginteger('standard_id');
            $table->integer('optional_type');
            $table->biginteger('sub_institute_id');
            $table->biginteger('created_by');
            $table->biginteger('updated_by');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('subject_optional_type');
    }
};
