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
        Schema::create('master_compliance', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name',255);
            $table->text('description')->nullable();
            $table->string('standard_name',255)->nullable();
            $table->unsignedInteger('assigned_to');  
            $table->foreign('assigned_to')->references('id')->on('tbluser');
            $table->date('duedate');
            $table->string('attachment',255)->nullable(); 
            $table->biginteger('sub_institute_id');
            $table->biginteger('created_by');
            $table->softDeletes();           
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
        Schema::dropIfExists('master_compliance');
    }
};
