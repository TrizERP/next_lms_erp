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
        Schema::create('tblstudent_doc_std_mapping', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('sub_institute_id')->default(0)->nullable();
            $table->integer('document_type_id')->default(0)->nullable();
            $table->integer('standard_id')->default(0)->nullable();
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
        Schema::dropIfExists('tblstudent_doc_std_mapping');
    }
};
